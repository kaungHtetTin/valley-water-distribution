<?php

namespace Tests\Feature\MasterData;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PriceBook;
use App\Models\Sku;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingControlMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
        $this->seed(PhaseOneReferenceSeeder::class);
    }

    public function test_approved_price_resolution_uses_customer_assignment_precedence(): void
    {
        $organization = $this->organization();
        $customer = $this->createCustomer();
        $sku = Sku::query()->where('organization_id', $organization->id)->where('code', 'VAL-500')->firstOrFail();
        $uom = UnitOfMeasure::query()->where('organization_id', $organization->id)->where('code', 'BTL')->firstOrFail();
        $retail = PriceBook::query()->where('organization_id', $organization->id)->where('code', 'RETAIL-DEFAULT')->firstOrFail();
        $special = PriceBook::query()->where('organization_id', $organization->id)->where('code', 'SPECIAL-DEFAULT')->firstOrFail();
        $retailPrice = $this->createPrice($retail, $sku, $uom, 700)->assertJsonPath('data.approval_status', 'approved');
        $specialPrice = $this->createPrice($special, $sku, $uom, 600)->assertJsonPath('data.approval_status', 'pending');
        $this->actingFor()->postJson('/api/v1/master-data/pricing-controls/assignments', ['price_book_public_id' => $special->public_id, 'target_type' => 'customer', 'target_key' => $customer, 'priority' => 0, 'effective_from' => '2026-08-01', 'effective_to' => null, 'status' => 'active'])->assertCreated();

        $query = http_build_query(['customer' => $customer, 'sku' => $sku->public_id, 'uom' => $uom->public_id, 'quantity' => 1, 'date' => '2026-08-12']);
        $this->actingFor()->getJson("/api/v1/master-data/pricing-controls/resolve?{$query}")->assertOk()->assertJsonPath('data.id', $retailPrice->json('data.id'))->assertJsonPath('data.unit_price_minor', 700);
        $this->actingFor()->patchJson('/api/v1/master-data/prices/'.$specialPrice->json('data.id').'/approve', ['version' => 1, 'reason' => 'Commercial approval'])->assertOk()->assertJsonPath('data.approval_status', 'approved');
        $this->actingFor()->getJson("/api/v1/master-data/pricing-controls/resolve?{$query}")->assertOk()->assertJsonPath('data.id', $specialPrice->json('data.id'))->assertJsonPath('data.unit_price_minor', 600);
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.price.approved', 'entity_public_id' => $specialPrice->json('data.id')]);
    }

    public function test_price_assignments_are_effective_dated_locked_and_archivable(): void
    {
        $organization = $this->organization();
        $customer = $this->createCustomer();
        $book = PriceBook::query()->where('organization_id', $organization->id)->where('code', 'WHOLESALE-DEFAULT')->firstOrFail();
        $payload = ['price_book_public_id' => $book->public_id, 'target_type' => 'customer', 'target_key' => $customer, 'priority' => 10, 'effective_from' => '2026-08-01', 'effective_to' => null, 'status' => 'active'];
        $created = $this->actingFor()->postJson('/api/v1/master-data/pricing-controls/assignments', $payload)->assertCreated();
        $this->actingFor()->postJson('/api/v1/master-data/pricing-controls/assignments', $payload)->assertConflict()->assertJsonPath('code', 'price_assignment_overlap');
        $this->actingFor()->putJson('/api/v1/master-data/pricing-controls/assignments/'.$created->json('data.id'), [...$payload, 'priority' => 5, 'version' => 1])->assertOk()->assertJsonPath('data.version', 2);
        $this->actingFor()->patchJson('/api/v1/master-data/pricing-controls/assignments/'.$created->json('data.id').'/archive', ['version' => 1, 'reason' => 'Stale request'])->assertConflict()->assertJsonPath('code', 'stale_version');
        $this->actingFor()->patchJson('/api/v1/master-data/pricing-controls/assignments/'.$created->json('data.id').'/archive', ['version' => 2, 'reason' => 'Assignment ended'])->assertOk()->assertJsonPath('data.status', 'archived');
    }

    public function test_weighted_average_cost_history_requires_approval_and_rejects_overlaps(): void
    {
        $organization = $this->organization();
        $warehouse = Warehouse::query()->where('organization_id', $organization->id)->where('code', 'TGI-WH')->firstOrFail();
        $sku = Sku::query()->where('organization_id', $organization->id)->where('code', 'VAL-500')->firstOrFail();
        $payload = ['warehouse_public_id' => $warehouse->public_id, 'sku_public_id' => $sku->public_id, 'unit_cost_minor' => 400, 'currency' => 'MMK', 'valuation_method' => 'weighted_average', 'effective_from' => '2026-08-01', 'effective_to' => null, 'reason' => 'Approved opening standard'];
        $cost = $this->actingFor()->postJson('/api/v1/master-data/pricing-controls/costs', $payload)->assertCreated()->assertJsonPath('data.approval_status', 'pending')->assertJsonPath('data.valuation_method', 'weighted_average');
        $this->actingFor()->postJson('/api/v1/master-data/pricing-controls/costs', [...$payload, 'unit_cost_minor' => 410])->assertConflict()->assertJsonPath('code', 'cost_date_overlap');
        $this->actingFor()->patchJson('/api/v1/master-data/pricing-controls/costs/'.$cost->json('data.id').'/approve', ['version' => 1, 'reason' => 'Finance approved'])->assertOk()->assertJsonPath('data.approval_status', 'approved')->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.product_cost.approved', 'entity_public_id' => $cost->json('data.id')]);
    }

    private function createCustomer(): string
    {
        $branch = Branch::query()->where('organization_id', $this->organization()->id)->firstOrFail();

        return $this->actingFor()->postJson('/api/v1/master-data/foundation-masters/customers', ['code' => 'SHOP-001', 'name_en' => 'Shop 001', 'name_my' => null, 'classification' => 'retail', 'branch_public_id' => $branch->public_id, 'area_public_id' => null, 'way_public_id' => null, 'price_book_public_id' => null, 'parent_public_id' => null, 'phone' => null, 'email' => null, 'address' => null, 'registration_number' => null, 'metadata' => ['credit_mode' => 'cod'], 'sort_order' => 0, 'status' => 'active'])->assertCreated()->json('data.id');
    }

    private function createPrice(PriceBook $book, Sku $sku, UnitOfMeasure $uom, int $amount)
    {
        return $this->actingFor()->postJson('/api/v1/master-data/prices', ['price_book_public_id' => $book->public_id, 'sku_public_id' => $sku->public_id, 'uom_public_id' => $uom->public_id, 'unit_price_minor' => $amount, 'minimum_quantity' => 1, 'effective_from' => '2026-08-01', 'effective_to' => null, 'status' => 'active'])->assertCreated();
    }

    private function organization(): Organization
    {
        return Organization::query()->where('code', 'VALLEY')->firstOrFail();
    }

    private function actingFor(): static
    {
        return $this->withHeader('X-Organization-ID', $this->organization()->public_id);
    }
}
