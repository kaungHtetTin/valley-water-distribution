<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseFourInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_create_invoice_from_confirmed_order()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $customerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        $productId = DB::table('products')->where('sku', 'VAL-5G')->value('id');

        $createdOrder = $this->postJson('/api/orders', [
            'customer_id' => $customerId,
            'order_date' => '2026-08-20',
            'requested_delivery_date' => '2026-08-21',
            'payment_type' => 'cash',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 8,
                    'item_type' => 'sale',
                ],
            ],
        ])->assertCreated();

        $orderId = $createdOrder->json('data.order.id');

        $this->postJson("/api/orders/{$orderId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.order.status', 'confirmed');

        $createdInvoice = $this->postJson('/api/invoices/from-order', [
            'order_id' => $orderId,
            'invoice_date' => '2026-08-21',
            'due_date' => '2026-08-28',
            'notes' => 'Invoice test',
        ])->assertCreated()
            ->assertJsonPath('data.invoice.status', 'draft')
            ->assertJsonPath('data.invoice.total', 24000)
            ->assertJsonPath('data.items.0.product_sku', 'VAL-5G')
            ->assertJsonPath('data.items.0.line_total', 24000);

        $invoiceId = $createdInvoice->json('data.invoice.id');
        $invoiceCode = $createdInvoice->json('data.invoice.code');

        $this->assertStringStartsWith('INV-202608-', $invoiceCode);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'invoiced',
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'order_id' => $orderId,
            'total' => 24000,
        ]);

        $this->getJson("/api/invoices?search={$invoiceCode}&status=draft")
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.code', $invoiceCode)
            ->assertJsonPath('data.summary.draft_count', 1);

        $this->getJson("/api/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.invoice.code', $invoiceCode)
            ->assertJsonPath('data.items.0.quantity', 8);

        $this->postJson('/api/invoices/from-order', [
            'order_id' => $orderId,
            'invoice_date' => '2026-08-21',
        ])->assertConflict();
    }

    public function test_invoice_creation_requires_confirmed_order()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $pendingOrderId = DB::table('orders')->where('code', 'ORD-202608-0001')->value('id');

        $this->postJson('/api/invoices/from-order', [
            'order_id' => $pendingOrderId,
            'invoice_date' => '2026-08-21',
        ])->assertConflict();
    }

    public function test_invoice_creation_rejects_adjustment_records()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $returnOrderId = DB::table('orders')->where('code', 'RET-202608-0001')->value('id');

        $this->postJson('/api/invoices/from-order', [
            'order_id' => $returnOrderId,
            'invoice_date' => '2026-08-26',
        ])->assertUnprocessable();
    }

    public function test_office_can_issue_and_cancel_invoice()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $invoiceId = DB::table('invoices')->where('code', 'INV-202608-0001')->value('id');
        $orderId = DB::table('orders')->where('code', 'ORD-202608-0002')->value('id');

        $this->postJson("/api/invoices/{$invoiceId}/issue")
            ->assertOk()
            ->assertJsonPath('data.invoice.status', 'issued');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'issued',
        ]);

        $this->postJson("/api/invoices/{$invoiceId}/issue")
            ->assertConflict();

        $this->postJson("/api/invoices/{$invoiceId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.invoice.status', 'cancelled');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'confirmed',
        ]);

        $this->postJson("/api/invoices/{$invoiceId}/cancel")
            ->assertConflict();
    }

    public function test_invoice_api_requires_office_invoice_permission()
    {
        $this->seed();
        $invoiceId = DB::table('invoices')->where('code', 'INV-202608-0001')->value('id');

        $this->getJson('/api/invoices')->assertUnauthorized();
        $this->getJson('/api/invoices/meta')->assertUnauthorized();
        $this->postJson("/api/invoices/{$invoiceId}/issue")->assertUnauthorized();
        $this->postJson("/api/invoices/{$invoiceId}/cancel")->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail())
            ->getJson('/api/invoices')
            ->assertForbidden();

        $this->postJson('/api/invoices/from-order', [])->assertForbidden();
        $this->postJson("/api/invoices/{$invoiceId}/issue")->assertForbidden();
        $this->postJson("/api/invoices/{$invoiceId}/cancel")->assertForbidden();
    }
}
