<?php

namespace Tests\Feature\MasterData;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Warehouse;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
        $this->seed(PhaseOneReferenceSeeder::class);
    }

    public function test_location_registers_and_options_are_organization_scoped(): void
    {
        $organization = $this->organization();
        $other = Organization::query()->create(['code' => 'OTHER', 'name' => 'Other', 'default_locale' => 'en', 'currency' => 'MMK', 'timezone' => 'Asia/Yangon', 'status' => 'active']);
        Branch::query()->create(['organization_id' => $other->id, 'code' => 'OTHER', 'name_en' => 'Other Branch']);

        $this->actingFor($organization)->getJson('/api/v1/master-data/branches')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'TGI')->assertJsonPath('data.0.warehouses_count', 2)
            ->assertJsonMissing(['code' => 'OTHER']);
        $this->actingFor($organization)->getJson('/api/v1/master-data/warehouses?search=TGI')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.branch.code', 'TGI');
        $this->actingFor($organization)->getJson('/api/v1/master-data/locations/options')
            ->assertOk()->assertJsonCount(1, 'data.branches')->assertJsonCount(3, 'data.areas');
    }

    public function test_branch_creation_normalizes_fields_audits_and_rejects_stale_updates(): void
    {
        $organization = $this->organization();
        $created = $this->actingFor($organization)->postJson('/api/v1/master-data/branches', $this->branchPayload())
            ->assertCreated()->assertJsonPath('data.code', 'NYAUNGSHWE')->assertJsonPath('data.currency', 'MMK');
        $branchId = $created->json('data.id');

        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.branch.created', 'entity_public_id' => $branchId]);
        $this->actingFor($organization)->putJson("/api/v1/master-data/branches/{$branchId}", [...$this->branchPayload(), 'version' => 1, 'name_en' => 'Nyaungshwe Operations'])
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.name.en', 'Nyaungshwe Operations');
        $this->actingFor($organization)->putJson("/api/v1/master-data/branches/{$branchId}", [...$this->branchPayload(), 'version' => 1])
            ->assertConflict()->assertJsonPath('code', 'stale_version');
    }

    public function test_branch_archive_is_blocked_until_live_warehouses_are_removed(): void
    {
        $organization = $this->organization();
        $branch = Branch::query()->where('organization_id', $organization->id)->where('code', 'TGI')->firstOrFail();

        $this->actingFor($organization)->patchJson("/api/v1/master-data/branches/{$branch->public_id}/archive", ['version' => 1, 'reason' => 'Branch consolidation'])
            ->assertConflict()->assertJsonPath('code', 'branch_has_warehouses');

        $empty = $this->actingFor($organization)->postJson('/api/v1/master-data/branches', $this->branchPayload())->assertCreated();
        $this->actingFor($organization)->patchJson('/api/v1/master-data/branches/'.$empty->json('data.id').'/archive', ['version' => 1, 'reason' => 'Location plan cancelled'])
            ->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('data.warehouses_count', 0);
    }

    public function test_warehouse_creation_validates_map_pair_and_writes_audit(): void
    {
        $organization = $this->organization();
        $payload = $this->warehousePayload();
        $this->actingFor($organization)->postJson('/api/v1/master-data/warehouses', [...$payload, 'longitude' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('longitude');

        $created = $this->actingFor($organization)->postJson('/api/v1/master-data/warehouses', $payload)
            ->assertCreated()->assertJsonPath('data.code', 'ATY-SAT')->assertJsonPath('data.branch.code', 'TGI')
            ->assertJsonPath('data.area.code', 'ATY')->assertJsonPath('data.map_position.latitude', '20.7617000');
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.warehouse.created', 'entity_public_id' => $created->json('data.id')]);
    }

    public function test_active_way_reference_blocks_warehouse_archive(): void
    {
        $organization = $this->organization();
        $area = Area::query()->where('organization_id', $organization->id)->where('code', 'TGI')->firstOrFail();
        $warehouse = Warehouse::query()->where('organization_id', $organization->id)->where('code', 'TGI-WH')->firstOrFail();
        $this->actingFor($organization)->postJson('/api/v1/master-data/ways', [
            'code' => 'TGI-CORE', 'name_en' => 'Taunggyi Core', 'name_my' => null, 'description' => null, 'status' => 'active',
            'area_public_id' => $area->public_id, 'default_warehouse_public_id' => $warehouse->public_id, 'boundary_description' => null,
            'service_days' => ['mon'], 'delivery_window_start' => null, 'delivery_window_end' => null, 'effective_from' => '2026-08-01', 'change_reason' => null,
        ])->assertCreated();

        $this->actingFor($organization)->patchJson("/api/v1/master-data/warehouses/{$warehouse->public_id}/archive", ['version' => 1, 'reason' => 'Warehouse relocation'])
            ->assertConflict()->assertJsonPath('code', 'warehouse_has_active_ways');
    }

    public function test_warehouse_update_uses_optimistic_locking_and_unreferenced_warehouse_can_be_archived(): void
    {
        $organization = $this->organization();
        $created = $this->actingFor($organization)->postJson('/api/v1/master-data/warehouses', $this->warehousePayload())->assertCreated();
        $warehouseId = $created->json('data.id');
        $updatedPayload = [...$this->warehousePayload(), 'version' => 1, 'name_en' => 'Aye Thar Yar Dispatch Hub'];

        $this->actingFor($organization)->putJson("/api/v1/master-data/warehouses/{$warehouseId}", $updatedPayload)
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.name.en', 'Aye Thar Yar Dispatch Hub');
        $this->actingFor($organization)->putJson("/api/v1/master-data/warehouses/{$warehouseId}", $updatedPayload)
            ->assertConflict()->assertJsonPath('code', 'stale_version');
        $this->actingFor($organization)->patchJson("/api/v1/master-data/warehouses/{$warehouseId}/archive", ['version' => 2, 'reason' => 'Dispatch plan withdrawn'])
            ->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('data.version', 3);

        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.warehouse.archived', 'entity_public_id' => $warehouseId, 'reason' => 'Dispatch plan withdrawn']);
    }

    private function organization(): Organization
    {
        return Organization::query()->where('code', 'VALLEY')->firstOrFail();
    }

    private function actingFor(Organization $organization): static
    {
        return $this->withHeader('X-Organization-ID', $organization->public_id);
    }

    private function branchPayload(): array
    {
        return ['code' => ' nyaungshwe ', 'name_en' => 'Nyaungshwe Branch', 'name_my' => null, 'phone' => '081-200000', 'address' => 'Nyaungshwe', 'timezone' => 'Asia/Yangon', 'currency' => ' mmk ', 'business_day_start' => '06:00', 'status' => 'active'];
    }

    private function warehousePayload(): array
    {
        $organization = $this->organization();
        $branch = Branch::query()->where('organization_id', $organization->id)->where('code', 'TGI')->firstOrFail();
        $area = Area::query()->where('organization_id', $organization->id)->where('code', 'ATY')->firstOrFail();

        return ['branch_public_id' => $branch->public_id, 'area_public_id' => $area->public_id, 'code' => ' aty-sat ', 'name_en' => 'Aye Thar Yar Satellite', 'name_my' => null, 'kind' => 'satellite', 'address' => 'Aye Thar Yar', 'contact_name' => 'Warehouse Lead', 'phone' => '081-300000', 'latitude' => 20.7617, 'longitude' => 97.0360, 'order_cutoff_time' => '14:30', 'service_area_note' => 'Eastern service zone', 'status' => 'active'];
    }
}
