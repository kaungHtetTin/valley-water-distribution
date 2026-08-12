<?php

namespace Tests\Feature\MasterData;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Warehouse;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTemplateMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
        $this->seed(PhaseOneReferenceSeeder::class);
    }

    public function test_route_template_preserves_ordered_way_coverage_and_audits_creation(): void
    {
        $organization = $this->organization();
        $first = $this->createWay('TGI-NORTH', 'Taunggyi North');
        $second = $this->createWay('TGI-SOUTH', 'Taunggyi South');
        $created = $this->actingFor($organization)->postJson('/api/v1/master-data/route-templates', $this->payload([$second, $first]))
            ->assertCreated()->assertJsonPath('data.code', 'TGI-DAILY')->assertJsonPath('data.ways.0.code', 'TGI-SOUTH')
            ->assertJsonPath('data.ways.0.sequence', 1)->assertJsonPath('data.ways.1.code', 'TGI-NORTH')->assertJsonPath('data.branch.code', 'TGI');

        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.route_template.created', 'entity_public_id' => $created->json('data.id')]);
        $this->assertDatabaseHas('route_template_ways', ['sequence' => 1]);
    }

    public function test_source_warehouse_must_belong_to_selected_branch(): void
    {
        $organization = $this->organization();
        $way = $this->createWay('TGI-EAST', 'Taunggyi East');
        $otherBranch = Branch::query()->create(['organization_id' => $organization->id, 'code' => 'OTHER', 'name_en' => 'Other Branch', 'status' => 'active']);

        $this->actingFor($organization)->postJson('/api/v1/master-data/route-templates', [...$this->payload([$way]), 'branch_public_id' => $otherBranch->public_id])
            ->assertConflict()->assertJsonPath('code', 'route_warehouse_branch_mismatch');
        $this->assertDatabaseCount('route_templates', 0);
    }

    public function test_update_reorders_ways_and_rejects_a_stale_version(): void
    {
        $organization = $this->organization();
        $first = $this->createWay('TGI-NORTH', 'Taunggyi North');
        $second = $this->createWay('TGI-SOUTH', 'Taunggyi South');
        $created = $this->actingFor($organization)->postJson('/api/v1/master-data/route-templates', $this->payload([$first, $second]))->assertCreated();
        $id = $created->json('data.id');
        $update = [...$this->payload([$second, $first]), 'version' => 1, 'name_en' => 'Taunggyi Daily Dispatch'];

        $this->actingFor($organization)->putJson("/api/v1/master-data/route-templates/{$id}", $update)
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.ways.0.code', 'TGI-SOUTH');
        $this->actingFor($organization)->putJson("/api/v1/master-data/route-templates/{$id}", $update)
            ->assertConflict()->assertJsonPath('code', 'stale_version');
    }

    public function test_way_archive_is_blocked_until_referencing_template_is_archived(): void
    {
        $organization = $this->organization();
        $way = $this->createWay('TGI-CORE', 'Taunggyi Core');
        $created = $this->actingFor($organization)->postJson('/api/v1/master-data/route-templates', $this->payload([$way]))->assertCreated();

        $this->actingFor($organization)->patchJson("/api/v1/master-data/ways/{$way}/archive", ['version' => 1, 'reason' => 'Territory retired'])
            ->assertConflict()->assertJsonPath('code', 'way_has_route_templates');
        $this->actingFor($organization)->patchJson('/api/v1/master-data/route-templates/'.$created->json('data.id').'/archive', ['version' => 1, 'reason' => 'Route plan retired'])
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->actingFor($organization)->patchJson("/api/v1/master-data/ways/{$way}/archive", ['version' => 1, 'reason' => 'Territory retired'])
            ->assertOk()->assertJsonPath('data.status', 'archived');
    }

    public function test_warehouse_archive_is_blocked_by_active_route_template(): void
    {
        $organization = $this->organization();
        $way = $this->createWay('NSN-CORE', 'Nam San Core', 'NSN');
        $warehouse = Warehouse::query()->where('organization_id', $organization->id)->where('code', 'NSN-WH')->firstOrFail();
        $this->actingFor($organization)->postJson('/api/v1/master-data/route-templates', [...$this->payload([$way]), 'source_warehouse_public_id' => $warehouse->public_id, 'code' => 'NSN-DAILY'])->assertCreated();

        $this->actingFor($organization)->patchJson("/api/v1/master-data/warehouses/{$warehouse->public_id}/archive", ['version' => 1, 'reason' => 'Warehouse retired'])
            ->assertConflict()->assertJsonPath('code', 'warehouse_has_route_templates');
    }

    private function organization(): Organization
    {
        return Organization::query()->where('code', 'VALLEY')->firstOrFail();
    }

    private function actingFor(Organization $organization): static
    {
        return $this->withHeader('X-Organization-ID', $organization->public_id);
    }

    private function createWay(string $code, string $name, string $areaCode = 'TGI'): string
    {
        $organization = $this->organization();
        $area = Area::query()->where('organization_id', $organization->id)->where('code', $areaCode)->firstOrFail();

        return $this->actingFor($organization)->postJson('/api/v1/master-data/ways', ['code' => $code, 'name_en' => $name, 'name_my' => null, 'description' => null, 'status' => 'active', 'area_public_id' => $area->public_id, 'default_warehouse_public_id' => null, 'boundary_description' => null, 'service_days' => ['mon', 'wed', 'fri'], 'delivery_window_start' => null, 'delivery_window_end' => null, 'effective_from' => '2026-08-01', 'change_reason' => null])->assertCreated()->json('data.id');
    }

    private function payload(array $wayIds): array
    {
        $organization = $this->organization();
        $branch = Branch::query()->where('organization_id', $organization->id)->where('code', 'TGI')->firstOrFail();
        $warehouse = Warehouse::query()->where('organization_id', $organization->id)->where('code', 'TGI-WH')->firstOrFail();

        return ['branch_public_id' => $branch->public_id, 'source_warehouse_public_id' => $warehouse->public_id, 'code' => ' tgi-daily ', 'name_en' => 'Taunggyi Daily Route', 'name_my' => null, 'description' => 'Reusable Way order for daily planning', 'service_days' => ['mon', 'wed', 'fri'], 'departure_time' => '08:00', 'estimated_duration_minutes' => 240, 'effective_from' => '2026-08-01', 'effective_to' => null, 'way_public_ids' => $wayIds, 'status' => 'active'];
    }
}
