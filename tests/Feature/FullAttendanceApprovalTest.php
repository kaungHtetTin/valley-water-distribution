<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AttendanceCheckIn;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FullAttendanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function record(string $status = 'accepted')
    {
        $employee = DB::table('employees')->where('code', 'DRV-001')->first();
        return AttendanceCheckIn::record([
            'employee_id' => $employee->id, 'entered_employee_code' => $employee->code,
            'attendance_location_id' => DB::table('attendance_locations')->value('id'),
            'attendance_at' => Carbon::parse('2026-10-07 08:10:00', 'Asia/Yangon'),
            'status' => $status, 'rejection_reason' => $status === 'rejected' ? 'gps_denied' : null,
        ]);
    }

    public function test_owner_approval_clears_fine_and_refreshes_existing_payroll_draft(): void
    {
        $this->seed();
        $record = $this->record();
        $owner = User::where('role', 'Owner')->firstOrFail();
        $this->actingAs($owner);
        $payroll = $this->postJson('/api/payrolls/generate', ['month' => '2026-10', 'employee_type' => 'driver'])
            ->assertCreated();
        $payrollId = DB::table('payrolls')->value('id');
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payrollId, 'other_deduction' => 7500]);
        $this->postJson('/api/attendance/records/'.$record->id.'/approve-full')->assertOk()
            ->assertJsonPath('data.record.status', 'accepted');
        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id, 'late_minutes' => 0, 'late_fine' => 0,
            'original_late_minutes' => 10, 'waived_late_fine' => 7500,
            'full_attendance_approved_by' => $owner->id,
        ]);
        $this->getJson('/api/attendance/summary?month=2026-10&search=DRV-001')->assertOk()
            ->assertJsonPath('data.totals.late_count', 0)->assertJsonPath('data.totals.late_fine', 0);
        $this->getJson('/api/payrolls/'.$payrollId)->assertOk();
        $this->assertDatabaseHas('payroll_items', ['payroll_id' => $payrollId, 'other_deduction' => 0]);
        $this->actingAs(User::where('role', 'Driver')->firstOrFail());
        $this->getJson('/api/mobile/attendance/records?month=2026-10')->assertOk()
            ->assertJsonPath('data.summary.late_count', 0)->assertJsonPath('data.summary.late_fine', 0);
    }

    public function test_finance_manager_can_approve_but_cannot_replace_original_approver(): void
    {
        $this->seed();
        $record = $this->record();
        $manager = User::factory()->create(['role' => 'Finance Manager']);
        $this->actingAs($manager);
        $url = '/api/attendance/records/'.$record->id.'/approve-full';
        $this->postJson($url)->assertOk();
        $this->actingAs(User::where('role', 'Owner')->firstOrFail());
        $this->postJson($url)->assertOk();
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'full_attendance_approved_by' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $manager->id, 'path' => '/api/attendance/records/{id}/approve-full']);
    }

    public function test_lower_roles_are_denied_even_if_the_permission_is_assigned_and_rejected_records_stay_rejected(): void
    {
        $this->seed();
        $record = $this->record('rejected');
        $permissionId = DB::table('permissions')->where('name', 'office.attendance.approve-full')->value('id');
        foreach (['HR', 'Office Staff', 'Accountant', 'Sales Supervisor', 'Driver', 'Sales Representative', 'Customer'] as $role) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => DB::table('roles')->where('name', $role)->value('id'), 'permission_id' => $permissionId,
            ]);
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->postJson('/api/attendance/records/'.$record->id.'/approve-full')->assertForbidden();
        }
        $this->actingAs(User::where('role', 'Owner')->firstOrFail());
        $this->postJson('/api/attendance/records/'.$record->id.'/approve-full')->assertStatus(422);
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'status' => 'rejected', 'full_attendance_approved_at' => null]);
    }
}
