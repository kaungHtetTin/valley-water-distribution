<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseFourAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_create_sales_return_and_damage_entries()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $customerId = DB::table('customers')->where('code', 'CUS-0003')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $originalOrderId = DB::table('orders')->where('code', 'ORD-202608-0005')->value('id');
        $originalItemId = DB::table('order_items')->where('order_id', $originalOrderId)->where('product_id', $productId)->value('id');
        $warehouseId = DB::table('deliveries')->where('order_id', $originalOrderId)->value('warehouse_id');
        $stockBefore = (float) DB::table('stock_balances')->where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity');

        $this->getJson('/api/order-adjustments/meta')
            ->assertOk()
            ->assertJsonFragment(['code' => 'ORD-202608-0005', 'returnable_quantity' => 9]);

        $return = $this->postJson('/api/order-adjustments', [
            'type' => 'sales_return',
            'original_order_id' => $originalOrderId,
            'return_warehouse_id' => $warehouseId,
            'return_settlement_method' => 'customer_credit',
            'entry_date' => '2026-08-25',
            'notes' => 'Feature test returned goods',
            'items' => [
                [
                    'original_order_item_id' => $originalItemId,
                    'good_quantity' => 2,
                    'damaged_quantity' => 1,
                    'remarks' => 'Returned from shop',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.record.status', 'confirmed')
            ->assertJsonPath('data.record.total', 9000)
            ->assertJsonPath('data.items.0.item_type', 'sales_return')
            ->assertJsonPath('data.items.0.return_condition', 'good')
            ->assertJsonPath('data.items.0.unit_price', 3000);

        $returnId = $return->json('data.record.id');
        $returnCode = $return->json('data.record.code');

        $this->assertStringStartsWith('RET-202608-', $returnCode);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $returnId,
            'product_id' => $productId,
            'item_type' => 'sales_return',
            'return_condition' => 'good',
            'quantity' => 2,
            'line_total' => 6000,
        ]);
        $this->assertDatabaseHas('order_items', ['order_id' => $returnId, 'return_condition' => 'damaged', 'quantity' => 1, 'line_total' => 3000]);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'sales_return', 'reference_id' => $returnId, 'movement_type' => 'sales_return', 'signed_quantity' => 2]);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'sales_return', 'reference_id' => $returnId, 'movement_type' => 'sales_return_damage', 'signed_quantity' => 0]);
        $this->assertEquals($stockBefore + 2, (float) DB::table('stock_balances')->where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity'));

        $this->getJson("/api/order-adjustments?type=sales_return&search={$returnCode}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.code', $returnCode)
            ->assertJsonPath('data.summary.total_quantity', 3)
            ->assertJsonPath('data.summary.total_amount', 9000);

        $this->getJson("/api/order-adjustments/{$returnId}?type=sales_return")
            ->assertOk()
            ->assertJsonPath('data.record.code', $returnCode)
            ->assertJsonPath('data.record.original_order_code', 'ORD-202608-0005')
            ->assertJsonCount(2, 'data.items');

        $this->getJson("/api/finance/customers/{$customerId}/ledger")
            ->assertOk()
            ->assertJsonPath('data.summary.return_credits_amount', 9000)
            ->assertJsonPath('data.summary.outstanding_amount', 18000)
            ->assertJsonFragment(['type' => 'sales_return', 'reference' => $returnCode, 'credit' => 9000]);

        $this->postJson('/api/order-adjustments', [
            'type' => 'sales_return',
            'original_order_id' => $originalOrderId,
            'return_warehouse_id' => $warehouseId,
            'return_settlement_method' => 'customer_credit',
            'items' => [['original_order_item_id' => $originalItemId, 'good_quantity' => 7, 'damaged_quantity' => 0]],
        ])->assertUnprocessable();

        $damage = $this->postJson('/api/order-adjustments', [
            'type' => 'damage',
            'customer_id' => $customerId,
            'entry_date' => '2026-08-25',
            'payment_type' => 'credit',
            'notes' => 'Feature test damaged goods',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 2,
                    'remarks' => 'Broken on return',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.record.total', 6000)
            ->assertJsonPath('data.items.0.item_type', 'damage');

        $damageCode = $damage->json('data.record.code');
        $this->assertStringStartsWith('DMG-202608-', $damageCode);

        $this->getJson("/api/order-adjustments?type=damage&search={$damageCode}")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.summary.total_quantity', 2)
            ->assertJsonPath('data.summary.total_amount', 6000);
    }

    public function test_adjustment_api_requires_office_order_permission()
    {
        $this->seed();

        $this->getJson('/api/order-adjustments')->assertUnauthorized();
        $this->getJson('/api/order-adjustments/meta')->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail())
            ->getJson('/api/order-adjustments')
            ->assertForbidden();

        $this->postJson('/api/order-adjustments', [])->assertForbidden();
    }

    public function test_cash_sale_return_posts_a_refund_transaction()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $originalOrderId = DB::table('orders')->where('code', 'ORD-202608-0005')->value('id');
        $originalItemId = DB::table('order_items')->where('order_id', $originalOrderId)->value('id');
        $warehouseId = DB::table('warehouses')->where('is_active', true)->value('id');
        DB::table('orders')->where('id', $originalOrderId)->update(['payment_type' => 'cash']);

        $response = $this->postJson('/api/order-adjustments', [
            'type' => 'sales_return',
            'original_order_id' => $originalOrderId,
            'return_warehouse_id' => $warehouseId,
            'return_settlement_method' => 'bank_refund',
            'entry_date' => '2026-08-25',
            'items' => [['original_order_item_id' => $originalItemId, 'good_quantity' => 1, 'damaged_quantity' => 0]],
        ])->assertCreated()
            ->assertJsonPath('data.record.refund_amount', 3000)
            ->assertJsonPath('data.record.return_settlement_method', 'bank_refund');

        $this->assertDatabaseHas('financial_transactions', [
            'reference_type' => 'sales_return',
            'reference_id' => $response->json('data.record.id'),
            'book_type' => 'bank',
            'direction' => 'out',
            'category' => 'sales_return_refund',
            'amount' => 3000,
        ]);
    }
}
