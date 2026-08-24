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
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

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
            ->assertJsonPath('data.summary.stock_value', 152500);

        $this->getJson("/api/stock/movements?search={$movementCode}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reference_code', 'RCV-TEST');
    }

    public function test_stock_issue_rejects_insufficient_balance()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

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
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

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
            ->assertJsonPath('data.items.0.quantity', 225)
            ->assertJsonPath('data.items.0.stock_value', 405000);
    }

    public function test_office_can_transfer_stock_and_view_stock_card()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

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

    public function test_office_can_view_stock_value_report()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $this->getJson('/api/stock/value')
            ->assertOk()
            ->assertJsonPath('data.summary.balance_lines', 3)
            ->assertJsonPath('data.summary.products_count', 2)
            ->assertJsonPath('data.summary.warehouses_count', 2)
            ->assertJsonPath('data.summary.total_quantity', 440)
            ->assertJsonPath('data.summary.stock_value', 1070000)
            ->assertJsonPath('data.warehouses.0.code', 'WH-TGI')
            ->assertJsonPath('data.warehouses.0.stock_value', 848000)
            ->assertJsonPath('data.items.0.product_sku', 'VAL-5G')
            ->assertJsonPath('data.items.0.stock_value', 432000);

        $namSanWarehouseId = DB::table('warehouses')->where('code', 'WH-NSN')->value('id');

        $this->getJson("/api/stock/value?warehouse_id={$namSanWarehouseId}")
            ->assertOk()
            ->assertJsonPath('data.summary.balance_lines', 1)
            ->assertJsonPath('data.summary.stock_value', 222000)
            ->assertJsonCount(1, 'data.warehouses');
    }

    public function test_office_can_record_closing_stock_from_a_physical_count()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $warehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');

        $created = $this->postJson('/api/stock/closing-counts', [
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_date' => '2026-08-31',
            'counted_quantity' => 225,
            'reference_code' => 'COUNT-AUG-TGI',
            'notes' => 'August physical count',
        ])->assertCreated()
            ->assertJsonPath('data.movement.movement_type', 'adjustment')
            ->assertJsonPath('data.movement.signed_quantity', -15)
            ->assertJsonPath('data.movement.balance_before', 240)
            ->assertJsonPath('data.movement.balance_after', 225)
            ->assertJsonPath('data.movement.unit_cost', 1800)
            ->assertJsonPath('data.balance.quantity', 225)
            ->assertJsonPath('data.balance.stock_value', 405000);

        $this->assertStringStartsWith('ADJ-202608-', $created->json('data.movement.code'));
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => 'adjustment',
            'reference_type' => 'closing_count',
            'reference_code' => 'COUNT-AUG-TGI',
            'signed_quantity' => -15,
            'balance_before' => 240,
            'balance_after' => 225,
        ]);

        $this->getJson('/api/stock/movements?type=adjustment&reference_type=closing_count')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.balance_before', 240)
            ->assertJsonPath('data.items.0.balance_after', 225);
    }

    public function test_stock_api_requires_inventory_permission()
    {
        $this->seed();

        $this->getJson('/api/stock/meta')->assertUnauthorized();
        $this->getJson('/api/stock/movements')->assertUnauthorized();
        $this->getJson('/api/stock/balances')->assertUnauthorized();
        $this->getJson('/api/stock/value')->assertUnauthorized();
        $this->getJson('/api/stock/card')->assertUnauthorized();
        $this->postJson('/api/stock/movements', [])->assertUnauthorized();
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
