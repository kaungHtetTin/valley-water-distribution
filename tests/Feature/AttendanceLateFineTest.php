<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\AttendanceCheckIn;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceLateFineTest extends TestCase
{
    use RefreshDatabase;

    private function checkIn(string $code, string $at, string $status = 'accepted')
    {
        $employee = DB::table('employees')->where('code', $code)->first();

        return AttendanceCheckIn::record([
            'employee_id' => $employee->id,
            'entered_employee_code' => $code,
            'attendance_location_id' => DB::table('attendance_locations')->value('id'),
            'attendance_at' => Carbon::parse($at, 'Asia/Yangon'),
            'status' => $status,
            'rejection_reason' => $status === 'rejected' ? 'gps_denied' : null,
        ]);
    }

    public function test_default_start_time_boundaries_rejections_and_salary_fallback(): void
    {
        $this->seed();
        DB::table('employees')->where('code', 'SAL-001')->update(['base_salary' => 480000]);
        $this->assertSame('08:00', Company::oldest('id')->value('attendance_start_time'));
        $this->assertSame('accepted', $this->checkIn('SAL-001', '2026-10-07 08:00:00')->status);
        $late = $this->checkIn('SAL-001', '2026-10-08 08:10:00');
        $this->assertSame('late', $late->status);
        $this->assertSame(10, $late->late_minutes);
        $this->assertEquals(10000, $late->late_fine);
        $this->assertSame(1, $this->checkIn('SAL-001', '2026-10-09 08:00:01')->late_minutes);
        $this->assertSame('accepted', $this->checkIn('SAL-001', '2026-10-10 07:59:59')->status);
        $rejected = $this->checkIn('SAL-001', '2026-10-11 10:00:00', 'rejected')->fresh();
        $this->assertEquals(0, $rejected->late_fine);
        $this->assertEquals(0, $rejected->late_minutes);
        DB::table('employees')->where('code', 'DRV-001')->update(['base_salary' => null]);
        $this->assertEquals(750, $this->checkIn('DRV-001', '2026-10-07 08:01:00')->late_fine);
    }

    public function test_snapshots_and_duplicate_submissions_do_not_increase_fines(): void
    {
        $this->seed();
        DB::table('employees')->where('code', 'SAL-001')->update(['base_salary' => 480000]);
        $first = $this->checkIn('SAL-001', '2026-10-07 08:10:00');
        Company::oldest('id')->first()->update(['attendance_start_time' => '09:00']);
        DB::table('employees')->where('code', 'SAL-001')->update(['base_salary' => 960000]);
        $duplicate = $this->checkIn('SAL-001', '2026-10-07 10:00:00');
        $this->assertSame($first->id, $duplicate->id);
        $this->assertEquals(10000, $first->fresh()->late_fine);
        $this->assertSame('08:00', $first->fresh()->start_time_snapshot);
        $this->assertSame('accepted', $this->checkIn('SAL-001', '2026-10-08 08:45:00')->status);
    }

    public function test_public_mobile_office_and_payroll_use_the_same_fine(): void
    {
        $this->seed();
        DB::table('employees')->where('code', 'SAL-001')->update(['base_salary' => 480000]);
        $this->travelTo(Carbon::parse('2026-10-07 08:10:00', 'Asia/Yangon'));
        $location = DB::table('attendance_locations')->where('code', 'ATT-OFFICE')->first();
        $payload = ['employee_code' => 'SAL-001', 'latitude' => $location->latitude, 'longitude' => $location->longitude];
        $response = $this->postJson('/api/public/attendance/'.$location->public_token, $payload)
            ->assertCreated()->assertJsonPath('data.result.status', 'late')
            ->assertJsonPath('data.result.late_minutes', 10)->assertJsonPath('data.result.late_fine', 10000);
        $recordId = $response->json('data.result.record_id');
        $this->postJson('/api/public/attendance/'.$location->public_token, $payload)
            ->assertJsonPath('data.result.record_id', $recordId);

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->getJson('/api/mobile/attendance/records?month=2026-10&per_page=1')
            ->assertOk()->assertJsonPath('data.summary.accepted', 1)
            ->assertJsonPath('data.summary.late_count', 1)->assertJsonPath('data.summary.late_minutes', 10)
            ->assertJsonPath('data.summary.late_fine', 10000);

        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $this->getJson('/api/attendance/summary?month=2026-10&search=SAL-001')
            ->assertOk()->assertJsonPath('data.totals.accepted', 1)
            ->assertJsonPath('data.totals.late_count', 1)->assertJsonPath('data.totals.late_minutes', 10)
            ->assertJsonPath('data.totals.late_fine', 10000);
        $this->postJson('/api/payrolls/generate', ['month' => '2026-10', 'employee_type' => 'sales'])->assertCreated();
        $this->assertDatabaseHas('payroll_items', [
            'employee_id' => User::where('email', 'sales@valley.test')->value('employee_id'),
            'accepted_count' => 1, 'other_deduction' => 10000, 'net_pay' => 470000,
        ]);
    }

    public function test_work_start_time_setting_is_validated_and_applies_to_future_check_ins(): void
    {
        $this->seed();
        $company = Company::oldest('id')->first();
        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->putJson('/api/settings/company', ['name' => $company->name, 'attendance_start_time' => '09:00'])->assertForbidden();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $this->putJson('/api/settings/company', ['name' => $company->name, 'attendance_start_time' => '25:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('attendance_start_time');
        $this->putJson('/api/settings/company', ['name' => $company->name, 'attendance_start_time' => '09:00'])
            ->assertOk()->assertJsonPath('data.company.attendance_start_time', '09:00');
        $this->assertSame('accepted', $this->checkIn('SAL-001', '2026-10-07 08:45:00')->status);
        $this->assertSame(10, $this->checkIn('SAL-001', '2026-10-08 09:10:00')->late_minutes);
    }

    public function test_mobile_check_ins_for_all_three_roles_accept_late_and_prevent_duplicate_fines(): void
    {
        $this->seed();
        $this->travelTo(Carbon::parse('2026-10-07 08:10:00', 'Asia/Yangon'));
        $location = DB::table('attendance_locations')->whereNotNull('warehouse_id')->first();
        foreach (['sales@valley.test', 'driver@valley.test', 'sales.supervisor@valley.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            DB::table('employees')->where('id', $user->employee_id)->update(['base_salary' => 480000]);
            $this->actingAs($user);
            $payload = ['attendance_location_id' => $location->id, 'latitude' => $location->latitude, 'longitude' => $location->longitude];
            $this->postJson('/api/mobile/attendance/check-in', $payload)->assertCreated()
                ->assertJsonPath('data.result.status', 'late')->assertJsonPath('data.result.late_fine', 10000);
            $this->postJson('/api/mobile/attendance/check-in', $payload)->assertOk()
                ->assertJsonPath('data.result.already_recorded', true)->assertJsonPath('data.result.late_fine', 10000);
            $this->getJson('/api/mobile/attendance/records?month=2026-10')->assertOk()
                ->assertJsonPath('data.summary.total', 1)->assertJsonPath('data.summary.late_fine', 10000);
        }
    }

    public function test_lateness_does_not_reduce_kpi_attendance_actuals_or_scores(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $record = $this->checkIn('SAL-001', '2026-10-07 07:55:00');
        DB::table('kpi_template_metrics')->where('code', 'SAL-ATTENDANCE')->update(['default_target' => 10]);
        $this->postJson('/api/kpi-reviews/generate', ['month' => '2026-10', 'employee_type' => 'sales'])->assertOk();
        $resultId = DB::table('kpi_results')->where('employee_id', $record->employee_id)->value('id');
        $metricId = DB::table('kpi_template_metrics')->where('code', 'SAL-ATTENDANCE')->value('id');
        $before = DB::table('kpi_result_items')->where('kpi_result_id', $resultId)->where('kpi_template_metric_id', $metricId)->first();
        $record->update(['status' => 'late', 'late_minutes' => 120, 'late_fine' => 100000]);
        $this->postJson('/api/kpi-reviews/generate', ['month' => '2026-10', 'employee_type' => 'sales'])->assertOk();
        $after = DB::table('kpi_result_items')->where('kpi_result_id', $resultId)->where('kpi_template_metric_id', $metricId)->first();
        $this->assertNotNull($before);
        $this->assertGreaterThan(0, $before->weighted_score);
        $this->assertEquals(1, $after->actual_value);
        $this->assertEquals($before->actual_value, $after->actual_value);
        $this->assertEquals($before->weighted_score, $after->weighted_score);
    }
}
