<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KpiWorkflowTest extends TestCase
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

    public function test_clean_seed_assigns_default_kpi_roles_to_operational_staff(): void
    {
        $this->seed();

        $this->assertDatabaseCount('kpi_templates', 6);
        $this->assertSame(1, $this->profileCount('office', 'OFFICE-STAFF-V1'));
        $this->assertSame(2, $this->profileCount('sales', 'SALES-REP-V1'));
        $this->assertSame(1, $this->profileCount('driver', 'DRIVER-V1'));
        $this->assertSame(1, $this->profileCount('warehouse', 'STOREKEEPER-V1'));
        $this->assertDatabaseHas('kpi_staff_profiles', [
            'employee_id' => DB::table('employees')->where('code', 'EMP-001')->value('id'),
        ]);
    }

    public function test_role_targets_apply_to_every_staff_member_in_that_role(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $template = DB::table('kpi_templates')->where('code', 'SALES-REP-V1')->first();
        $metrics = DB::table('kpi_template_metrics')
            ->where('kpi_template_id', $template->id)
            ->where('calculation_type', '!=', 'manual')
            ->get();

        $this->putJson("/api/kpi-targets/roles/{$template->id}", [
            'target_bonus' => 55000,
            'apply_to_staff' => true,
            'targets' => $metrics->map(fn ($metric) => [
                'metric_id' => $metric->id,
                'target_value' => 25,
            ])->all(),
        ])->assertOk()
            ->assertJsonPath('data.staff_count', 2);

        $profiles = DB::table('kpi_staff_profiles')
            ->where('kpi_template_id', $template->id)
            ->get();
        $this->assertCount(2, $profiles);
        foreach ($profiles as $profile) {
            $this->assertEquals(55000, (float) $profile->target_bonus);
            $this->assertSame(
                $metrics->count(),
                DB::table('kpi_staff_target_items')->where('kpi_staff_profile_id', $profile->id)->count()
            );
            $this->assertSame(
                $metrics->count(),
                DB::table('kpi_staff_target_items')
                    ->where('kpi_staff_profile_id', $profile->id)
                    ->where('target_value', 25)
                    ->count()
            );
        }
    }

    public function test_employee_override_review_approval_bonus_and_reports_work_end_to_end(): void
    {
        $this->seed();
        $office = User::where('email', 'owner@valley.test')->firstOrFail();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $employeeId = (int) $sales->employee_id;
        $template = DB::table('kpi_templates')->where('code', 'SALES-REP-V1')->first();
        $metrics = DB::table('kpi_template_metrics')
            ->where('kpi_template_id', $template->id)
            ->orderBy('sort_order')
            ->get();

        $this->actingAs($office)->putJson("/api/kpi-targets/{$employeeId}", [
            'template_id' => $template->id,
            'target_bonus' => 60000,
            'targets' => $metrics
                ->where('calculation_type', '!=', 'manual')
                ->map(fn ($metric) => ['metric_id' => $metric->id, 'target_value' => 1])
                ->values()
                ->all(),
        ])->assertOk();

        $this->postJson('/api/kpi-reviews/generate', [
            'month' => '2026-08',
            'template_id' => $template->id,
        ])->assertOk()
            ->assertJsonPath('data.created', 2);

        $result = DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->where('kpi_results.employee_id', $employeeId)
            ->where('kpi_periods.month', '2026-08')
            ->select('kpi_results.*')
            ->first();
        $this->assertNotNull($result);
        $this->assertEquals(60000, (float) $result->target_bonus);

        $items = DB::table('kpi_result_items')
            ->join('kpi_template_metrics', 'kpi_result_items.kpi_template_metric_id', '=', 'kpi_template_metrics.id')
            ->where('kpi_result_items.kpi_result_id', $result->id)
            ->select('kpi_result_items.*', 'kpi_template_metrics.calculation_type')
            ->get();

        $this->putJson("/api/kpi-reviews/{$result->id}", [
            'notes' => 'Monthly manager review complete.',
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'target_value' => $item->calculation_type === 'manual' ? $item->target_value : 1,
                'actual_value' => $item->actual_value,
                'manual_score' => $item->calculation_type === 'manual' ? 100 : null,
            ])->all(),
        ])->assertOk();

        $this->postJson("/api/kpi-reviews/{$result->id}/submit")->assertOk()
            ->assertJsonPath('data.result.status', 'submitted');
        $this->postJson("/api/kpi-reviews/{$result->id}/approve")->assertOk()
            ->assertJsonPath('data.result.status', 'approved');
        $this->postJson("/api/kpi-reviews/{$result->id}/post-bonus")->assertOk()
            ->assertJsonPath('data.result.employee_code', 'SAL-001');
        $this->postJson("/api/kpi-reviews/{$result->id}/post-bonus")->assertStatus(409);

        $postedResult = DB::table('kpi_results')->where('id', $result->id)->first();
        $this->assertNotNull($postedResult->payroll_adjustment_id);
        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $postedResult->payroll_adjustment_id,
            'employee_id' => $employeeId,
            'adjustment_type' => 'incentive',
            'status' => 'active',
        ]);

        $this->getJson("/api/kpi-reports?period=year&year=2026&employee_id={$employeeId}")
            ->assertOk()
            ->assertJsonPath('data.summary.reviews', 1)
            ->assertJsonPath('data.reviews.0.employee_code', 'SAL-001');

        $this->actingAs($sales)->getJson('/api/mobile/kpi?month=2026-08')
            ->assertOk()
            ->assertJsonPath('data.result.employee_code', 'SAL-001')
            ->assertJsonPath('data.result.status', 'approved');
    }

    public function test_kpi_management_endpoints_reject_mobile_staff(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());

        $this->getJson('/api/kpi-targets')->assertForbidden();
        $this->getJson('/api/kpi-reviews')->assertForbidden();
        $this->getJson('/api/kpi-reports')->assertForbidden();
    }

    private function profileCount(string $employeeType, string $templateCode): int
    {
        return DB::table('kpi_staff_profiles')
            ->join('employees', 'kpi_staff_profiles.employee_id', '=', 'employees.id')
            ->join('kpi_templates', 'kpi_staff_profiles.kpi_template_id', '=', 'kpi_templates.id')
            ->where('employees.employee_type', $employeeType)
            ->where('kpi_templates.code', $templateCode)
            ->count();
    }
}
