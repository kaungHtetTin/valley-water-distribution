<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseFourOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_create_a_customer_independent_order_with_its_own_destination()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $route = DB::table('routes')->where('code', 'TGI-N')->first();
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');

        $created = $this->postJson('/api/orders', [
            'customer_id' => null,
            'area_id' => $route->area_id,
            'route_id' => $route->id,
            'recipient_name' => 'North Walk-in Shop',
            'recipient_phone' => '09-777-000-111',
            'delivery_address' => 'No. 24, Northern Market, Taunggyi',
            'payment_type' => 'cash',
            'items' => [[
                'product_id' => $productId,
                'quantity' => 3,
                'item_type' => 'sale',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.order.customer_id', null)
            ->assertJsonPath('data.order.shop_name', 'North Walk-in Shop')
            ->assertJsonPath('data.order.area_id', $route->area_id)
            ->assertJsonPath('data.order.route_id', $route->id)
            ->assertJsonPath('data.order.delivery_address', 'No. 24, Northern Market, Taunggyi');

        $orderId = $created->json('data.order.id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'customer_id' => null,
            'recipient_name' => 'North Walk-in Shop',
            'route_id' => $route->id,
        ]);

        $confirmed = $this->postJson("/api/orders/{$orderId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.order.status', 'invoiced')
            ->assertJsonPath('data.financial_record.status', 'issued');
        $invoiceId = $confirmed->json('data.financial_record.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'customer_id' => null,
            'recipient_name' => 'North Walk-in Shop',
            'status' => 'issued',
        ]);
        $this->assertDatabaseHas('financial_transactions', [
            'reference_type' => 'invoice',
            'reference_id' => $invoiceId,
            'direction' => 'in',
            'category' => 'cash_sale',
            'amount' => 9000,
        ]);

        $delivery = $this->postJson('/api/deliveries', [
            'invoice_ids' => [$invoiceId],
            'warehouse_id' => DB::table('warehouses')->where('is_active', true)->value('id'),
            'route_id' => $route->id,
            'driver_id' => DB::table('employees')->where('employee_type', 'driver')->where('is_active', true)->value('id'),
            'vehicle_id' => DB::table('vehicles')->where('is_active', true)->value('id'),
            'planned_date' => '2026-09-10',
        ])->assertCreated()
            ->assertJsonPath('data.delivery.shop_name', 'North Walk-in Shop')
            ->assertJsonPath('data.delivery.delivery_address', 'No. 24, Northern Market, Taunggyi');

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->json('data.delivery.id'),
            'customer_id' => null,
            'recipient_name' => 'North Walk-in Shop',
            'route_id' => $route->id,
        ]);
    }

    public function test_guest_order_requires_a_valid_destination_and_cash_payment()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $route = DB::table('routes')->where('code', 'TGI-N')->first();
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');
        $payload = [
            'area_id' => $route->area_id,
            'route_id' => $route->id,
            'recipient_name' => 'Guest Shop',
            'delivery_address' => 'Guest delivery address',
            'payment_type' => 'credit',
            'credit_due_date' => '2026-08-25',
            'items' => [['product_id' => $productId, 'quantity' => 1, 'item_type' => 'sale']],
        ];

        $this->postJson('/api/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Credit orders require a registered customer.');

        $otherAreaId = DB::table('areas')->where('id', '!=', $route->area_id)->value('id');
        $this->postJson('/api/orders', array_merge($payload, ['payment_type' => 'cash', 'area_id' => $otherAreaId]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The selected route must belong to the selected area.');
    }

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
            ->assertJsonPath('data.order.status', 'invoiced')
            ->assertJsonPath('data.order.credit_due_date', '2026-08-25')
            ->assertJsonPath('data.financial_record.status', 'issued');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'invoiced',
            'credit_due_date' => '2026-08-25 00:00:00',
        ]);
        $this->assertDatabaseHas('invoices', [
            'order_id' => $orderId,
            'status' => 'issued',
            'due_date' => '2026-08-25 00:00:00',
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
