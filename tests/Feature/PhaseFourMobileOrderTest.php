<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseFourMobileOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_selected_price_and_default_fallback_are_used_for_create_and_edit(): void
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $this->actingAs($sales);
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $specialId = DB::table('price_types')->where('code', 'SPC')->value('id');
        $customerId = DB::table('customers')->where('route_id', DB::table('employees')->where('id', $sales->employee_id)->value('assigned_route_id'))->value('id');
        $payload = ['customer_id' => $customerId, 'price_type_id' => $specialId, 'save_as' => 'draft',
            'items' => [['product_id' => $productId, 'quantity' => 2, 'item_type' => 'sale'],
                ['product_id' => $productId, 'quantity' => 1, 'item_type' => 'foc']]];
        $created = $this->postJson('/api/mobile/orders', $payload)->assertCreated()
            ->assertJsonPath('data.order.price_type_id', $specialId)
            ->assertJsonPath('data.items.0.unit_price', 2800)
            ->assertJsonPath('data.items.1.unit_price', 0);
        DB::table('product_prices')->where('product_id', $productId)->where('price_type_id', $specialId)->update(['amount' => 0]);
        $this->putJson('/api/mobile/orders/'.$created->json('data.order.id'), $payload)->assertOk()
            ->assertJsonPath('data.items.0.unit_price', 3000)->assertJsonPath('data.order.total', 6000);
        DB::table('product_prices')->where('product_id', $productId)->where('price_type_id', $specialId)->delete();
        $this->postJson('/api/mobile/orders', $payload)->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', 3000)->assertJsonPath('data.items.1.unit_price', 0);
        $meta = $this->getJson('/api/mobile/orders/meta')->assertOk()->json('data');
        $product = collect($meta['products'])->firstWhere('id', $productId);
        $this->assertEquals(3000, $product['default_price']);
        $this->assertEquals('WSL', collect($meta['price_types'])->firstWhere('is_default', true)['code']);
    }

    public function test_missing_default_does_not_create_free_sale_or_use_future_price(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'client@valley.test')->firstOrFail());
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $defaultId = DB::table('price_types')->where('is_default', true)->value('id');
        DB::table('product_prices')->where('product_id', $productId)->where('price_type_id', $defaultId)
            ->update(['effective_from' => now()->addYear()->toDateString()]);
        $this->postJson('/api/mobile/orders', ['price_type_id' => $defaultId,
            'items' => [['product_id' => $productId, 'quantity' => 1, 'item_type' => 'sale']]])->assertStatus(422);
        $meta = $this->getJson('/api/mobile/orders/meta')->assertOk()->json('data.products');
        $this->assertNull(collect($meta)->firstWhere('id', $productId)['default_price']);
    }

    public function test_client_can_place_and_view_own_order()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'client@valley.test')->firstOrFail());

        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');

        $meta = $this->getJson('/api/mobile/orders/meta')
            ->assertOk()
            ->assertJsonPath('data.app', 'client')
            ->assertJsonPath('data.customers.0.code', 'CUS-0001');
        $this->assertEquals(500000, $meta->json('data.customers.0.credit_limit'));
        $this->assertEquals(30000, $meta->json('data.customers.0.outstanding_balance'));
        $this->assertEquals(470000, $meta->json('data.customers.0.available_credit'));
        $this->assertSame('ok', $meta->json('data.customers.0.credit_status'));

        $created = $this->postJson('/api/mobile/orders', [
            'requested_delivery_date' => '2026-08-22',
            'payment_type' => 'credit',
            'notes' => 'Client mobile order',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 5,
                    'item_type' => 'sale',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.order.source_app', 'client')
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.total', 15000)
            ->assertJsonPath('data.items.0.unit_price', 3000);

        $orderId = $created->json('data.order.id');

        $this->getJson('/api/mobile/orders?search='.$created->json('data.order.code'))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id', $orderId);

        $this->getJson("/api/mobile/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 5);
    }

    public function test_sales_can_place_order_for_assigned_route_customer()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());

        $productId = DB::table('products')->where('sku', 'VAL-1L')->value('id');
        $customerId = DB::table('customers')->where('code', 'CUS-0002')->value('id');

        $meta = $this->getJson('/api/mobile/orders/meta')
            ->assertOk()
            ->assertJsonPath('data.app', 'sales');
        $assignedCustomer = collect($meta->json('data.customers'))->firstWhere('code', 'CUS-0001');
        $this->assertEquals(30000, $assignedCustomer['outstanding_balance']);
        $this->assertEquals(470000, $assignedCustomer['available_credit']);

        $created = $this->postJson('/api/mobile/orders', [
            'customer_id' => $customerId,
            'requested_delivery_date' => '2026-08-23',
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 2,
                    'item_type' => 'sale',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.order.source_app', 'sales')
            ->assertJsonPath('data.order.shop_name', 'Cherry Mini Mart')
            ->assertJsonPath('data.order.total', 16400);

        $this->assertDatabaseHas('orders', [
            'id' => $created->json('data.order.id'),
            'customer_id' => $customerId,
            'source_app' => 'sales',
        ]);

        $this->getJson('/api/mobile/orders?customer_id='.$customerId)
            ->assertOk()
            ->assertJsonPath('data.items.0.customer_id', $customerId);

        $this->getJson('/api/mobile/orders?search=Cherry')
            ->assertOk()
            ->assertJsonPath('data.items.0.customer_id', $customerId)
            ->assertJsonPath('data.items.0.shop_name', 'Cherry Mini Mart');
    }

    public function test_sales_can_place_a_cash_order_for_a_non_registered_customer_on_the_assigned_route()
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $this->actingAs($sales);

        $routeId = DB::table('employees')->where('id', $sales->employee_id)->value('assigned_route_id');
        $route = DB::table('routes')->find($routeId);
        $productId = DB::table('products')->where('sku', 'VAL-1L')->value('id');

        $created = $this->postJson('/api/mobile/orders', [
            'area_id' => $route->area_id,
            'route_id' => $route->id,
            'recipient_name' => 'Route Walk-in Shop',
            'recipient_phone' => '09-555-123-456',
            'delivery_address' => 'Temporary stall beside the route market',
            'payment_type' => 'cash',
            'items' => [['product_id' => $productId, 'quantity' => 2, 'item_type' => 'sale']],
        ])->assertCreated()
            ->assertJsonPath('data.order.customer_id', null)
            ->assertJsonPath('data.order.source_app', 'sales')
            ->assertJsonPath('data.order.shop_name', 'Route Walk-in Shop')
            ->assertJsonPath('data.order.route_id', $route->id);

        $this->getJson('/api/mobile/orders?search=Route%20Walk-in')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $created->json('data.order.id'));
    }

    public function test_client_can_view_invoiced_order_status_details()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'client@valley.test')->firstOrFail());

        $invoicedOrderId = DB::table('orders')->where('code', 'ORD-202608-0002')->value('id');

        $this->getJson('/api/mobile/orders?status=invoiced&search=ORD-202608-0002')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.status', 'invoiced')
            ->assertJsonPath('data.items.0.invoice_code', 'INV-202608-0001')
            ->assertJsonPath('data.items.0.invoice_status', 'draft');

        $this->getJson("/api/mobile/orders/{$invoicedOrderId}")
            ->assertOk()
            ->assertJsonPath('data.order.status', 'invoiced')
            ->assertJsonPath('data.order.confirmed_at', '2026-08-16 09:30:00')
            ->assertJsonPath('data.order.invoice_code', 'INV-202608-0001')
            ->assertJsonPath('data.order.invoice_date', '2026-08-17')
            ->assertJsonPath('data.order.due_date', '2026-08-24')
            ->assertJsonPath('data.items.0.quantity', 12);
    }

    public function test_mobile_order_history_excludes_adjustment_only_records()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'client@valley.test')->firstOrFail());

        $returnId = DB::table('orders')->where('code', 'RET-202608-0001')->value('id');

        $this->getJson('/api/mobile/orders?search=RET-202608-0001')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->getJson("/api/mobile/orders/{$returnId}")
            ->assertNotFound();
    }

    public function test_sales_mobile_order_is_limited_to_assigned_route()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());

        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $outsideCustomerId = DB::table('customers')->where('code', 'CUS-0004')->value('id');

        $this->postJson('/api/mobile/orders', [
            'customer_id' => $outsideCustomerId,
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 1,
                    'item_type' => 'sale',
                ],
            ],
        ])->assertForbidden();
    }

    public function test_mobile_order_api_requires_mobile_order_permission()
    {
        $this->seed();

        $this->getJson('/api/mobile/orders')->assertUnauthorized();

        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail())
            ->getJson('/api/mobile/orders')
            ->assertForbidden();
    }

    public function test_sales_can_save_edit_discount_and_cancel_a_draft_order()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $productId = DB::table('products')->where('sku', 'VAL-1L')->value('id');
        $customerId = DB::table('customers')->where('code', 'CUS-0002')->value('id');

        $created = $this->postJson('/api/mobile/orders', [
            'customer_id' => $customerId,
            'payment_type' => 'cash',
            'save_as' => 'draft',
            'items' => [
                ['product_id' => $productId, 'quantity' => 2, 'discount_amount' => 400],
                ['product_id' => $productId, 'quantity' => 1, 'item_type' => 'foc'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.order.status', 'draft')
            ->assertJsonPath('data.order.discount_total', 400)
            ->assertJsonPath('data.order.total', 16000)
            ->assertJsonPath('data.items.1.item_type', 'foc')
            ->assertJsonPath('data.items.1.unit_price', 0)
            ->assertJsonPath('data.items.1.line_total', 0);

        $orderId = $created->json('data.order.id');
        $this->putJson("/api/mobile/orders/{$orderId}", [
            'customer_id' => $customerId,
            'payment_type' => 'cash',
            'save_as' => 'pending',
            'items' => [['product_id' => $productId, 'quantity' => 3, 'discount_amount' => 600]],
        ])->assertOk()
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.total', 24000);

        $this->postJson("/api/mobile/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.order.status', 'cancelled');
    }
}
