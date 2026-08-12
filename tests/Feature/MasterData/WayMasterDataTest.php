<?php

namespace Tests\Feature\MasterData;

use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\Warehouse;
use App\Models\Way;
use App\Models\WayVersion;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WayMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
        $this->seed(PhaseOneReferenceSeeder::class);
    }

    public function test_way_creation_normalizes_code_creates_initial_policy_and_writes_audit(): void
    {
        $organization = $this->organization();
        $response = $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson('/api/v1/master-data/ways', $this->payload(['code' => ' tgi-north ']));

        $response->assertCreated()
            ->assertJsonPath('data.code', 'TGI-NORTH')
            ->assertJsonPath('data.policy.version', 1)
            ->assertJsonPath('data.policy.area.code', 'TGI')
            ->assertJsonPath('data.policy.service_days.0', 'mon');

        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.way.created', 'entity_public_id' => $response->json('data.id')]);
    }

    public function test_policy_change_creates_an_effective_version_and_stale_update_is_rejected(): void
    {
        $organization = $this->organization();
        $created = $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson('/api/v1/master-data/ways', $this->payload())
            ->assertCreated();
        $wayId = $created->json('data.id');

        $updatedPayload = $this->payload([
            'version' => 1,
            'service_days' => ['tue', 'thu', 'sat'],
            'effective_from' => '2026-09-01',
            'change_reason' => 'New monthly service plan',
        ]);
        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->putJson("/api/v1/master-data/ways/{$wayId}", $updatedPayload)
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.policy.version', 2)
            ->assertJsonPath('data.policy.service_days.0', 'tue');

        $way = Way::query()->where('public_id', $wayId)->firstOrFail();
        $this->assertDatabaseHas('way_versions', ['way_id' => $way->id, 'version' => 1, 'status' => 'superseded', 'effective_to' => '2026-08-31 00:00:00']);
        $this->assertDatabaseHas('way_versions', ['way_id' => $way->id, 'version' => 2, 'status' => 'active']);

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->putJson("/api/v1/master-data/ways/{$wayId}", [...$updatedPayload, 'name_en' => 'Stale overwrite'])
            ->assertConflict()
            ->assertJsonPath('code', 'stale_version');
    }

    public function test_policy_revision_must_start_after_the_current_version(): void
    {
        $organization = $this->organization();
        $created = $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson('/api/v1/master-data/ways', $this->payload())
            ->assertCreated();

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->putJson('/api/v1/master-data/ways/'.$created->json('data.id'), $this->payload([
                'version' => 1,
                'service_days' => ['fri'],
                'effective_from' => '2026-08-01',
                'change_reason' => 'Invalid same-day revision',
            ]))
            ->assertConflict()
            ->assertJsonPath('code', 'way_version_date_conflict');

        $this->assertDatabaseCount('way_versions', 1);
    }

    public function test_way_list_is_organization_scoped_and_supports_area_filtering(): void
    {
        $organization = $this->organization();
        $created = $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson('/api/v1/master-data/ways', $this->payload())
            ->assertCreated();
        $area = Area::query()->where('organization_id', $organization->id)->where('code', 'TGI')->firstOrFail();

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->getJson("/api/v1/master-data/ways?search=North&area={$area->public_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $created->json('data.id'));

        $other = Organization::query()->create(['code' => 'OTHER', 'name' => 'Other', 'default_locale' => 'en', 'currency' => 'MMK', 'timezone' => 'Asia/Yangon', 'status' => 'active']);
        Way::query()->create(['organization_id' => $other->id, 'code' => 'OTHER-WAY', 'name_en' => 'Other Way']);
        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->getJson('/api/v1/master-data/ways')
            ->assertJsonMissing(['code' => 'OTHER-WAY']);
    }

    public function test_archiving_requires_a_reason_and_preserves_the_latest_policy(): void
    {
        $organization = $this->organization();
        $created = $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson('/api/v1/master-data/ways', $this->payload())
            ->assertCreated();
        $wayId = $created->json('data.id');

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->patchJson("/api/v1/master-data/ways/{$wayId}/archive", ['version' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->patchJson("/api/v1/master-data/ways/{$wayId}/archive", ['version' => 1, 'reason' => 'Territory retired after service review'])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.policy.status', 'archived');

        $this->assertSame('Territory retired after service review', AuditEvent::query()->where('action', 'master_data.way.archived')->firstOrFail()->reason);
        $this->assertSame('archived', WayVersion::query()->firstOrFail()->status);
    }

    private function organization(): Organization
    {
        return Organization::query()->where('code', 'VALLEY')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        $organization = $this->organization();
        $area = Area::query()->where('organization_id', $organization->id)->where('code', 'TGI')->firstOrFail();
        $warehouse = Warehouse::query()->where('organization_id', $organization->id)->where('code', 'TGI-WH')->firstOrFail();

        return [
            'code' => 'TGI-NORTH',
            'name_en' => 'Taunggyi North',
            'name_my' => 'တောင်ကြီး မြောက်ပိုင်း',
            'description' => 'Northern commercial service territory',
            'status' => 'active',
            'area_public_id' => $area->public_id,
            'default_warehouse_public_id' => $warehouse->public_id,
            'boundary_description' => 'North of central Taunggyi',
            'service_days' => ['mon', 'wed', 'fri'],
            'delivery_window_start' => '09:00',
            'delivery_window_end' => '16:00',
            'effective_from' => '2026-08-01',
            'change_reason' => null,
            ...$overrides,
        ];
    }
}
