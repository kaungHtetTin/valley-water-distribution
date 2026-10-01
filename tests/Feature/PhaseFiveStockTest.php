<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseFiveStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_receive_stock_and_see_balance()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $warehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-500')->value('id');

        $this->getJson('/api/stock/meta')
            ->assertOk()
            ->assertJsonPath('data.warehouses.0.code', 'WH-NSN')
            ->assertJsonFragment(['sku' => 'VAL-500']);

        $created = $this->postJson('/api/stock/movements', [
            'movement_type' => 'receive',
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_date' => '2026-08-20',
            'quantity' => 25,
            'unit_cost' => 6100,
            'reference_code' => 'RCV-TEST',
            'notes' => 'Feature test receive',
        ])->assertCreated()
            ->assertJsonPath('data.movement.movement_type', 'receive')
            ->assertJsonPath('data.movement.signed_quantity', 25)
            ->assertJsonPath('data.balance.quantity', 25)
            ->assertJsonPath('data.balance.stock_value', 152500);

        $movementCode = $created->json('data.movement.code');
        $this->assertStringStartsWith('RCV-202608-', $movementCode);

        $this->assertDatabaseHas('stock_movements', [
            'code' => $movementCode,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'signed_quantity' => 25,
            'total_cost' => 152500,
        ]);

        $this->getJson("/api/stock/balances?warehouse_id={$warehouseId}&product_id={$productId}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.quantity', 25)
            ->assertJsonPath('data.summary.total_quantity', 25)
            ->assertJsonCount(1, 'data.warehouses');

        $this->getJson("/api/stock/movements?search={$movementCode}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reference_code', 'RCV-TEST');
    }

    public function test_office_can_receive_multiple_products_as_one_receipt()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $warehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $supplierId = DB::table('suppliers')->where('code', 'SUP-001')->value('id');
        $fiveGallonId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $smallBottleId = DB::table('products')->where('sku', 'VAL-500')->value('id');

        $created = $this->postJson('/api/stock/receipts', [
            'movement_type' => 'receive',
            'supplier_id' => $supplierId,
            'settlement_method' => 'credit',
            'warehouse_id' => $warehouseId,
            'movement_date' => '2026-08-25',
            'reference_code' => 'SUP-INV-1001',
            'notes' => 'Two-product delivery',
            'items' => [
                ['product_id' => $fiveGallonId, 'quantity' => 10, 'unit_cost' => 1900],
                ['product_id' => $smallBottleId, 'quantity' => 20, 'unit_cost' => 6000],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.receipt.products_count', 2)
            ->assertJsonPath('data.receipt.total_quantity', 30)
            ->assertJsonPath('data.receipt.total_value', 139000)
            ->assertJsonCount(2, 'data.receipt.items');

        $documentCode = $created->json('data.receipt.document_code');
        $this->assertStringStartsWith('REC-202608-', $documentCode);
        $this->assertDatabaseCount('stock_movements', 5);
        $this->assertSame(2, DB::table('stock_movements')->where('document_code', $documentCode)->count());

        $this->getJson("/api/stock/receipts?search={$documentCode}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.document_code', $documentCode)
            ->assertJsonPath('data.items.0.products_count', 2)
            ->assertJsonPath('data.items.0.total_quantity', 30)
            ->assertJsonPath('data.items.0.total_value', 139000);

        $this->assertEquals(250, DB::table('stock_balances')->where('warehouse_id', $warehouseId)->where('product_id', $fiveGallonId)->value('quantity'));
        $this->assertEquals(20, DB::table('stock_balances')->where('warehouse_id', $warehouseId)->where('product_id', $smallBottleId)->value('quantity'));
    }

    public function test_stock_balance_is_grouped_by_permitted_active_warehouse()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $mainWarehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $namSanWarehouseId = DB::table('warehouses')->where('code', 'WH-NSN')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');

        $response = $this->getJson('/api/stock/balances')
            ->assertOk()
            ->assertJsonPath('data.summary.warehouses_count', 2)
            ->assertJsonPath('data.summary.products_count', 3)
            ->assertJsonPath('data.summary.total_quantity', 440)
            ->assertJsonCount(2, 'data.warehouses');

        $product = collect($response->json('data.items'))->firstWhere('product_id', $productId);
        $this->assertSame(240, (int) $product['quantities'][(string) $mainWarehouseId]);
        $this->assertSame(120, (int) $product['quantities'][(string) $namSanWarehouseId]);
        $this->assertSame(360, (int) $product['quantity']);
        $this->assertArrayNotHasKey('stock_value', $product);
        $this->assertArrayNotHasKey('average_cost', $product);

        $inactiveWarehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'WH-HIDDEN',
            'name' => 'Inactive warehouse',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_balances')->insert([
            'warehouse_id' => $inactiveWarehouseId,
            'product_id' => $productId,
            'quantity' => 999,
            'average_cost' => 1,
            'stock_value' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/stock/balances')
            ->assertOk()
            ->assertJsonMissing(['code' => 'WH-HIDDEN'])
            ->assertJsonPath('data.summary.total_quantity', 440);

        $this->getJson("/api/stock/balances?warehouse_id={$inactiveWarehouseId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.warehouses')
            ->assertJsonPath('data.summary.total_quantity', 0);
    }

    public function test_stock_issue_rejects_insufficient_balance()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $this->postJson('/api/stock/movements', [
            'movement_type' => 'issue',
            'warehouse_id' => DB::table('warehouses')->where('code', 'WH-TGI')->value('id'),
            'product_id' => DB::table('products')->where('sku', 'VAL-500')->value('id'),
            'movement_date' => '2026-08-20',
            'quantity' => 9999,
            'unit_cost' => 6100,
        ])->assertConflict()
            ->assertJsonPath('message', 'Insufficient stock balance.');

        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => DB::table('products')->where('sku', 'VAL-500')->value('id'),
            'movement_type' => 'issue',
        ]);
    }

    public function test_office_can_issue_and_record_damage_stock()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $warehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');

        $issue = $this->postJson('/api/stock/movements', [
            'movement_type' => 'issue',
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_date' => '2026-08-22',
            'quantity' => 10,
            'reference_code' => 'ISS-TEST',
            'notes' => 'Feature test issue',
        ])->assertCreated()
            ->assertJsonPath('data.movement.movement_type', 'issue')
            ->assertJsonPath('data.movement.signed_quantity', -10)
            ->assertJsonPath('data.movement.unit_cost', 1800)
            ->assertJsonPath('data.balance.quantity', 230);

        $this->assertStringStartsWith('ISS-202608-', $issue->json('data.movement.code'));

        $damage = $this->postJson('/api/stock/movements', [
            'movement_type' => 'damage',
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_date' => '2026-08-22',
            'quantity' => 5,
            'reference_code' => 'DMG-STOCK-TEST',
            'notes' => 'Feature test damaged stock',
        ])->assertCreated()
            ->assertJsonPath('data.movement.movement_type', 'damage')
            ->assertJsonPath('data.movement.signed_quantity', -5)
            ->assertJsonPath('data.balance.quantity', 225);

        $this->assertStringStartsWith('DMG-202608-', $damage->json('data.movement.code'));

        $this->getJson("/api/stock/movements?warehouse_id={$warehouseId}&product_id={$productId}&type=damage&search=DMG-STOCK-TEST")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.summary.out_quantity', 5);

        $this->getJson("/api/stock/balances?warehouse_id={$warehouseId}&product_id={$productId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 225);
    }

    public function test_office_can_transfer_stock_and_view_stock_card()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $sourceWarehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $destinationWarehouseId = DB::table('warehouses')->where('code', 'WH-NSN')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');

        $created = $this->postJson('/api/stock/transfers', [
            'from_warehouse_id' => $sourceWarehouseId,
            'to_warehouse_id' => $destinationWarehouseId,
            'product_id' => $productId,
            'movement_date' => '2026-08-21',
            'quantity' => 40,
            'notes' => 'Feature test transfer',
        ])->assertCreated()
            ->assertJsonPath('data.out_movement.movement_type', 'transfer_out')
            ->assertJsonPath('data.out_movement.signed_quantity', -40)
            ->assertJsonPath('data.in_movement.movement_type', 'transfer_in')
            ->assertJsonPath('data.in_movement.signed_quantity', 40)
            ->assertJsonPath('data.source_balance.quantity', 200)
            ->assertJsonPath('data.destination_balance.quantity', 160);

        $transferCode = $created->json('data.transfer_code');
        $this->assertStringStartsWith('TRF-202608-', $transferCode);

        $this->assertDatabaseHas('stock_movements', [
            'code' => "{$transferCode}-OUT",
            'warehouse_id' => $sourceWarehouseId,
            'product_id' => $productId,
            'movement_type' => 'transfer_out',
            'reference_code' => $transferCode,
            'signed_quantity' => -40,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'code' => "{$transferCode}-IN",
            'warehouse_id' => $destinationWarehouseId,
            'product_id' => $productId,
            'movement_type' => 'transfer_in',
            'reference_code' => $transferCode,
            'signed_quantity' => 40,
        ]);

        $this->getJson("/api/stock/card?warehouse_id={$sourceWarehouseId}&product_id={$productId}&date_from=2026-08-01&date_to=2026-08-31")
            ->assertOk()
            ->assertJsonPath('data.summary.opening_balance', 0)
            ->assertJsonPath('data.summary.in_quantity', 240)
            ->assertJsonPath('data.summary.out_quantity', 40)
            ->assertJsonPath('data.summary.closing_balance', 200)
            ->assertJsonPath('data.items.0.running_balance', 240)
            ->assertJsonPath('data.items.1.running_balance', 200);
    }

    public function test_office_can_transfer_multiple_products_as_one_document()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $sourceId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $destinationId = DB::table('warehouses')->where('code', 'WH-NSN')->value('id');
        $fiveGallonId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $smallBottleId = DB::table('products')->where('sku', 'VAL-1L')->value('id');

        $created = $this->postJson('/api/stock/transfers', [
            'from_warehouse_id' => $sourceId,
            'to_warehouse_id' => $destinationId,
            'movement_date' => '2026-09-11',
            'reference_code' => 'MULTI-TRF-TEST',
            'items' => [
                ['product_id' => $fiveGallonId, 'quantity' => 10],
                ['product_id' => $smallBottleId, 'quantity' => 5],
            ],
        ])->assertCreated()->assertJsonPath('data.products_count', 2);

        $transferCode = $created->json('data.transfer_code');
        $this->assertSame(4, DB::table('stock_movements')->where('document_code', $transferCode)->count());

        $this->getJson("/api/stock/transfers?search={$transferCode}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.document_code', $transferCode)
            ->assertJsonPath('data.items.0.products_count', 2)
            ->assertJsonPath('data.items.0.total_quantity', 15)
            ->assertJsonPath('data.items.0.from_warehouse_code', 'WH-TGI')
            ->assertJsonPath('data.items.0.to_warehouse_code', 'WH-NSN');

        $this->getJson("/api/stock/transfers?from_warehouse_id={$sourceId}&to_warehouse_id={$destinationId}&product_id={$smallBottleId}&date=2026-09-11")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.document_code', $transferCode);

        $this->getJson("/api/stock/transfers?from_warehouse_id={$destinationId}&to_warehouse_id={$sourceId}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->getJson('/api/stock/transfers?search=Nam%20San')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_office_can_view_stock_value_report()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $response = $this->getJson('/api/stock/value')
            ->assertOk()
            ->assertJsonPath('data.summary.balance_lines', 3)
            ->assertJsonPath('data.summary.products_count', 3)
            ->assertJsonPath('data.summary.warehouses_count', 2)
            ->assertJsonPath('data.summary.total_quantity', 440)
            ->assertJsonPath('data.summary.stock_value', 1070000);

        $data = $response->json('data');
        $taunggyi = collect($data['warehouses'])->firstWhere('code', 'WH-TGI');
        $namSan = collect($data['warehouses'])->firstWhere('code', 'WH-NSN');
        $fiveGallon = collect($data['items'])->firstWhere('product_sku', 'VAL-5G');

        $this->assertSame(848000, $taunggyi['stock_value']);
        $this->assertSame(222000, $namSan['stock_value']);
        $this->assertSame(432000, $fiveGallon['values'][(string) $taunggyi['id']]);
        $this->assertSame(222000, $fiveGallon['values'][(string) $namSan['id']]);
        $this->assertSame(654000, $fiveGallon['stock_value']);
        $this->assertArrayNotHasKey('average_cost', $fiveGallon);

        $namSanWarehouseId = DB::table('warehouses')->where('code', 'WH-NSN')->value('id');

        $this->getJson("/api/stock/value?warehouse_id={$namSanWarehouseId}")
            ->assertOk()
            ->assertJsonPath('data.summary.balance_lines', 1)
            ->assertJsonPath('data.summary.stock_value', 222000)
            ->assertJsonCount(1, 'data.warehouses');

        $inactiveWarehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'WH-HIDDEN',
            'name' => 'Hidden Warehouse',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_balances')->insert([
            'warehouse_id' => $inactiveWarehouseId,
            'product_id' => $fiveGallon['product_id'],
            'quantity' => 999,
            'average_cost' => 999,
            'stock_value' => 998001,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/stock/value')
            ->assertOk()
            ->assertJsonMissing(['code' => 'WH-HIDDEN'])
            ->assertJsonPath('data.summary.stock_value', 1070000);

        $this->getJson("/api/stock/value?warehouse_id={$inactiveWarehouseId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.warehouses')
            ->assertJsonPath('data.summary.stock_value', 0);
    }

    public function test_office_can_record_closing_stock_from_a_physical_count()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $warehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $activeProducts = DB::table('products')->where('is_active', true)->count();

        $this->getJson("/api/stock/closing-counts/preview?warehouse_id={$warehouseId}")
            ->assertOk()
            ->assertJsonCount($activeProducts, 'data.items')
            ->assertJsonPath('data.warehouse.code', 'WH-TGI');

        $items = DB::table('products')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id'])
            ->map(function ($product) use ($warehouseId, $productId) {
                $balance = DB::table('stock_balances')
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $product->id)
                    ->first();

                return [
                    'product_id' => $product->id,
                    'system_quantity' => (float) ($balance->quantity ?? 0),
                    'counted_quantity' => $product->id === $productId ? 225 : (float) ($balance->quantity ?? 0),
                    'unit_cost' => (float) ($balance->average_cost ?? 0),
                ];
            })->all();

        $created = $this->postJson('/api/stock/closing-counts', [
            'warehouse_id' => $warehouseId,
            'movement_date' => '2026-08-31',
            'reference_code' => 'COUNT-AUG-TGI',
            'notes' => 'August physical count',
            'items' => $items,
        ])->assertCreated()
            ->assertJsonPath('data.count.products_count', count($items))
            ->assertJsonPath('data.count.quantity_removed', 15)
            ->assertJsonPath('data.count.quantity_added', 0)
            ->assertJsonPath('data.count.variance_value', -27000);

        $this->assertStringStartsWith('CNT-202608-', $created->json('data.count.code'));
        $countId = $created->json('data.count.id');
        $this->assertDatabaseCount('stock_count_items', count($items));
        $this->assertDatabaseHas('stock_count_items', [
            'stock_count_id' => $countId,
            'product_id' => $productId,
            'system_quantity' => 240,
            'counted_quantity' => 225,
            'variance_quantity' => -15,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => 'adjustment',
            'reference_type' => 'closing_count',
            'reference_id' => $countId,
            'reference_code' => 'COUNT-AUG-TGI',
            'signed_quantity' => -15,
            'balance_before' => 240,
            'balance_after' => 225,
        ]);

        $this->getJson('/api/stock/closing-counts')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.products_count', count($items))
            ->assertJsonPath('data.items.0.quantity_removed', 15);

        $detail = $this->getJson("/api/stock/closing-counts/{$countId}")
            ->assertOk()
            ->assertJsonPath('data.count.id', $countId)
            ->assertJsonPath('data.count.warehouse_code', 'WH-TGI')
            ->assertJsonPath('data.count.products_count', count($items))
            ->assertJsonCount(count($items), 'data.items');

        $changedLine = collect($detail->json('data.items'))->firstWhere('product_id', $productId);
        $this->assertSame(-15, (int) $changedLine['variance_quantity']);
        $this->assertNotNull($changedLine['movement_code']);
        $this->assertCount(count($items) - 1, collect($detail->json('data.items'))->whereNull('movement_code'));
    }

    public function test_office_can_record_and_filter_reasoned_stock_adjustments()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $warehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');

        $this->postJson('/api/stock/movements', [
            'movement_type' => 'adjustment',
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_date' => '2026-09-11',
            'quantity' => -4,
            'adjustment_reason' => 'expired',
            'reference_code' => 'EXP-TEST',
            'notes' => 'Expired bottles removed during inspection.',
        ])->assertCreated()
            ->assertJsonPath('data.movement.movement_type', 'adjustment')
            ->assertJsonPath('data.movement.adjustment_reason', 'expired')
            ->assertJsonPath('data.movement.reference_type', 'stock_adjustment')
            ->assertJsonPath('data.movement.signed_quantity', -4)
            ->assertJsonPath('data.balance.quantity', 236);

        $this->postJson('/api/stock/movements', [
            'movement_type' => 'adjustment',
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_date' => '2026-09-11',
            'quantity' => 2,
            'adjustment_reason' => 'found_stock',
            'reference_code' => 'FOUND-TEST',
        ])->assertCreated()
            ->assertJsonPath('data.movement.adjustment_reason', 'found_stock')
            ->assertJsonPath('data.movement.signed_quantity', 2)
            ->assertJsonPath('data.balance.quantity', 238);

        $this->getJson("/api/stock/movements?type_group=adjustment&adjustment_reason=expired&warehouse_id={$warehouseId}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reference_code', 'EXP-TEST')
            ->assertJsonPath('data.summary.out_quantity', 4);

        $this->postJson('/api/stock/movements', [
            'movement_type' => 'adjustment',
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'quantity' => -1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('adjustment_reason');
    }

    public function test_stock_api_requires_inventory_permission()
    {
        $this->seed();

        $this->getJson('/api/stock/meta')->assertUnauthorized();
        $this->getJson('/api/stock/movements')->assertUnauthorized();
        $this->getJson('/api/stock/receipts')->assertUnauthorized();
        $this->getJson('/api/stock/closing-counts')->assertUnauthorized();
        $this->getJson('/api/stock/closing-counts/preview')->assertUnauthorized();
        $this->getJson('/api/stock/closing-counts/1')->assertUnauthorized();
        $this->getJson('/api/stock/balances')->assertUnauthorized();
        $this->getJson('/api/stock/value')->assertUnauthorized();
        $this->getJson('/api/stock/card')->assertUnauthorized();
        $this->postJson('/api/stock/movements', [])->assertUnauthorized();
        $this->postJson('/api/stock/receipts', [])->assertUnauthorized();
        $this->postJson('/api/stock/transfers', [])->assertUnauthorized();
        $this->postJson('/api/stock/closing-counts', [])->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail())
            ->getJson('/api/stock/movements')
            ->assertForbidden();

        $this->postJson('/api/stock/movements', [])->assertForbidden();
        $this->postJson('/api/stock/transfers', [])->assertForbidden();
        $this->postJson('/api/stock/closing-counts', [])->assertForbidden();
    }
}
