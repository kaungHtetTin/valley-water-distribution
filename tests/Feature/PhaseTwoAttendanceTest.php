<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseTwoAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_manage_attendance_locations_and_rotate_public_token()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $created = $this->postJson('/api/attendance/locations', [
            'code' => 'ATT-WH',
            'name' => 'Taunggyi Warehouse Gate',
            'address' => 'Industrial Zone, Taunggyi',
            'latitude' => 20.7900000,
            'longitude' => 97.0380000,
            'allowed_radius_m' => 20,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.location.allowed_radius_m', 20)
            ->assertJsonPath('data.location.is_active', true);

        $locationId = $created->json('data.location.id');
        $originalToken = $created->json('data.location.public_token');

        $rotated = $this->postJson("/api/attendance/locations/{$locationId}/rotate-token")
            ->assertOk()
            ->json('data.location.public_token');

        $this->assertNotSame($originalToken, $rotated);
        $this->getJson('/api/attendance/locations?search=Warehouse&is_active=1')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_attendance_location_api_requires_office_permission()
    {
        $this->seed();

        $this->getJson('/api/attendance/locations')->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail())
            ->getJson('/api/attendance/locations')
            ->assertForbidden();
    }

    public function test_public_attendance_accepts_employee_inside_allowed_radius()
    {
        $this->seed();
        $token = DB::table('attendance_locations')->where('code', 'ATT-OFFICE')->value('public_token');

        $this->getJson("/api/public/attendance/{$token}")
            ->assertOk()
            ->assertJsonPath('data.location.allowed_radius_m', 20);

        $this->postJson("/api/public/attendance/{$token}", [
            'employee_code' => 'SAL-001',
            'latitude' => 20.7892000,
            'longitude' => 97.0378000,
            'gps_denied' => false,
        ])->assertCreated()
            ->assertJsonPath('data.result.status', 'accepted')
            ->assertJsonPath('data.result.rejection_reason', null);
    }

    public function test_public_attendance_records_gps_and_identity_rejections()
    {
        $this->seed();
        $token = DB::table('attendance_locations')->where('code', 'ATT-OFFICE')->value('public_token');

        $this->postJson("/api/public/attendance/{$token}", [
            'employee_code' => 'SAL-001',
            'gps_denied' => true,
        ])->assertCreated()
            ->assertJsonPath('data.result.status', 'rejected')
            ->assertJsonPath('data.result.rejection_reason', 'gps_denied');

        $this->postJson("/api/public/attendance/{$token}", [
            'employee_code' => 'UNKNOWN',
            'latitude' => 20.7892000,
            'longitude' => 97.0378000,
        ])->assertCreated()
            ->assertJsonPath('data.result.rejection_reason', 'invalid_employee_id');
    }

    public function test_public_attendance_rejects_outside_radius_and_inactive_token()
    {
        $this->seed();
        $location = DB::table('attendance_locations')->where('code', 'ATT-OFFICE')->first();

        $this->postJson("/api/public/attendance/{$location->public_token}", [
            'employee_code' => 'DRV-001',
            'latitude' => 20.7902000,
            'longitude' => 97.0378000,
        ])->assertCreated()
            ->assertJsonPath('data.result.status', 'rejected')
            ->assertJsonPath('data.result.rejection_reason', 'outside_allowed_radius');

        DB::table('attendance_locations')->where('id', $location->id)->update(['is_active' => false]);

        $this->postJson("/api/public/attendance/{$location->public_token}", [
            'employee_code' => 'DRV-001',
            'gps_denied' => true,
        ])->assertCreated()
            ->assertJsonPath('data.result.rejection_reason', 'inactive_qr_token');
    }

    public function test_office_can_filter_attendance_records()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $this->getJson('/api/attendance/records?date=2026-08-17&status=accepted')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.employee_code', 'SAL-001')
            ->assertJsonPath('data.items.0.location_name', 'Taunggyi Office');

        $this->getJson('/api/attendance/records?search=SAL-001')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_only_driver_can_view_their_own_mobile_attendance_history()
    {
        $this->seed();

        $this->getJson('/api/mobile/attendance/records')->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail())
            ->getJson('/api/mobile/attendance/records')
            ->assertForbidden();

        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail())
            ->getJson('/api/mobile/attendance/records')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.rejected', 1)
            ->assertJsonPath('data.items.0.entered_employee_code', 'DRV-001')
            ->assertJsonPath('data.items.0.rejection_reason', 'gps_denied');
    }

    public function test_office_can_view_attendance_summary_for_payroll_preparation()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $this->getJson('/api/attendance/summary?month=2026-08&employee_type=sales&search=SAL-001')
            ->assertOk()
            ->assertJsonPath('data.period.start', '2026-08-01')
            ->assertJsonPath('data.period.end', '2026-08-31')
            ->assertJsonPath('data.totals.records', 1)
            ->assertJsonPath('data.totals.accepted', 1)
            ->assertJsonPath('data.items.0.employee_code', 'SAL-001')
            ->assertJsonPath('data.items.0.accepted_count', 1);

        $this->getJson('/api/attendance/summary?date_from=2026-08-17&date_to=2026-08-17&search=DRV-001')
            ->assertOk()
            ->assertJsonPath('data.totals.records', 1)
            ->assertJsonPath('data.totals.rejected', 1)
            ->assertJsonPath('data.items.0.employee_code', 'DRV-001')
            ->assertJsonPath('data.items.0.gps_denied_count', 1);
    }
}
