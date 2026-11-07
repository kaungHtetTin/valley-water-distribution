<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppAccess;
use App\Support\EmployeeTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeRoleAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_roles_have_matching_names_and_scoped_permissions(): void
    {
        $this->seed();
        foreach (EmployeeTypes::ROLES as $type => $role) {
            $this->assertSame(EmployeeTypes::LABELS[$type], $role);
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
        $this->assertSame([], AppAccess::allowedAppsForRole('Sale Helper'));
        $this->assertSame([], AppAccess::permissionsForRole('Sale Helper'));
        $this->assertSame(['office'], AppAccess::allowedAppsForRole('Storekeeper'));
        $this->assertSame(['office.inventory.manage', 'office.inventory.view'], AppAccess::permissionsForRole('Storekeeper'));
        $this->assertContains('office.payroll.drafts.approve', AppAccess::permissionsForRole('Manager'));
        $this->assertNotContains('office.payroll.drafts.pay', AppAccess::permissionsForRole('Manager'));
        $this->assertContains('office.payroll.drafts.pay', AppAccess::permissionsForRole('Accountant'));
        $this->assertNotContains('office.payroll.drafts.approve', AppAccess::permissionsForRole('Accountant'));
        $this->assertNotContains('office.access.roles.manage', AppAccess::permissionsForRole('HR'));
    }

    public function test_renamed_roles_can_sign_in_to_their_app_and_cannot_use_another_app(): void
    {
        $this->seed();
        foreach (['Sale Representative' => 'sales', 'Sale Manager' => 'supervisor', 'Manager' => 'office', 'Other' => 'office', 'Storekeeper' => 'office'] as $role => $app) {
            $user = User::factory()->create(['role' => $role]);
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password', 'app' => $app])
                ->assertOk()->assertJsonPath('data.user.role', $role);
            $this->postJson('/api/auth/logout')->assertOk();
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password', 'app' => 'client'])->assertForbidden();
        }
    }

    public function test_migration_renames_existing_accounts_and_preserves_customized_permissions_and_role_ids(): void
    {
        $this->seed();
        $before = [];
        foreach (['Sales Representative' => 'Sale Representative', 'Sales Supervisor' => 'Sale Manager', 'Finance Manager' => 'Manager', 'Office Staff' => 'Other'] as $old => $new) {
            $id = DB::table('roles')->where('name', $new)->value('id');
            DB::table('roles')->where('id', $id)->update(['name' => $old]);
            // Simulate a restricted, customized production role.
            DB::table('permission_role')->where('role_id', $id)->where('permission_id', DB::table('permission_role')->where('role_id', $id)->min('permission_id'))->delete();
            $user = User::factory()->create(['role' => $old]);
            $before[$new] = [$id, $user, AppAccess::permissionsForRole($old), AppAccess::allowedAppsForRole($old)];
        }
        DB::table('roles')->whereIn('name', ['Storekeeper', 'Sale Helper'])->delete();
        $migration = require database_path('migrations/2026_11_07_000001_align_roles_with_employee_types.php');
        $migration->up();
        $migration->up();
        foreach ($before as $new => [$id, $user, $permissions, $apps]) {
            $this->assertDatabaseHas('roles', ['id' => $id, 'name' => $new]);
            $this->assertSame($permissions, AppAccess::permissionsForRole($new));
            $this->assertSame($apps, AppAccess::allowedAppsForRole($new));
            $this->assertSame($new, $user->fresh()->role);
            $this->assertSame($user->password, $user->fresh()->password);
        }
        $this->assertSame(['office.inventory.manage', 'office.inventory.view'], AppAccess::permissionsForRole('Storekeeper'));
        $this->assertSame([], AppAccess::allowedAppsForRole('Sale Helper'));
    }

    public function test_conflicting_roles_do_not_merge_permissions_or_partially_change_accounts(): void
    {
        $this->seed();
        DB::table('roles')->insert([
            'name' => 'Sales Representative', 'guard_name' => 'web', 'allowed_apps' => json_encode(['office']), 'is_active' => true,
        ]);
        $user = User::factory()->create(['role' => 'Sales Representative']);
        $migration = require database_path('migrations/2026_11_07_000001_align_roles_with_employee_types.php');
        try {
            $migration->up();
            $this->fail('Conflicting access settings must not be merged.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('different access settings', $exception->getMessage());
        }
        $this->assertSame('Sales Representative', $user->fresh()->role);
        $this->assertSame(['sales'], AppAccess::allowedAppsForRole('Sale Representative'));
    }

    public function test_equivalent_legacy_and_new_roles_can_be_consolidated_and_rerun(): void
    {
        $this->seed();
        $target = DB::table('roles')->where('name', 'Sale Manager')->first();
        $values = (array) $target;
        unset($values['id']);
        $values['name'] = 'Sales Supervisor';
        $sourceId = DB::table('roles')->insertGetId($values);
        foreach (DB::table('permission_role')->where('role_id', $target->id)->pluck('permission_id') as $permissionId) {
            DB::table('permission_role')->insert(['role_id' => $sourceId, 'permission_id' => $permissionId]);
        }
        $user = User::factory()->create(['role' => 'Sales Supervisor']);
        $permissions = AppAccess::permissionsForRole('Sale Manager');
        $migration = require database_path('migrations/2026_11_07_000001_align_roles_with_employee_types.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseMissing('roles', ['id' => $sourceId]);
        $this->assertSame('Sale Manager', $user->fresh()->role);
        $this->assertSame($permissions, AppAccess::permissionsForRole('Sale Manager'));
    }

    public function test_no_access_role_can_be_saved_without_granting_app_access(): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'Owner')->firstOrFail());
        $id = DB::table('roles')->where('name', 'Sale Helper')->value('id');
        $this->putJson('/api/master-data/roles/'.$id, [
            'name' => 'Sale Helper', 'guard_name' => 'web', 'allowed_apps' => [], 'permission_ids' => [], 'is_active' => true,
        ])->assertOk();
        $helper = User::factory()->create(['role' => 'Sale Helper']);
        $this->assertFalse(AppAccess::canAccess($helper, 'office'));
        $this->assertFalse(AppAccess::canAccess($helper, 'sales'));
    }
}
