<?php

namespace Tests\Feature\MasterData;

use App\Models\Brand;
use App\Models\Organization;
use App\Models\PriceBook;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSetupMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
        $this->seed(PhaseOneReferenceSeeder::class);
    }

    public function test_dashboard_exposes_scoped_setup_records_and_safe_usage_counts(): void
    {
        $this->actingFor()->getJson('/api/v1/master-data/catalog-setup')
            ->assertOk()
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonCount(1, 'data.brands')
            ->assertJsonCount(1, 'data.products')
            ->assertJsonCount(3, 'data.units')
            ->assertJsonCount(3, 'data.price_types')
            ->assertJsonCount(3, 'data.price_books')
            ->assertJsonCount(1, 'data.branches')
            ->assertJsonPath('data.products.0.skus_count', 3)
            ->assertJsonPath('data.units.0.code', 'BTL')
            ->assertJsonPath('data.units.0.usage_count', 6)
            ->assertJsonPath('data.units.1.code', 'CTN')
            ->assertJsonPath('data.units.1.usage_count', 0);
    }

    public function test_category_lifecycle_normalizes_audits_and_rejects_stale_updates(): void
    {
        $payload = ['code' => ' beverages ', 'name_en' => 'Beverages', 'name_my' => null, 'status' => 'active'];
        $created = $this->actingFor()->postJson('/api/v1/master-data/catalog-setup/categories', $payload)
            ->assertCreated()->assertJsonPath('data.code', 'BEVERAGES')->assertJsonPath('data.version', 1);
        $id = $created->json('data.id');

        $this->actingFor()->putJson("/api/v1/master-data/catalog-setup/categories/{$id}", [...$payload, 'code' => 'BEVERAGES', 'name_en' => 'Drinking Beverages', 'version' => 1])
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.name.en', 'Drinking Beverages');
        $this->actingFor()->putJson("/api/v1/master-data/catalog-setup/categories/{$id}", [...$payload, 'code' => 'BEVERAGES', 'version' => 1])
            ->assertConflict()->assertJsonPath('code', 'stale_version');

        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.product_category.created', 'entity_public_id' => $id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.product_category.updated', 'entity_public_id' => $id]);
    }

    public function test_product_references_are_scoped_and_active_dependencies_block_archives(): void
    {
        $organization = $this->organization();
        $brand = Brand::query()->where('organization_id', $organization->id)->firstOrFail();
        $category = ProductCategory::query()->where('organization_id', $organization->id)->firstOrFail();
        $product = Product::query()->where('organization_id', $organization->id)->firstOrFail();

        $this->actingFor()->postJson('/api/v1/master-data/catalog-setup/products', [
            'brand_public_id' => $brand->public_id, 'category_public_id' => $category->public_id,
            'code' => 'VALLEY-SPARKLING', 'name_en' => 'Valley Sparkling Water', 'name_my' => null,
            'description' => 'Future catalog family', 'active_from' => '2026-09-01', 'active_to' => null, 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.brand.code', 'VALLEY')->assertJsonPath('data.category.code', 'WATER');

        $this->actingFor()->patchJson("/api/v1/master-data/catalog-setup/products/{$product->public_id}/archive", ['version' => 1, 'reason' => 'Retired family'])
            ->assertConflict()->assertJsonPath('code', 'product_has_skus');
        $this->actingFor()->patchJson("/api/v1/master-data/catalog-setup/brands/{$brand->public_id}/archive", ['version' => 1, 'reason' => 'Retired brand'])
            ->assertConflict()->assertJsonPath('code', 'brand_has_products');
        $this->actingFor()->patchJson("/api/v1/master-data/catalog-setup/categories/{$category->public_id}/archive", ['version' => 1, 'reason' => 'Retired category'])
            ->assertConflict()->assertJsonPath('code', 'category_has_products');
    }

    public function test_units_cannot_archive_while_used_but_unused_units_can_be_archived(): void
    {
        $organization = $this->organization();
        $bottle = UnitOfMeasure::query()->where('organization_id', $organization->id)->where('code', 'BTL')->firstOrFail();
        $carton = UnitOfMeasure::query()->where('organization_id', $organization->id)->where('code', 'CTN')->firstOrFail();

        $this->actingFor()->patchJson("/api/v1/master-data/catalog-setup/units/{$bottle->public_id}/archive", ['version' => 1, 'reason' => 'Replace unit'])
            ->assertConflict()->assertJsonPath('code', 'unit_has_usage');
        $this->actingFor()->patchJson("/api/v1/master-data/catalog-setup/units/{$carton->public_id}/archive", ['version' => 1, 'reason' => 'Unused package unit'])
            ->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.unit_of_measure.archived', 'entity_public_id' => $carton->public_id, 'reason' => 'Unused package unit']);
    }

    public function test_price_type_and_book_lifecycle_enforces_reference_and_item_dependencies(): void
    {
        $type = $this->actingFor()->postJson('/api/v1/master-data/catalog-setup/price-types', [
            'code' => 'PROMO', 'name_en' => 'Promotion', 'name_my' => null, 'precedence' => 50, 'requires_approval' => true, 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.requires_approval', true);

        $book = $this->actingFor()->postJson('/api/v1/master-data/catalog-setup/price-books', [
            'branch_public_id' => null, 'price_type_public_id' => $type->json('data.id'), 'code' => ' promo-default ',
            'name_en' => 'Promotion Default', 'name_my' => null, 'currency' => 'mmk', 'scope_type' => 'organization_default',
            'effective_from' => '2026-08-12', 'effective_to' => null, 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.code', 'PROMO-DEFAULT')->assertJsonPath('data.currency', 'MMK')->assertJsonPath('data.price_type.code', 'PROMO');

        $this->actingFor()->patchJson("/api/v1/master-data/catalog-setup/price-types/{$type->json('data.id')}/archive", ['version' => 1, 'reason' => 'Promotion ended'])
            ->assertConflict()->assertJsonPath('code', 'price_type_has_books');
        $this->actingFor()->patchJson("/api/v1/master-data/catalog-setup/price-books/{$book->json('data.id')}/archive", ['version' => 1, 'reason' => 'Promotion ended'])
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->actingFor()->patchJson("/api/v1/master-data/catalog-setup/price-types/{$type->json('data.id')}/archive", ['version' => 1, 'reason' => 'Promotion ended'])
            ->assertOk()->assertJsonPath('data.status', 'archived');
    }

    public function test_duplicate_codes_and_invalid_date_windows_are_rejected(): void
    {
        $organization = $this->organization();
        $type = PriceType::query()->where('organization_id', $organization->id)->firstOrFail();

        $this->actingFor()->postJson('/api/v1/master-data/catalog-setup/brands', ['code' => 'VALLEY', 'name_en' => 'Duplicate', 'name_my' => null, 'status' => 'active'])
            ->assertConflict()->assertJsonPath('code', 'duplicate_catalog_setup_code');
        $this->actingFor()->postJson('/api/v1/master-data/catalog-setup/price-books', [
            'branch_public_id' => null, 'price_type_public_id' => $type->public_id, 'code' => 'TEST-BOOK', 'name_en' => 'Test',
            'name_my' => null, 'currency' => 'MMK', 'scope_type' => 'organization_default', 'effective_from' => '2026-09-01',
            'effective_to' => '2026-08-01', 'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('effective_to');

        $book = PriceBook::query()->where('organization_id', $organization->id)->where('code', 'RETAIL-DEFAULT')->firstOrFail();
        $this->assertSame(1, $book->lock_version);
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
