<?php

namespace Tests\Feature\MasterData;

use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\PriceBook;
use App\Models\Product;
use App\Models\Sku;
use App\Models\UnitOfMeasure;
use Database\Seeders\PhaseOneReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('platform.features.master_data', true);
        $this->seed(PhaseOneReferenceSeeder::class);
    }

    public function test_reference_catalog_seeds_dynamic_sizes_without_inventing_package_factors_or_prices(): void
    {
        $organization = Organization::query()->where('code', 'VALLEY')->firstOrFail();

        $response = $this->withHeader('X-Organization-ID', $organization->public_id)
            ->getJson('/api/v1/master-data/skus?per_page=20');

        $response->assertOk()->assertJsonCount(3, 'data');
        $this->assertEqualsCanonicalizing(['VAL-500', 'VAL-700', 'VAL-1000'], collect($response->json('data'))->pluck('code')->all());
        $this->assertDatabaseCount('sku_uom_conversions', 3);
        $this->assertDatabaseMissing('sku_uom_conversions', ['factor_to_base' => 6]);
        $this->assertDatabaseMissing('sku_uom_conversions', ['factor_to_base' => 12]);
        $this->assertDatabaseCount('price_types', 3);
        $this->assertDatabaseCount('price_book_items', 0);
    }

    public function test_sku_creation_is_scoped_normalized_and_audited(): void
    {
        $organization = Organization::query()->where('code', 'VALLEY')->firstOrFail();
        $product = Product::query()->where('organization_id', $organization->id)->firstOrFail();
        $unit = UnitOfMeasure::query()->where('organization_id', $organization->id)->where('code', 'BTL')->firstOrFail();

        $response = $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson('/api/v1/master-data/skus', [
                'product_public_id' => $product->public_id,
                'base_uom_public_id' => $unit->public_id,
                'code' => ' val-1500 ',
                'name_en' => 'Valley Water 1.5 L',
                'name_my' => 'Valley သောက်ရေ ၁.၅ လီတာ',
                'size_label' => '1.5 L',
                'volume_ml' => 1500,
                'minimum_order_quantity' => 1,
                'order_step_quantity' => 1,
                'minimum_delivery_quantity' => 1,
                'sale_status' => 'saleable',
                'status' => 'active',
            ]);

        $response->assertCreated()->assertJsonPath('data.code', 'VAL-1500')->assertJsonPath('data.conversions.0.factor_to_base', '1.000000');
        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.sku.created', 'entity_public_id' => $response->json('data.id')]);
    }

    public function test_effective_prices_reject_overlaps_and_special_prices_require_approval(): void
    {
        $organization = Organization::query()->where('code', 'VALLEY')->firstOrFail();
        $sku = Sku::query()->where('organization_id', $organization->id)->firstOrFail();
        $unit = UnitOfMeasure::query()->where('organization_id', $organization->id)->where('code', 'BTL')->firstOrFail();
        $book = PriceBook::query()->where('organization_id', $organization->id)->where('code', 'SPECIAL-DEFAULT')->firstOrFail();
        $payload = [
            'price_book_public_id' => $book->public_id,
            'sku_public_id' => $sku->public_id,
            'uom_public_id' => $unit->public_id,
            'unit_price_minor' => 750,
            'minimum_quantity' => 1,
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-08-31',
            'status' => 'active',
        ];

        $created = $this->withHeader('X-Organization-ID', $organization->public_id)->postJson('/api/v1/master-data/prices', $payload);
        $created->assertCreated()->assertJsonPath('data.approval_status', 'pending')->assertJsonPath('data.unit_price_minor', 750);

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson('/api/v1/master-data/prices', [...$payload, 'effective_from' => '2026-08-15', 'effective_to' => null])
            ->assertConflict()
            ->assertJsonPath('code', 'price_date_overlap');

        $this->assertDatabaseHas('audit_events', ['action' => 'master_data.price.created', 'entity_public_id' => $created->json('data.id')]);
    }

    public function test_price_unit_must_have_an_active_sku_conversion(): void
    {
        $organization = Organization::query()->where('code', 'VALLEY')->firstOrFail();
        $sku = Sku::query()->where('organization_id', $organization->id)->firstOrFail();
        $carton = UnitOfMeasure::query()->where('organization_id', $organization->id)->where('code', 'CTN')->firstOrFail();
        $book = PriceBook::query()->where('organization_id', $organization->id)->where('code', 'RETAIL-DEFAULT')->firstOrFail();

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson('/api/v1/master-data/prices', [
                'price_book_public_id' => $book->public_id,
                'sku_public_id' => $sku->public_id,
                'uom_public_id' => $carton->public_id,
                'unit_price_minor' => 9000,
                'minimum_quantity' => 1,
                'effective_from' => '2026-08-01',
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'missing_unit_conversion');
    }

    public function test_stale_sku_update_is_rejected_without_losing_the_current_value(): void
    {
        $organization = Organization::query()->where('code', 'VALLEY')->firstOrFail();
        $sku = Sku::query()->where('organization_id', $organization->id)->firstOrFail();
        $payload = [
            'version' => 1,
            'code' => $sku->code,
            'name_en' => 'Current catalog name',
            'name_my' => $sku->name_my,
            'size_label' => $sku->size_label,
            'barcode' => null,
            'volume_ml' => $sku->volume_ml,
            'weight_grams' => null,
            'shelf_life_days' => null,
            'track_lot' => false,
            'track_expiry' => false,
            'is_returnable' => false,
            'minimum_order_quantity' => 1,
            'order_step_quantity' => 1,
            'minimum_delivery_quantity' => 1,
            'sale_status' => 'saleable',
            'active_from' => null,
            'active_to' => null,
            'status' => 'active',
        ];

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->putJson("/api/v1/master-data/skus/{$sku->public_id}", $payload)
            ->assertOk()->assertJsonPath('data.version', 2);

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->putJson("/api/v1/master-data/skus/{$sku->public_id}", [...$payload, 'name_en' => 'Stale catalog name'])
            ->assertConflict()->assertJsonPath('code', 'stale_version');

        $this->assertSame('Current catalog name', $sku->refresh()->name_en);
        $this->assertSame(2, $sku->lock_version);
        $this->assertSame(1, AuditEvent::query()->where('action', 'master_data.sku.updated')->count());
    }

    public function test_unit_conversion_revisions_preserve_versions_and_advance_the_sku_lock(): void
    {
        $organization = Organization::query()->where('code', 'VALLEY')->firstOrFail();
        $sku = Sku::query()->where('organization_id', $organization->id)->firstOrFail();
        $carton = UnitOfMeasure::query()->where('organization_id', $organization->id)->where('code', 'CTN')->firstOrFail();

        $first = $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson("/api/v1/master-data/skus/{$sku->public_id}/conversions", [
                'version' => 1,
                'uom_public_id' => $carton->public_id,
                'factor_to_base' => 12,
                'effective_from' => '2026-08-01',
                'is_selling_unit' => true,
                'is_kpi_base' => false,
            ]);

        $first->assertOk()->assertJsonPath('data.version', 2);

        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson("/api/v1/master-data/skus/{$sku->public_id}/conversions", [
                'version' => 2,
                'uom_public_id' => $carton->public_id,
                'factor_to_base' => 24,
                'effective_from' => '2026-09-01',
                'is_selling_unit' => true,
                'is_kpi_base' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 3);

        $this->assertDatabaseHas('sku_uom_conversions', ['sku_id' => $sku->id, 'uom_id' => $carton->id, 'version' => 1, 'factor_to_base' => 12, 'status' => 'superseded', 'effective_to' => '2026-08-31 00:00:00']);
        $this->assertDatabaseHas('sku_uom_conversions', ['sku_id' => $sku->id, 'uom_id' => $carton->id, 'version' => 2, 'factor_to_base' => 24, 'status' => 'active']);
        $this->assertSame(2, AuditEvent::query()->where('action', 'master_data.sku.conversion_revised')->count());

        $bottle = UnitOfMeasure::query()->where('organization_id', $organization->id)->where('code', 'BTL')->firstOrFail();
        $this->withHeader('X-Organization-ID', $organization->public_id)
            ->postJson("/api/v1/master-data/skus/{$sku->public_id}/conversions", [
                'version' => 3,
                'uom_public_id' => $bottle->public_id,
                'factor_to_base' => 1,
                'effective_from' => '2026-10-01',
                'is_selling_unit' => true,
                'is_kpi_base' => false,
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'missing_kpi_base');

        $this->assertSame(3, $sku->refresh()->lock_version);
        $this->assertDatabaseHas('sku_uom_conversions', ['sku_id' => $sku->id, 'uom_id' => $bottle->id, 'version' => 1, 'status' => 'active', 'is_kpi_base' => true]);
    }
}
