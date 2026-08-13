<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $permissionGroups = [
            'office.dashboard' => ['view'],
            'office.users' => ['view', 'create', 'edit', 'delete'],
            'office.roles' => ['view', 'create', 'edit', 'delete'],
            'office.customers' => ['view'],
            'office.orders' => ['view'],
            'office.inventory' => ['view'],
            'office.deliveries' => ['view'],
            'office.settings' => ['view'],
            'client.home' => ['view'],
            'client.orders' => ['view', 'create'],
            'client.deliveries' => ['view'],
            'client.profile' => ['view'],
            'sales.home' => ['view'],
            'sales.route' => ['view'],
            'sales.customers' => ['view', 'create'],
            'sales.collections' => ['view'],
            'driver.home' => ['view'],
            'driver.load' => ['view'],
            'driver.route' => ['view'],
            'driver.confirm' => ['view', 'update'],
        ];

        foreach ($permissionGroups as $group => $actions) {
            foreach ($actions as $action) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => "{$group}.{$action}"],
                    ['group' => $group, 'guard_name' => 'web', 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        $roles = [
            'Owner' => 'Full system access for business owner.',
            'Office Staff' => 'Office dashboard and operational data entry.',
            'Customer' => 'Client mobile app access.',
            'Sales Representative' => 'Sales mobile app access.',
            'Driver' => 'Driver mobile delivery app access.',
        ];

        foreach ($roles as $role => $description) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role],
                ['description' => $description, 'guard_name' => 'web', 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $rolePermissions = [
            'Owner' => DB::table('permissions')->pluck('name')->all(),
            'Office Staff' => [
                'office.dashboard.view',
                'office.customers.view',
                'office.orders.view',
                'office.inventory.view',
                'office.deliveries.view',
            ],
            'Customer' => [
                'client.home.view',
                'client.orders.view',
                'client.orders.create',
                'client.deliveries.view',
                'client.profile.view',
            ],
            'Sales Representative' => [
                'sales.home.view',
                'sales.route.view',
                'sales.customers.view',
                'sales.customers.create',
                'sales.collections.view',
            ],
            'Driver' => [
                'driver.home.view',
                'driver.load.view',
                'driver.route.view',
                'driver.confirm.view',
                'driver.confirm.update',
            ],
        ];

        foreach ($rolePermissions as $role => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $role)->value('id');
            $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');

            DB::table('permission_role')->where('role_id', $roleId)->delete();

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $users = [
            ['name' => 'Valley Owner', 'email' => 'owner@valley.test', 'phone' => '09 420 000 001', 'role' => 'Owner'],
            ['name' => 'Office Demo', 'email' => 'office@valley.test', 'phone' => '09 420 000 002', 'role' => 'Office Staff'],
            ['name' => 'Customer Demo', 'email' => 'client@valley.test', 'phone' => '09 420 000 003', 'role' => 'Customer'],
            ['name' => 'Sales Demo', 'email' => 'sales@valley.test', 'phone' => '09 420 000 004', 'role' => 'Sales Representative'],
            ['name' => 'Driver Demo', 'email' => 'driver@valley.test', 'phone' => '09 420 000 005', 'role' => 'Driver'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user + ['locale' => 'en', 'password' => Hash::make('password')]
            );
        }
    }
}
