<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseSevenFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_collection_reduces_receivable_and_posts_to_cash_book()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $customerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        $invoiceId = DB::table('invoices')->where('code', 'INV-202608-0001')->value('id');

        $before = $this->getJson('/api/finance/receivables')->assertOk();
        $this->assertEquals(30000, collect($before->json('data.items'))->firstWhere('code', 'CUS-0001')['outstanding_amount']);

        $created = $this->postJson('/api/finance/collections', [
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'collection_date' => '2026-08-17',
            'amount' => 4000,
            'payment_method' => 'cash',
            'reference_no' => 'RCPT-TEST-001',
            'notes' => 'Office counter collection',
        ])->assertCreated()
            ->assertJsonPath('data.collection.status', 'approved')
            ->assertJsonPath('data.collection.amount', 4000);

        $this->assertStringStartsWith('COL-202608-', $created->json('data.collection.code'));
        $after = $this->getJson('/api/finance/receivables')->assertOk();
        $this->assertEquals(26000, collect($after->json('data.items'))->firstWhere('code', 'CUS-0001')['outstanding_amount']);

        $this->getJson("/api/finance/customers/{$customerId}/ledger")
            ->assertOk()
            ->assertJsonPath('data.summary.collected_amount', 10000)
            ->assertJsonPath('data.summary.outstanding_amount', 26000);
        $this->getJson('/api/finance/books/cash')
            ->assertOk()
            ->assertJsonPath('data.summary.inflow', 10000);

        $this->postJson('/api/finance/collections', [
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'collection_date' => '2026-08-17',
            'amount' => 30000,
            'payment_method' => 'cash',
        ])->assertUnprocessable();
    }

    public function test_sales_collection_is_scoped_and_requires_office_approval()
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $customerId = DB::table('customers')->where('code', 'CUS-0002')->value('id');
        $outsideCustomerId = DB::table('customers')->where('code', 'CUS-0004')->value('id');

        $this->actingAs($sales);
        $this->getJson('/api/mobile/finance/meta')
            ->assertOk()
            ->assertJsonPath('data.app', 'sales');
        $created = $this->postJson('/api/mobile/finance/collections', [
            'customer_id' => $customerId,
            'collection_date' => '2026-08-17',
            'amount' => 7000,
            'payment_method' => 'cash',
            'reference_no' => 'FIELD-TEST-01',
        ])->assertCreated();
        $collectionId = $created->json('data.collection_id');
        $this->assertDatabaseHas('collections', ['id' => $collectionId, 'employee_id' => $sales->employee_id, 'source_app' => 'sales', 'status' => 'submitted']);
        $this->assertDatabaseMissing('financial_transactions', ['reference_type' => 'collection', 'reference_id' => $collectionId]);

        $this->postJson('/api/mobile/finance/collections', [
            'customer_id' => $outsideCustomerId,
            'collection_date' => '2026-08-17',
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertForbidden();

        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $this->postJson("/api/finance/collections/{$collectionId}/review", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.collection.status', 'approved');
        $this->assertDatabaseHas('financial_transactions', ['reference_type' => 'collection', 'reference_id' => $collectionId, 'direction' => 'in']);
    }

    public function test_driver_collection_requires_an_assigned_delivery()
    {
        $this->seed();
        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $delivery = DB::table('deliveries')->where('code', 'DEL-202608-0001')->first();

        $this->actingAs($driver);
        $this->postJson('/api/mobile/finance/collections', [
            'customer_id' => $delivery->customer_id,
            'delivery_id' => $delivery->id,
            'collection_date' => '2026-08-17',
            'amount' => 2000,
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->postJson('/api/mobile/finance/collections', [
            'customer_id' => DB::table('customers')->where('code', 'CUS-0004')->value('id'),
            'delivery_id' => $delivery->id,
            'collection_date' => '2026-08-17',
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertForbidden();
    }

    public function test_mobile_expenses_are_reviewed_and_included_in_profit_and_loss()
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $this->actingAs($sales);
        $created = $this->postJson('/api/mobile/finance/expenses', [
            'expense_date' => '2026-08-17',
            'category' => 'travel',
            'description' => 'Route taxi fare',
            'amount' => 2500,
            'payment_method' => 'cash',
            'notes' => 'Customer visit',
        ])->assertCreated();
        $expenseId = $created->json('data.expense_id');
        $this->assertDatabaseHas('expenses', ['id' => $expenseId, 'expense_type' => 'outdoor', 'status' => 'submitted']);

        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $this->postJson("/api/finance/expenses/{$expenseId}/review", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.expense.status', 'approved');
        $this->assertDatabaseHas('financial_transactions', ['reference_type' => 'expense', 'reference_id' => $expenseId, 'direction' => 'out']);

        $this->getJson('/api/finance/profit-loss?date_from=2026-01-01&date_to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.costs.payroll', 775000)
            ->assertJsonPath('data.costs.vehicle', 645000)
            ->assertJsonPath('data.costs.outdoor_employee', 5500)
            ->assertJsonPath('data.costs.daily_expense', 8000);
    }

    public function test_supplier_ledger_and_bank_book_are_available()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $supplierId = DB::table('suppliers')->where('code', 'SUP-001')->value('id');

        $this->getJson('/api/finance/suppliers')
            ->assertOk()
            ->assertJsonPath('data.summary.payable_amount', 100000);
        $this->postJson("/api/finance/suppliers/{$supplierId}/ledger", [
            'entry_date' => '2026-08-17',
            'entry_type' => 'payment',
            'reference_no' => 'SUP-PAY-TEST',
            'description' => 'Additional supplier payment',
            'amount' => 20000,
        ])->assertCreated();
        $this->getJson("/api/finance/suppliers/{$supplierId}/ledger")
            ->assertOk()
            ->assertJsonPath('data.summary.balance', 80000);
        $this->getJson('/api/finance/books/bank')
            ->assertOk()
            ->assertJsonPath('data.summary.outflow', 530000)
            ->assertJsonPath('data.summary.balance', -530000);
    }

    public function test_client_sees_only_own_ledger_and_payment_history()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'client@valley.test')->firstOrFail());

        $this->getJson('/api/mobile/finance')
            ->assertOk()
            ->assertJsonPath('data.customer.code', 'CUS-0001')
            ->assertJsonPath('data.summary.collected_amount', 6000)
            ->assertJsonPath('data.summary.outstanding_amount', 30000)
            ->assertJsonPath('data.entries.0.reference', 'COL-202608-0001');
        $this->postJson('/api/mobile/finance/collections', [])->assertForbidden();
        $this->postJson('/api/mobile/finance/expenses', [])->assertForbidden();
    }

    public function test_finance_endpoints_enforce_permissions()
    {
        $this->seed();
        $this->getJson('/api/finance/collections')->assertUnauthorized();
        $this->getJson('/api/mobile/finance')->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->getJson('/api/finance/collections')->assertForbidden();
        $this->postJson('/api/finance/expenses', [])->assertForbidden();

        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $this->getJson('/api/mobile/finance')->assertForbidden();
    }
}
