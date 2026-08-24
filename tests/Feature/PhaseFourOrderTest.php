<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseFourOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_create_view_and_confirm_order()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $customerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');

        $created = $this->postJson('/api/orders', [
            'customer_id' => $customerId,
            'order_date' => '2026-08-18',
            'requested_delivery_date' => '2026-08-19',
            'payment_type' => 'credit',
            'notes' => 'Feature test order',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 10,
                    'item_type' => 'sale',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.total', 30000)
            ->assertJsonPath('data.items.0.product_sku', 'VAL-5G')
            ->assertJsonPath('data.items.0.unit_price', 3000);

        $orderId = $created->json('data.order.id');
        $orderCode = $created->json('data.order.code');

        $this->assertStringStartsWith('ORD-202608-', $orderCode);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'customer_id' => $customerId,
            'status' => 'pending',
            'total' => 30000,
        ]);

        $this->getJson("/api/orders?search={$orderCode}&status=pending")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.code', $orderCode)
            ->assertJsonPath('data.summary.pending_count', 1);

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.order.code', $orderCode)
            ->assertJsonPath('data.items.0.quantity', 10);

        $this->postJson("/api/orders/{$orderId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.order.status', 'confirmed');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'confirmed',
        ]);

        $this->postJson("/api/orders/{$orderId}/confirm")
            ->assertConflict();
    }

    public function test_order_api_requires_office_order_permission()
    {
        $this->seed();
        $orderId = DB::table('orders')->where('code', 'ORD-202608-0001')->value('id');

        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson('/api/orders/meta')->assertUnauthorized();
        $this->postJson("/api/orders/{$orderId}/cancel")->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail())
            ->getJson('/api/orders')
            ->assertForbidden();

        $this->postJson('/api/orders', [])->assertForbidden();
        $this->postJson("/api/orders/{$orderId}/cancel")->assertForbidden();
    }

    public function test_office_can_cancel_pending_and_confirmed_orders()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $pendingOrderId = DB::table('orders')->where('code', 'ORD-202608-0001')->value('id');

        $this->postJson("/api/orders/{$pendingOrderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.order.status', 'cancelled');

        $this->assertDatabaseHas('orders', [
            'id' => $pendingOrderId,
            'status' => 'cancelled',
        ]);

        $this->postJson("/api/orders/{$pendingOrderId}/cancel")
            ->assertConflict();

        $customerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $created = $this->postJson('/api/orders', [
            'customer_id' => $customerId,
            'order_date' => '2026-08-27',
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 4,
                    'item_type' => 'sale',
                ],
            ],
        ])->assertCreated();
        $confirmedOrderId = $created->json('data.order.id');

        $this->postJson("/api/orders/{$confirmedOrderId}/confirm")
            ->assertOk();

        $this->postJson("/api/orders/{$confirmedOrderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.order.status', 'cancelled');

        $invoicedOrderId = DB::table('orders')->where('code', 'ORD-202608-0002')->value('id');
        $this->postJson("/api/orders/{$invoicedOrderId}/cancel")
            ->assertConflict();
    }

    public function test_office_can_override_customer_price_type_on_order_entry()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $customerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $retailPriceTypeId = DB::table('price_types')->where('code', 'RTL')->value('id');

        $created = $this->postJson('/api/orders', [
            'customer_id' => $customerId,
            'price_type_id' => $retailPriceTypeId,
            'order_date' => '2026-08-28',
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 2,
                    'item_type' => 'sale',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.order.price_type', 'Retail')
            ->assertJsonPath('data.order.total', 7000)
            ->assertJsonPath('data.items.0.unit_price', 3500);

        $this->assertDatabaseHas('orders', [
            'id' => $created->json('data.order.id'),
            'price_type_id' => $retailPriceTypeId,
            'total' => 7000,
        ]);
    }

    public function test_regular_order_api_excludes_adjustment_only_records()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $returnId = DB::table('orders')->where('code', 'RET-202608-0001')->value('id');

        $this->getJson('/api/orders?search=RET-202608-0001')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->getJson("/api/orders/{$returnId}")
            ->assertNotFound();

        $this->postJson('/api/orders', [
            'customer_id' => DB::table('customers')->where('code', 'CUS-0001')->value('id'),
            'order_date' => '2026-08-26',
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => DB::table('products')->where('sku', 'VAL-5G')->value('id'),
                    'quantity' => 1,
                    'item_type' => 'damage',
                ],
            ],
        ])->assertUnprocessable();
    }
}
