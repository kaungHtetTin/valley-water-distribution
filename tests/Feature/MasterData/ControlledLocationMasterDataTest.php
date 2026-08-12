<?php

namespace Tests\Feature\MasterData;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Sku;
use App\Models\Warehouse;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlledLocationMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
        $this->seed(PhaseOneReferenceSeeder::class);
    }

    public function test_dashboard_is_scoped_and_exposes_active_warehouse_sku_and_branch_options(): void
    {
        $organization = $this->organization();
        $this->actingFor($organization)->getJson('/api/v1/master-data/controlled-locations')
            ->assertOk()->assertJsonCount(0, 'data.zones')->assertJsonCount(0, 'data.bins')->assertJsonCount(0, 'data.replenishment')
            ->assertJsonCount(0, 'data.cash')->assertJsonCount(2, 'data.warehouses')->assertJsonCount(3, 'data.skus')->assertJsonCount(1, 'data.branches');
    }

    public function test_zone_and_bin_lifecycle_enforces_dependency_archive_order(): void
    {
        $organization = $this->organization();
        $zone = $this->actingFor($organization)->postJson('/api/v1/master-data/controlled-locations/zones', $this->zonePayload())
            ->assertCreated()->assertJsonPath('data.code', 'STORAGE-A')->assertJsonPath('data.warehouse.code', 'TGI-WH');
        $zoneId = $zone->json('data.id');
        $bin = $this->actingFor($organization)->postJson('/api/v1/master-data/controlled-locations/bins', $this->binPayload($zoneId))
            ->assertCreated()->assertJsonPath('data.zone.code', 'STORAGE-A')->assertJsonPath('data.capacity_units', '1200.0000');
        $binId = $bin->json('data.id');

        $this->actingFor($organization)->patchJson("/api/v1/master-data/controlled-locations/zones/{$zoneId}/archive", ['version' => 1, 'reason' => 'Layout revised'])
            ->assertConflict()->assertJsonPath('code', 'zone_has_bins');
        $this->actingFor($organization)->patchJson("/api/v1/master-data/controlled-locations/bins/{$binId}/archive", ['version' => 1, 'reason' => 'Bin removed'])
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->actingFor($organization)->patchJson("/api/v1/master-data/controlled-locations/zones/{$zoneId}/archive", ['version' => 1, 'reason' => 'Layout revised'])
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.warehouse_zone.archived', 'entity_public_id' => $zoneId]);
    }

    public function test_replenishment_thresholds_are_ordered_unique_and_use_base_units(): void
    {
        $organization = $this->organization();
        $payload = $this->policyPayload();
        $this->actingFor($organization)->postJson('/api/v1/master-data/controlled-locations/replenishment', [...$payload, 'safety_stock' => 200, 'reorder_point' => 100])
            ->assertUnprocessable()->assertJsonValidationErrors('safety_stock');
        $created = $this->actingFor($organization)->postJson('/api/v1/master-data/controlled-locations/replenishment', $payload)
            ->assertCreated()->assertJsonPath('data.sku.code', 'VAL-500')->assertJsonPath('data.sku.base_uom.code', 'BTL')->assertJsonPath('data.target_stock', '1000.0000');
        $this->actingFor($organization)->postJson('/api/v1/master-data/controlled-locations/replenishment', $payload)
            ->assertConflict()->assertJsonPath('code', 'duplicate_controlled_location');
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.warehouse_sku_policy.created', 'entity_public_id' => $created->json('data.id')]);
    }

    public function test_cash_location_has_no_editable_balance_and_uses_optimistic_locking(): void
    {
        $organization = $this->organization();
        $payload = $this->cashPayload();
        $created = $this->actingFor($organization)->postJson('/api/v1/master-data/controlled-locations/cash', [...$payload, 'code' => ' tgi-safe '])
            ->assertCreated()->assertJsonPath('data.code', 'TGI-SAFE')->assertJsonPath('data.branch.code', 'TGI')->assertJsonMissingPath('data.balance');
        $id = $created->json('data.id');
        $this->actingFor($organization)->putJson("/api/v1/master-data/controlled-locations/cash/{$id}", [...$payload, 'code' => 'TGI-SAFE', 'name_en' => 'Taunggyi Main Safe', 'version' => 1])
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.name.en', 'Taunggyi Main Safe');
        $this->actingFor($organization)->putJson("/api/v1/master-data/controlled-locations/cash/{$id}", [...$payload, 'code' => 'TGI-SAFE', 'version' => 1])
            ->assertConflict()->assertJsonPath('code', 'stale_version');
    }

    public function test_warehouse_archive_is_blocked_by_active_topology(): void
    {
        $organization = $this->organization();
        $this->actingFor($organization)->postJson('/api/v1/master-data/controlled-locations/zones', $this->zonePayload())->assertCreated();
        $warehouse = Warehouse::query()->where('organization_id', $organization->id)->where('code', 'TGI-WH')->firstOrFail();

        $this->actingFor($organization)->patchJson("/api/v1/master-data/warehouses/{$warehouse->public_id}/archive", ['version' => 1, 'reason' => 'Warehouse retired'])
            ->assertConflict()->assertJsonPath('code', 'warehouse_has_topology');
    }

    private function organization(): Organization
    {
        return Organization::query()->where('code', 'VALLEY')->firstOrFail();
    }

    private function actingFor(Organization $organization): static
    {
        return $this->withHeader('X-Organization-ID', $organization->public_id);
    }

    private function zonePayload(): array
    {
        $warehouse = Warehouse::query()->where('organization_id', $this->organization()->id)->where('code', 'TGI-WH')->firstOrFail();

        return ['warehouse_public_id' => $warehouse->public_id, 'code' => ' storage-a ', 'name_en' => 'Storage Zone A', 'name_my' => null, 'zone_type' => 'storage', 'temperature_class' => 'ambient', 'sort_order' => 10, 'status' => 'active'];
    }

    private function binPayload(string $zoneId): array
    {
        return ['zone_public_id' => $zoneId, 'code' => ' A-01 ', 'label' => 'Aisle A Bin 01', 'bin_type' => 'bulk', 'capacity_units' => 1200, 'sort_order' => 10, 'status' => 'active'];
    }

    private function policyPayload(): array
    {
        $organization = $this->organization();
        $warehouse = Warehouse::query()->where('organization_id', $organization->id)->where('code', 'TGI-WH')->firstOrFail();
        $sku = Sku::query()->where('organization_id', $organization->id)->where('code', 'VAL-500')->firstOrFail();

        return ['warehouse_public_id' => $warehouse->public_id, 'sku_public_id' => $sku->public_id, 'safety_stock' => 100, 'reorder_point' => 300, 'target_stock' => 1000, 'replenishment_lead_days' => 2, 'status' => 'active'];
    }

    private function cashPayload(): array
    {
        $branch = Branch::query()->where('organization_id', $this->organization()->id)->where('code', 'TGI')->firstOrFail();

        return ['branch_public_id' => $branch->public_id, 'code' => 'TGI-CASH', 'name_en' => 'Taunggyi Cashier', 'name_my' => null, 'location_type' => 'cashier', 'currency' => 'MMK', 'description' => 'Office cashier custody point', 'status' => 'active'];
    }
}
