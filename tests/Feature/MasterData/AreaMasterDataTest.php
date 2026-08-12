<?php

namespace Tests\Feature\MasterData;

use App\Models\Area;
use App\Models\AuditEvent;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
    }

    public function test_area_search_is_strictly_scoped_to_the_selected_organization(): void
    {
        $valley = $this->organization('VALLEY', 'Valley Water');
        $other = $this->organization('OTHER', 'Other Distributor');

        Area::query()->create([
            'organization_id' => $valley->id,
            'code' => 'TGI',
            'name_en' => 'Taunggyi',
            'name_my' => 'တောင်ကြီး',
            'sort_order' => 10,
        ]);
        Area::query()->create([
            'organization_id' => $other->id,
            'code' => 'MDY',
            'name_en' => 'Mandalay',
            'sort_order' => 10,
        ]);

        $response = $this->withHeader('X-Organization-ID', $valley->public_id)
            ->getJson('/api/v1/master-data/areas?search=Taung&status=active');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'TGI')
            ->assertJsonMissing(['code' => 'MDY']);
    }

    public function test_area_creation_normalizes_code_prevents_duplicates_and_writes_audit(): void
    {
        $organization = $this->organization('VALLEY', 'Valley Water');

        $response = $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson('/api/v1/master-data/areas', [
                'code' => ' aty ',
                'name_en' => 'Aye Thar Yar',
                'name_my' => 'အေးသာယာ',
                'sort_order' => 20,
                'status' => 'active',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.code', 'ATY')
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseHas('areas', [
            'organization_id' => $organization->id,
            'code' => 'ATY',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->id,
            'action' => 'master_data.area.created',
            'entity_public_id' => $response->json('data.id'),
        ]);

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson('/api/v1/master-data/areas', [
                'code' => 'ATY',
                'name_en' => 'Duplicate',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_stale_updates_are_rejected_without_overwriting_newer_values(): void
    {
        $organization = $this->organization('VALLEY', 'Valley Water');
        $area = Area::query()->create([
            'organization_id' => $organization->id,
            'code' => 'NSN',
            'name_en' => 'Nam San',
            'sort_order' => 30,
        ]);

        $payload = [
            'version' => 1,
            'code' => 'NSN',
            'name_en' => 'Nam San Township',
            'name_my' => null,
            'description' => null,
            'parent_area_public_id' => null,
            'sort_order' => 30,
            'status' => 'active',
        ];

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->putJson("/api/v1/master-data/areas/{$area->public_id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->putJson("/api/v1/master-data/areas/{$area->public_id}", [...$payload, 'name_en' => 'Stale overwrite'])
            ->assertConflict()
            ->assertJsonPath('code', 'stale_version');

        $this->assertDatabaseHas('areas', ['id' => $area->id, 'name_en' => 'Nam San Township', 'lock_version' => 2]);
    }

    public function test_archiving_requires_a_reason_and_preserves_an_audit_event(): void
    {
        $organization = $this->organization('VALLEY', 'Valley Water');
        $area = Area::query()->create([
            'organization_id' => $organization->id,
            'code' => 'OLD',
            'name_en' => 'Old Area',
        ]);

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->patchJson("/api/v1/master-data/areas/{$area->public_id}/archive", ['version' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->patchJson("/api/v1/master-data/areas/{$area->public_id}/archive", [
                'version' => 1,
                'reason' => 'Territory structure retired',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.version', 2);

        $audit = AuditEvent::query()->where('action', 'master_data.area.archived')->firstOrFail();
        $this->assertSame('Territory structure retired', $audit->reason);
        $this->assertSame('active', $audit->before_state['status']);
        $this->assertSame('archived', $audit->after_state['status']);
    }

    public function test_an_area_cannot_be_moved_below_its_descendant(): void
    {
        $organization = $this->organization('VALLEY', 'Valley Water');
        $parent = Area::query()->create([
            'organization_id' => $organization->id,
            'code' => 'PARENT',
            'name_en' => 'Parent Area',
        ]);
        $child = Area::query()->create([
            'organization_id' => $organization->id,
            'parent_area_id' => $parent->id,
            'code' => 'CHILD',
            'name_en' => 'Child Area',
        ]);

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->putJson("/api/v1/master-data/areas/{$parent->public_id}", [
                'version' => 1,
                'code' => 'PARENT',
                'name_en' => 'Parent Area',
                'name_my' => null,
                'description' => null,
                'parent_area_public_id' => $child->public_id,
                'sort_order' => 0,
                'status' => 'active',
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'invalid_parent_cycle');

        $this->assertNull($parent->refresh()->parent_area_id);
    }

    public function test_an_invalid_explicit_organization_is_never_replaced_by_the_local_default(): void
    {
        $this->organization('VALLEY', 'Valley Water');

        $this->withHeader('X-Organization-ID', '01INVALIDORGANIZATION00000')
            ->getJson('/api/v1/master-data/areas')
            ->assertForbidden()
            ->assertJsonPath('code', 'organization_context_required');
    }

    private function organization(string $code, string $name): Organization
    {
        return Organization::query()->create([
            'code' => $code,
            'name' => $name,
            'default_locale' => 'en',
            'currency' => 'MMK',
            'timezone' => 'Asia/Yangon',
            'status' => 'active',
        ]);
    }
}
