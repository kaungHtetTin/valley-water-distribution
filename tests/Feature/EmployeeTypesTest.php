<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\EmployeeTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_nine_types_have_forms_and_matching_kpi_profiles_without_granting_department_access(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $meta = $this->getJson('/api/master-data/meta')->assertOk()->json('data.resources');
        $employees = collect($meta)->firstWhere('key', 'employees');
        $field = collect($employees['fields'])->firstWhere('name', 'employee_type');
        $this->assertSame(array_keys(EmployeeTypes::LABELS), $field['options']);
        $fields = collect($employees['fields']);
        $this->assertFalse($fields->contains('name', 'access_role'));
        foreach (['email', 'password', 'password_confirmation'] as $credential) {
            $this->assertSame(EmployeeTypes::LOGIN_TYPES, $fields->firstWhere('name', $credential)['show_when']);
        }
        foreach (EmployeeTypes::LABELS as $type => $label) {
            $credentials = in_array($type, EmployeeTypes::LOGIN_TYPES, true)
                ? ['email' => $type.'@types.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!']
                : [];
            $response = $this->postJson('/api/master-data/employees', [
                'name' => $label, 'employee_type' => $type, ...$credentials,
            ])->assertCreated()->assertJsonPath('data.item.employee_type', $type);
            $id = $response->json('data.item.id');
            $profile = DB::table('kpi_staff_profiles')->where('employee_id', $id)->first();
            $this->assertNotNull($profile, $type);
            $this->assertDatabaseHas('kpi_templates', ['id' => $profile->kpi_template_id, 'employee_type' => $type]);
            if (in_array($type, ['accountant', 'hr', 'manager', 'sale_helper', 'other', 'warehouse'], true)) {
                $this->assertDatabaseMissing('users', ['employee_id' => $id]);
                $this->postJson('/api/master-data/employees', [
                    'name' => 'Invalid login', 'employee_type' => $type,
                    'email' => 'blocked@types.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
                ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'password_confirmation']);
            }
        }
        $this->postJson('/api/master-data/employees', ['name' => 'Legacy', 'employee_type' => 'office'])
            ->assertUnprocessable()->assertJsonValidationErrors('employee_type');
        $company = Company::oldest('id')->firstOrFail();
        $salaries = array_intersect_key($company->default_base_salaries, EmployeeTypes::LABELS);
        $salaries['accountant'] = 650000;
        $this->putJson('/api/settings/company', ['name' => $company->name, 'default_base_salaries' => $salaries])
            ->assertOk()->assertJsonPath('data.company.default_base_salaries.accountant', 650000);
        $this->putJson('/api/settings/company', ['name' => $company->name, 'default_base_salaries' => array_replace($salaries, ['hr' => -1])])
            ->assertUnprocessable()->assertJsonValidationErrors('default_base_salaries.hr');
        foreach (['sale_helper', 'accountant', 'manager', 'hr', 'other'] as $type) {
            $this->getJson('/api/kpi-targets?employee_type='.$type)->assertOk();
            $this->postJson('/api/kpi-reviews/generate', ['month' => '2026-11', 'employee_type' => $type])->assertOk();
            $this->postJson('/api/payrolls/generate', ['month' => '2026-11', 'employee_type' => $type])->assertCreated();
            $this->getJson('/api/kpi-reports?month=2026-11&employee_type='.$type)->assertOk();
        }
    }

    public function test_non_login_employee_updates_preserve_separately_managed_office_accounts(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $this->actingAs($owner);
        $response = $this->postJson('/api/master-data/employees', [
            'name' => 'Accountant employee', 'employee_type' => 'accountant',
        ])->assertCreated();
        $id = $response->json('data.item.id');
        $office = User::create([
            'name' => 'Office account', 'email' => 'accountant@office.test',
            'password' => 'OriginalPassword123!', 'role' => 'Accountant', 'employee_id' => $id,
        ]);
        $originalPassword = $office->password;
        $this->putJson('/api/master-data/employees/'.$id, [
            'name' => 'Updated employee', 'employee_type' => 'accountant', 'base_salary' => 700000,
        ])->assertOk();
        $this->assertSame('Office account', $office->fresh()->name);
        $this->assertSame('accountant@office.test', $office->fresh()->email);
        $this->assertSame('Accountant', $office->fresh()->role);
        $this->assertSame($originalPassword, $office->fresh()->password);
        $this->putJson('/api/master-data/employees/'.$id, [
            'name' => 'Updated employee', 'employee_type' => 'accountant',
            'password' => 'ChangedPassword123!', 'password_confirmation' => 'ChangedPassword123!',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame($originalPassword, $office->fresh()->password);
    }

    public static function existingTemplates(): array
    {
        return ['new templates' => [false], 'existing templates' => [true]];
    }

    #[DataProvider('existingTemplates')]
    public function test_migration_classifies_office_staff_and_preserves_salary_permissions_and_kpi_targets(bool $existingTemplates): void
    {
        $this->seed();
        // Recreate pre-migration office templates and staff against the current schema.
        if (! $existingTemplates) {
            DB::table('kpi_templates')->whereIn('code', ['ACCOUNTANT-V1', 'MANAGER-V1', 'HR-V1', 'SALE-HELPER-V1'])->delete();
        }
        $templateId = DB::table('kpi_templates')->where('code', 'OFFICE-STAFF-V1')->value('id');
        DB::table('kpi_templates')->where('id', $templateId)->update(['employee_type' => 'office']);
        $metricId = DB::table('kpi_template_metrics')->where('kpi_template_id', $templateId)->value('id');
        foreach (['Accountant' => 'accountant', 'HR' => 'hr', 'Finance Manager' => 'other', 'Office Staff' => 'other'] as $role => $type) {
            $id = DB::table('employees')->insertGetId([
                'code' => 'OLD-'.str_replace(' ', '-', $role), 'name' => $role,
                'employee_type' => 'office', 'is_active' => true,
            ]);
            User::create(['name' => $role, 'email' => $type.$id.'@legacy.test', 'password' => 'password', 'role' => $role, 'employee_id' => $id]);
            $profileId = DB::table('kpi_staff_profiles')->insertGetId(['employee_id' => $id, 'kpi_template_id' => $templateId, 'target_bonus' => 70000]);
            DB::table('kpi_staff_target_items')->insert(['kpi_staff_profile_id' => $profileId, 'kpi_template_metric_id' => $metricId, 'target_value' => 29]);
        }
        $periodId = DB::table('kpi_periods')->insertGetId(['month' => '2026-11', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
        $reviewIds = [];
        foreach (['Accountant' => 'draft', 'HR' => 'approved'] as $role => $status) {
            $employeeId = DB::table('employees')->where('name', $role)->value('id');
            $reviewIds[$status] = DB::table('kpi_results')->insertGetId([
                'kpi_period_id' => $periodId, 'kpi_template_id' => $templateId,
                'employee_id' => $employeeId, 'status' => $status, 'overall_score' => 88, 'bonus_amount' => 61600,
            ]);
            DB::table('kpi_result_items')->insert(['kpi_result_id' => $reviewIds[$status], 'kpi_template_metric_id' => $metricId, 'target_value' => 29, 'actual_value' => 27]);
        }
        $migration = require database_path('migrations/2026_11_03_000001_define_employee_types.php');
        $migration->up();
        $this->assertDatabaseHas('kpi_results', ['id' => $reviewIds['approved'], 'status' => 'approved', 'kpi_template_id' => $templateId, 'overall_score' => 88, 'bonus_amount' => 61600]);
        $this->assertDatabaseHas('kpi_result_items', ['kpi_result_id' => $reviewIds['approved'], 'kpi_template_metric_id' => $metricId, 'actual_value' => 27]);
        $draftTemplateId = DB::table('kpi_templates')->where('code', 'ACCOUNTANT-V1')->value('id');
        $draftMetricId = DB::table('kpi_template_metrics')->where('kpi_template_id', $draftTemplateId)->where('code', 'ACCOUNTANT-OFF-ATTENDANCE')->value('id');
        $this->assertDatabaseHas('kpi_results', ['id' => $reviewIds['draft'], 'kpi_template_id' => $draftTemplateId]);
        $this->assertDatabaseHas('kpi_result_items', ['kpi_result_id' => $reviewIds['draft'], 'kpi_template_metric_id' => $draftMetricId, 'target_value' => 29, 'actual_value' => 27]);
        foreach (['Accountant' => 'accountant', 'HR' => 'hr', 'Finance Manager' => 'other', 'Office Staff' => 'other'] as $role => $type) {
            $employee = DB::table('employees')->where('name', $role)->first();
            $this->assertSame($type, $employee->employee_type);
            $this->assertEquals(450000, $employee->base_salary);
            $this->assertDatabaseHas('users', ['employee_id' => $employee->id, 'role' => $role]);
            $profile = DB::table('kpi_staff_profiles')->where('employee_id', $employee->id)->first();
            $this->assertEquals(70000, $profile->target_bonus);
            $this->assertDatabaseHas('kpi_templates', ['id' => $profile->kpi_template_id, 'employee_type' => $type]);
            $this->assertDatabaseHas('kpi_staff_target_items', ['kpi_staff_profile_id' => $profile->id, 'target_value' => 29]);
        }
    }

    public function test_migration_reuses_existing_helper_template_without_overwriting_settings_or_duplicating_children(): void
    {
        $this->seed();
        $templateId = DB::table('kpi_templates')->where('code', 'SALE-HELPER-V1')->value('id');
        DB::table('kpi_templates')->where('id', $templateId)->update(['name' => 'Custom helper', 'target_bonus' => 85000, 'is_active' => false]);
        $metricId = DB::table('kpi_template_metrics')->where('kpi_template_id', $templateId)->value('id');
        DB::table('kpi_template_metrics')->where('id', $metricId)->update(['default_target' => 42]);
        DB::table('kpi_bonus_rules')->where('kpi_template_id', $templateId)->update(['payout_percent' => 65]);
        $before = [];
        foreach (['kpi_templates', 'kpi_template_metrics', 'kpi_bonus_rules'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        $migration = require database_path('migrations/2026_11_03_000001_define_employee_types.php');
        $migration->up();
        $migration->up();

        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
    }
}
