<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderStockAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function channels(): array
    {
        return [
            ['owner@valley.test', '/api/orders'],
            ['sales@valley.test', '/api/mobile/orders'],
            ['client@valley.test', '/api/mobile/orders'],
        ];
    }

    private function payload(int $productId, string $type = 'sale'): array
    {
        return [
            'customer_id' => DB::table('customers')->where('code', 'CUS-0001')->value('id'),
            'order_date' => '2026-10-13',
            'items' => [['product_id' => $productId, 'quantity' => 2, 'item_type' => $type]],
        ];
    }

    public function test_all_channels_reject_zero_missing_and_negative_stock_without_creating_records(): void
    {
        $this->seed();
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $orderCount = DB::table('orders')->count();
        $itemCount = DB::table('order_items')->count();
        foreach (['zero', 'negative', 'missing'] as $condition) {
            if ($condition === 'missing') {
                DB::table('stock_balances')->where('product_id', $productId)->delete();
            } else {
                DB::table('stock_balances')->where('product_id', $productId)->update(['quantity' => $condition === 'zero' ? 0 : -1]);
            }
            foreach ($this->channels() as [$email, $endpoint]) {
                $this->actingAs(User::where('email', $email)->firstOrFail())
                    ->postJson($endpoint, $this->payload($productId))->assertUnprocessable()
                    ->assertJsonValidationErrors('items.0.product_id')
                    ->assertJsonFragment(['items.0.product_id' => ['Valley 5 Gallon Water (VAL-5G) is out of stock. Remove this product or replenish stock before saving the order.']]);
            }
            $this->assertSame($orderCount, DB::table('orders')->count());
            $this->assertSame($itemCount, DB::table('order_items')->count());
        }
    }

    public function test_foc_and_draft_orders_cannot_bypass_stock_checks(): void
    {
        $this->seed();
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        DB::table('stock_balances')->where('product_id', $productId)->update(['quantity' => 0]);
        foreach ($this->channels() as [$email, $endpoint]) {
            $this->actingAs(User::where('email', $email)->firstOrFail());
            $payload = $this->payload($productId, 'foc');
            $payload['save_as'] = 'draft';
            $this->postJson($endpoint, $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
        }
    }

    public function test_stock_is_rechecked_after_loading_product_metadata(): void
    {
        $this->seed();
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        foreach ($this->channels() as [$email, $endpoint]) {
            DB::table('stock_balances')->where('product_id', $productId)->update(['quantity' => 10]);
            $this->actingAs(User::where('email', $email)->firstOrFail());
            $products = $this->getJson($endpoint.'/meta')->assertOk()->json('data.products');
            $product = collect($products)->firstWhere('id', $productId);
            $this->assertTrue($product['is_in_stock']);
            $this->assertGreaterThan(0, $product['available_stock']);
            DB::table('stock_balances')->where('product_id', $productId)->update(['quantity' => 0]);
            $this->postJson($endpoint, $this->payload($productId))->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
            $products = $this->getJson($endpoint.'/meta')->assertOk()->json('data.products');
            $product = collect($products)->firstWhere('id', $productId);
            $this->assertFalse($product['is_in_stock']);
            $this->assertEquals(0, $product['available_stock']);
        }
    }

    public function test_mixed_orders_and_order_updates_reject_unavailable_items_atomically(): void
    {
        $this->seed();
        $availableId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $emptyId = DB::table('products')->where('sku', 'VAL-1L')->value('id');
        DB::table('stock_balances')->where('product_id', $emptyId)->update(['quantity' => 0]);
        foreach ($this->channels() as [$email, $endpoint]) {
            $this->actingAs(User::where('email', $email)->firstOrFail());
            $payload = $this->payload($availableId);
            $created = $this->postJson($endpoint, $payload)->assertCreated();
            $orderId = $created->json('data.order.id');
            $payload['items'][] = ['product_id' => $emptyId, 'quantity' => 1, 'item_type' => 'foc'];
            $count = DB::table('orders')->count();
            $this->postJson($endpoint, $payload)->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');
            $this->assertSame($count, DB::table('orders')->count());
            $this->putJson($endpoint.'/'.$orderId, $payload)->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');
            $this->assertSame(1, DB::table('order_items')->where('order_id', $orderId)->count());
            $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'product_id' => $availableId]);
        }
    }

    public function test_stock_in_another_warehouse_allows_orders_in_all_channels(): void
    {
        $this->seed();
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        DB::table('stock_balances')->where('product_id', $productId)->update(['quantity' => 0]);
        $warehouseId = DB::table('warehouses')->orderByDesc('id')->value('id');
        DB::table('stock_balances')->updateOrInsert(['warehouse_id' => $warehouseId, 'product_id' => $productId], [
            'quantity' => 10, 'average_cost' => 1000, 'stock_value' => 10000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($this->channels() as [$email, $endpoint]) {
            $this->actingAs(User::where('email', $email)->firstOrFail());
            $products = $this->getJson($endpoint.'/meta')->assertOk()->json('data.products');
            $this->assertEquals(10, collect($products)->firstWhere('id', $productId)['available_stock']);
            $this->postJson($endpoint, $this->payload($productId))->assertCreated();
        }
    }
}
