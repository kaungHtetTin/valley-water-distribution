<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\AttendanceCheckIn;
use App\Support\SalaryDefaults;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalaryDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_can_be_read_updated_and_validated_only_by_authorized_staff(): void
    {
        $this->seed();
        $company = Company::oldest('id')->first();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $this->getJson('/api/settings/company')->assertOk()
            ->assertJsonPath('data.company.default_base_salaries', SalaryDefaults::AMOUNTS);
        $amounts = array_replace(SalaryDefaults::AMOUNTS, ['sales' => 500000.50, 'driver' => 0]);
        $this->putJson('/api/settings/company', ['name' => $company->name, 'default_base_salaries' => $amounts])
            ->assertOk()->assertJsonPath('data.company.default_base_salaries.sales', 500000.50)
            ->assertJsonPath('data.company.default_base_salaries.driver', 0);
        $this->putJson('/api/settings/company', ['name' => $company->name])->assertOk()
            ->assertJsonPath('data.company.default_base_salaries.sales', 500000.50);
        $this->putJson('/api/settings/company', ['name' => $company->name, 'default_base_salaries' => ['sales' => -1]])
            ->assertUnprocessable()->assertJsonValidationErrors(['default_base_salaries.sales', 'default_base_salaries.office']);
        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->putJson('/api/settings/company', ['name' => $company->name, 'default_base_salaries' => $amounts])->assertForbidden();
        $this->assertEquals(500000.50, $company->fresh()->default_base_salaries['sales']);
    }

    public function test_configured_defaults_feed_payroll_and_fines_but_employee_overrides_and_snapshots_take_priority(): void
    {
        $this->seed();
        $company = Company::oldest('id')->first();
        $company->update(['default_base_salaries' => array_replace(SalaryDefaults::AMOUNTS, ['sales' => 480000])]);
        $sales = DB::table('employees')->where('code', 'SAL-001')->first();
        DB::table('employees')->where('id', $sales->id)->update(['base_salary' => null]);
        $attributes = [
            'employee_id' => $sales->id, 'entered_employee_code' => $sales->code,
            'attendance_at' => Carbon::parse('2026-10-07 08:10:00', 'Asia/Yangon'), 'status' => 'accepted',
        ];
        $record = AttendanceCheckIn::record($attributes);
        $this->assertEquals(480000, $record->salary_snapshot);
        $this->assertEquals(10000, $record->late_fine);
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $this->postJson('/api/payrolls/generate', ['month' => '2026-10', 'employee_type' => 'sales'])->assertCreated();
        $this->assertDatabaseHas('payroll_items', ['employee_id' => $sales->id, 'base_salary' => 480000, 'net_pay' => 470000]);

        $company->update(['default_base_salaries' => array_replace(SalaryDefaults::AMOUNTS, ['sales' => 960000])]);
        DB::table('employees')->where('id', $sales->id)->update(['base_salary' => 240000]);
        $attributes['attendance_at'] = Carbon::parse('2026-10-08 08:10:00', 'Asia/Yangon');
        $override = AttendanceCheckIn::record($attributes);
        $this->assertEquals(5000, $override->late_fine);
        $this->assertEquals(10000, $record->fresh()->late_fine);
        $this->postJson('/api/payrolls/generate', ['month' => '2026-10', 'employee_type' => 'sales'])->assertCreated();
        $this->assertDatabaseHas('payroll_items', ['employee_id' => $sales->id, 'base_salary' => 240000, 'net_pay' => 225000]);

        DB::table('employees')->where('id', $sales->id)->update(['base_salary' => 0]);
        $attributes['attendance_at'] = Carbon::parse('2026-10-09 08:10:00', 'Asia/Yangon');
        $this->assertEquals(0, AttendanceCheckIn::record($attributes)->late_fine);
    }
}
