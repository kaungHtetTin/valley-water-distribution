<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupervisorMobileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-31 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sales_supervisor_can_sign_in_and_view_only_the_assigned_team(): void
    {
        $this->seed();

        $this->postJson('/api/auth/login', [
            'email' => 'sales.supervisor@valley.test',
            'password' => 'password',
            'app' => 'supervisor',
        ])->assertOk()
            ->assertJsonPath('data.user.role', 'Sales Supervisor')
            ->assertJsonPath('data.user.default_app', 'supervisor')
            ->assertJsonFragment(['supervisor.team.view']);

        $this->getJson('/api/mobile/supervisor')
            ->assertOk()
            ->assertJsonPath('data.supervisor.code', 'SUP-001')
            ->assertJsonPath('data.summary.team_members', 2)
            ->assertJsonCount(2, 'data.team')
            ->assertJsonFragment(['code' => 'SAL-001'])
            ->assertJsonFragment(['code' => 'SAL-002']);
    }

    public function test_supervisor_can_submit_own_outdoor_expense_for_office_review(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $this->actingAs($supervisor);
        $this->getJson('/api/mobile/finance/meta')->assertOk()
            ->assertJsonPath('data.app', 'supervisor')->assertJsonFragment(['travel']);
        $created = $this->postJson('/api/mobile/finance/expenses', [
            'expense_date' => '2026-08-31', 'category' => 'travel',
            'description' => 'Team customer visit taxi fare', 'amount' => 3500,
            'payment_method' => 'cash', 'notes' => 'Supervisor field visit',
        ])->assertCreated()->assertJsonPath('data.expense.source_app', 'supervisor')
            ->assertJsonPath('data.expense.status', 'submitted');
        $expenseId = $created->json('data.expense_id');
        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId, 'employee_id' => $supervisor->employee_id,
            'submitted_by' => $supervisor->id, 'expense_type' => 'outdoor',
        ]);
        $this->getJson('/api/mobile/finance')->assertOk()
            ->assertJsonCount(1, 'data.expenses')->assertJsonPath('data.expenses.0.id', $expenseId);
        $this->postJson('/api/mobile/finance/collections', [])->assertForbidden();
        $this->postJson("/api/finance/expenses/{$expenseId}/review", ['status' => 'approved'])->assertForbidden();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $response = $this->getJson('/api/mobile/finance')->assertOk();
        $this->assertNotContains($expenseId, collect($response->json('data.expenses'))->pluck('id')->all());

        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $this->postJson("/api/finance/expenses/{$expenseId}/review", ['status' => 'approved'])
            ->assertOk()->assertJsonPath('data.expense.status', 'approved');
        $this->assertDatabaseHas('financial_transactions', ['reference_type' => 'expense', 'reference_id' => $expenseId, 'direction' => 'out']);
    }

    public function test_supervisor_can_view_assigned_representative_customer_visits_by_date(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $this->actingAs($supervisor);
        $member = DB::table('employees')->where('code', 'SAL-001')->first();
        $customerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        DB::table('sales_route_visits')->insert([
            'employee_id' => $member->id, 'route_id' => $member->assigned_route_id,
            'customer_id' => $customerId, 'visit_date' => '2026-08-31', 'status' => 'completed',
            'completed_at' => '2026-08-31 09:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $url = "/api/mobile/supervisor/team/{$member->id}/customer-visits";
        $response = $this->getJson($url)->assertOk()->assertJsonPath('data.date', '2026-08-31')
            ->assertJsonPath('data.summary.total', 3)->assertJsonPath('data.summary.visited', 1)
            ->assertJsonPath('data.summary.not_visited', 2);
        $this->assertSame('completed', collect($response->json('data.customers'))->firstWhere('id', $customerId)['visit_status']);
        $this->getJson($url.'?date=2026-08-30')->assertOk()->assertJsonPath('data.summary.visited', 0)
            ->assertJsonPath('data.summary.not_visited', 3);
        $this->getJson($url.'?date=invalid')->assertUnprocessable();
        DB::table('employees')->where('id', $member->id)->update(['assigned_route_id' => null]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.customers', [])->assertJsonPath('data.summary.total', 0);
        DB::table('employees')->where('id', $member->id)->update(['supervisor_id' => null]);
        $this->getJson($url)->assertNotFound();
        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->getJson($url)->assertForbidden();
    }

    public function test_non_supervisor_and_unlinked_supervisor_profiles_are_rejected(): void
    {
        $this->seed();

        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $this->actingAs($sales)->getJson('/api/mobile/supervisor')->assertForbidden();

        $sales->update(['role' => 'Sales Supervisor']);
        $this->actingAs($sales->fresh())->getJson('/api/mobile/supervisor/team')->assertNotFound();
    }

    public function test_supervisor_cannot_view_a_representative_outside_their_team(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $warehouseEmployeeId = DB::table('employees')->where('code', 'WH-001')->value('id');

        $this->actingAs($supervisor)
            ->getJson("/api/mobile/supervisor/team/{$warehouseEmployeeId}")
            ->assertNotFound();
    }

    public function test_supervisor_can_view_monthly_and_yearly_kpi_for_a_team_member(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $templateId = DB::table('kpi_templates')->where('code', 'SALES-REP-V1')->value('id');
        $salesEmployeeId = DB::table('employees')->where('code', 'SAL-001')->value('id');

        $this->actingAs($owner)->postJson('/api/kpi-reviews/generate', [
            'month' => '2026-08',
            'template_id' => $templateId,
        ])->assertOk();

        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $this->actingAs($supervisor)
            ->getJson("/api/mobile/supervisor/team/{$salesEmployeeId}?period=month&month=2026-08")
            ->assertOk()
            ->assertJsonPath('data.representative.code', 'SAL-001')
            ->assertJsonPath('data.filters.period', 'month')
            ->assertJsonPath('data.summary.reviews', 1)
            ->assertJsonCount(7, 'data.metrics');

        $this->getJson("/api/mobile/supervisor/team/{$salesEmployeeId}?period=year&year=2026")
            ->assertOk()
            ->assertJsonPath('data.filters.period', 'year')
            ->assertJsonPath('data.summary.reviews', 1);
    }

    public function test_a_sales_representative_can_belong_to_only_one_supervisor(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $existingSupervisorId = DB::table('employees')->where('code', 'SUP-001')->value('id');
        $salesOneId = DB::table('employees')->where('code', 'SAL-001')->value('id');
        $salesTwoId = DB::table('employees')->where('code', 'SAL-002')->value('id');
        $secondSupervisorId = DB::table('employees')->insertGetId([
            'code' => 'SUP-002',
            'name' => 'Second Supervisor',
            'employee_type' => 'sales_supervisor',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson("/api/master-data/employees/{$secondSupervisorId}/sales-team", [
            'sales_representative_ids' => [$salesOneId],
        ])->assertUnprocessable()->assertJsonValidationErrors('sales_representative_ids');

        $this->putJson("/api/master-data/employees/{$existingSupervisorId}/sales-team", [
            'sales_representative_ids' => [$salesOneId],
        ])->assertOk()->assertJsonCount(1, 'data.team_members');

        $this->assertDatabaseHas('employees', ['id' => $salesOneId, 'supervisor_id' => $existingSupervisorId]);
        $this->assertDatabaseHas('employees', ['id' => $salesTwoId, 'supervisor_id' => null]);
    }
}
