<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseThreePayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_generate_monthly_payroll_draft_from_attendance()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $generated = $this->postJson('/api/payrolls/generate', [
            'month' => '2026-08',
            'employee_type' => 'sales',
        ])->assertCreated()
            ->assertJsonPath('data.payroll.code', 'PAY-202608-SALES')
            ->assertJsonPath('data.payroll.status', 'draft')
            ->assertJsonPath('data.payroll.items_count', 2)
            ->assertJsonPath('data.payroll.total_net', 830000);

        $payrollId = $generated->json('data.payroll.id');
        $salesEmployeeId = DB::table('employees')->where('code', 'SAL-001')->value('id');

        $this->getJson("/api/payrolls/{$payrollId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.employee_type', 'sales')
            ->assertJsonPath('data.items.1.employee_type', 'sales');

        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $payrollId,
            'employee_id' => $salesEmployeeId,
            'allowance_amount' => 50000,
            'incentive_amount' => 20000,
            'net_pay' => 450000,
        ]);

        $this->getJson('/api/payrolls?month=2026-08&employee_type=sales')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.total_net', 830000);
    }

    public function test_generating_same_draft_refreshes_existing_payroll()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $first = $this->postJson('/api/payrolls/generate', [
            'month' => '2026-08',
            'employee_type' => 'driver',
        ])->assertCreated();

        $second = $this->postJson('/api/payrolls/generate', [
            'month' => '2026-08',
            'employee_type' => 'driver',
        ])->assertCreated();

        $this->assertSame($first->json('data.payroll.id'), $second->json('data.payroll.id'));
        $this->assertSame(1, DB::table('payrolls')->where('month', '2026-08')->where('employee_type', 'driver')->count());
        $this->assertSame(1, DB::table('payroll_items')->where('payroll_id', $second->json('data.payroll.id'))->count());
        $this->assertDatabaseHas('payroll_items', [
            'payroll_id' => $second->json('data.payroll.id'),
            'advance_deduction' => 30000,
            'ot_amount' => 15000,
            'net_pay' => 345000,
        ]);
    }

    public function test_office_can_approve_and_mark_payroll_paid()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $generated = $this->postJson('/api/payrolls/generate', [
            'month' => '2026-08',
            'employee_type' => 'sales',
        ])->assertCreated();

        $payrollId = $generated->json('data.payroll.id');

        $this->postJson("/api/payrolls/{$payrollId}/approve")
            ->assertOk()
            ->assertJsonPath('data.payroll.status', 'approved');

        $this->assertDatabaseHas('payrolls', [
            'id' => $payrollId,
            'status' => 'approved',
        ]);

        $this->postJson('/api/payrolls/generate', [
            'month' => '2026-08',
            'employee_type' => 'sales',
        ])->assertConflict();

        $this->postJson("/api/payrolls/{$payrollId}/mark-paid", [
            'payment_reference' => 'KBZ-202608-SALES',
        ])->assertOk()
            ->assertJsonPath('data.payroll.status', 'paid')
            ->assertJsonPath('data.payroll.payment_reference', 'KBZ-202608-SALES');

        $this->postJson("/api/payrolls/{$payrollId}/approve")
            ->assertConflict();
    }

    public function test_office_can_manage_payroll_adjustments()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $employeeId = DB::table('employees')->where('code', 'SAL-002')->value('id');

        $created = $this->postJson('/api/payroll-adjustments', [
            'employee_id' => $employeeId,
            'adjustment_type' => 'allowance',
            'title' => 'Uniform allowance',
            'amount' => 25000,
            'effective_date' => '2026-08-20',
            'status' => 'active',
            'notes' => 'August uniform top-up',
        ])->assertCreated()
            ->assertJsonPath('data.adjustment.employee_code', 'SAL-002')
            ->assertJsonPath('data.adjustment.effective_date', '2026-08-20');

        $adjustmentId = $created->json('data.adjustment.id');

        $this->getJson('/api/payroll-adjustments?month=2026-08&adjustment_type=allowance&search=Uniform')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.title', 'Uniform allowance');

        $this->putJson("/api/payroll-adjustments/{$adjustmentId}", [
            'employee_id' => $employeeId,
            'adjustment_type' => 'allowance',
            'title' => 'Uniform allowance',
            'amount' => 20000,
            'effective_date' => '2026-08-20',
            'status' => 'active',
            'notes' => null,
        ])->assertOk()
            ->assertJsonPath('data.adjustment.amount', 20000);

        $this->deleteJson("/api/payroll-adjustments/{$adjustmentId}")
            ->assertOk();

        $this->assertDatabaseMissing('payroll_adjustments', [
            'id' => $adjustmentId,
        ]);
    }

    public function test_sales_and_driver_can_view_their_own_paid_salary_history()
    {
        $this->seed();

        $this->getJson('/api/mobile/payroll/history')->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail())
            ->getJson('/api/mobile/payroll/history')
            ->assertOk()
            ->assertJsonPath('data.summary.payments_count', 1)
            ->assertJsonPath('data.summary.total_net', 425000)
            ->assertJsonPath('data.items.0.employee_code', 'SAL-001')
            ->assertJsonPath('data.items.0.month', '2026-07')
            ->assertJsonPath('data.items.0.status', 'paid');

        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail())
            ->getJson('/api/mobile/payroll/history?month=2026-07')
            ->assertOk()
            ->assertJsonPath('data.summary.payments_count', 1)
            ->assertJsonPath('data.summary.total_net', 350000)
            ->assertJsonPath('data.items.0.employee_code', 'DRV-001')
            ->assertJsonPath('data.items.0.payment_reference', 'CASH-202607-DRIVER');
    }

    public function test_office_can_filter_paid_salary_history()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $this->getJson('/api/payroll-history?month=2026-07&employee_type=sales&search=SAL-001')
            ->assertOk()
            ->assertJsonPath('data.summary.payments_count', 1)
            ->assertJsonPath('data.summary.employees_count', 1)
            ->assertJsonPath('data.summary.total_net', 425000)
            ->assertJsonPath('data.items.0.employee_code', 'SAL-001')
            ->assertJsonPath('data.items.0.payroll_code', 'PAY-202607-SALES')
            ->assertJsonPath('data.items.0.status', 'paid');

        $this->getJson('/api/payroll-history?month=2026-07')
            ->assertOk()
            ->assertJsonPath('data.summary.payments_count', 2)
            ->assertJsonPath('data.summary.total_net', 775000);
    }

    public function test_payroll_api_requires_office_payroll_permission()
    {
        $this->seed();

        $this->getJson('/api/payrolls')->assertUnauthorized();
        $this->getJson('/api/payroll-history')->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail())
            ->getJson('/api/payrolls')
            ->assertForbidden();

        $this->getJson('/api/payroll-history')
            ->assertForbidden();
    }
}
