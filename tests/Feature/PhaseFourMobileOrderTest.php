<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseFourMobileOrderTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertEquals(36000, $meta->json('data.customers.0.outstanding_balance'));
        $this->assertEquals(464000, $meta->json('data.customers.0.available_credit'));
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
        $this->assertEquals(36000, $assignedCustomer['outstanding_balance']);
        $this->assertEquals(464000, $assignedCustomer['available_credit']);

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
}
