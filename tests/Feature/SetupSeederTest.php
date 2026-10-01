<?php

namespace Tests\Feature;

use Database\Seeders\SetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetupSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_seeder_creates_only_administrator_and_initial_configuration(): void
    {
        $this->seed(SetupSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['role' => 'Owner']);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('roles', 9);
        foreach (['Owner', 'Office Staff', 'HR', 'Accountant', 'Finance Manager', 'Customer', 'Sales Representative', 'Sales Supervisor', 'Driver'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
        $this->assertGreaterThan(0, DB::table('permissions')->count());

        foreach ([
            'areas', 'warehouses', 'employees', 'customers', 'orders', 'invoices',
            'deliveries', 'attendance_records', 'collections', 'expenses', 'payrolls',
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_setup_seeder_can_be_rerun_without_resetting_owner_or_custom_role_permissions(): void
    {
        $this->seed(SetupSeeder::class);

        $owner = DB::table('users')->where('role', 'Owner')->first();
        $officeRoleId = DB::table('roles')->where('name', 'Office Staff')->value('id');
        $removedPermissionId = DB::table('permissions')->where('name', 'office.orders.manage')->value('id');
        DB::table('permission_role')
            ->where('role_id', $officeRoleId)
            ->where('permission_id', $removedPermissionId)
            ->delete();
        $customPermissionCount = DB::table('permission_role')->where('role_id', $officeRoleId)->count();

        config([
            'valley.initial_admin.email' => null,
            'valley.initial_admin.password' => null,
        ]);
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        try {
            app(SetupSeeder::class)->run();
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($owner->password, DB::table('users')->where('id', $owner->id)->value('password'));
        $this->assertTrue(Hash::check('password', $owner->password));
        $this->assertSame($customPermissionCount, DB::table('permission_role')->where('role_id', $officeRoleId)->count());
        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $officeRoleId,
            'permission_id' => $removedPermissionId,
        ]);
    }

    public function test_readiness_accepts_a_provisioned_owner_without_the_bootstrap_secret_and_rejects_wildcard_proxies(): void
    {
        $this->seed(SetupSeeder::class);
        config([
            'valley.initial_admin.email' => null,
            'valley.initial_admin.password' => null,
            'trustedproxy.proxies' => '*',
        ]);

        $this->assertSame(1, Artisan::call('valley:production-check', ['--skip-assets' => true]));
        $output = Artisan::output();
        $this->assertMatchesRegularExpression('/Trusted proxy scope\s+\| FAIL/', $output);
        $this->assertMatchesRegularExpression('/Initial administrator email\s+\| PASS/', $output);
        $this->assertMatchesRegularExpression('/Initial administrator password\s+\| PASS/', $output);
    }
}
