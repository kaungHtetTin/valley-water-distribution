<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SetupSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'office.dashboard' => ['view'],
            'office.users' => ['view', 'create', 'edit', 'delete'],
            'office.roles' => ['view', 'create', 'edit', 'delete'],
            'office.customers' => ['view'],
            'office.orders' => ['view', 'manage'],
            'office.invoices' => ['view', 'manage'],
            'office.inventory' => ['view', 'manage'],
            'office.deliveries' => ['view', 'manage'],
            'office.settings' => ['view'],
            'office.master-data' => ['view', 'manage'],
            'office.employees' => ['view', 'manage'],
            'office.suppliers' => ['view', 'manage'],
            'office.access.users' => ['manage'],
            'office.access.roles' => ['manage'],
            'office.attendance' => ['view', 'manage'],
            'office.payroll' => ['view', 'manage'],
            'office.payroll.drafts' => ['prepare', 'approve', 'pay'],
            'office.payroll.adjustments' => ['manage'],
            'office.kpi' => ['view', 'manage', 'approve'],
            'office.finance' => ['view', 'manage'],
            'office.finance.collections' => ['view', 'create', 'review', 'receive'],
            'office.finance.receivables' => ['view'],
            'office.finance.expenses' => ['view', 'create', 'review'],
            'office.finance.suppliers' => ['view', 'pay', 'adjust'],
            'office.finance.books' => ['view'],
            'office.finance.profit-loss' => ['view'],
            'office.reports.operations' => ['view'],
            'office.vehicle-costs' => ['view', 'manage'],
            'office.uat' => ['view', 'manage'],
            'client.home' => ['view'],
            'client.orders' => ['view', 'create'],
            'client.deliveries' => ['view'],
            'client.profile' => ['view'],
            'client.finance' => ['view'],
            'sales.home' => ['view'],
            'sales.profile' => ['view'],
            'sales.route' => ['view'],
            'sales.orders' => ['view', 'create'],
            'sales.customers' => ['view', 'create'],
            'sales.attendance' => ['view'],
            'sales.payroll' => ['view'],
            'sales.finance' => ['view'],
            'sales.expenses' => ['view', 'create'],
            'driver.home' => ['view'],
            'driver.profile' => ['view'],
            'driver.vehicle' => ['view'],
            'driver.load' => ['view'],
            'driver.route' => ['view', 'update'],
            'driver.confirm' => ['view', 'update'],
            'driver.attendance' => ['view'],
            'driver.payroll' => ['view'],
            'driver.finance' => ['view'],
            'driver.collections' => ['view', 'create'],
            'driver.expenses' => ['view', 'create'],
            'driver.vehicle-costs' => ['view', 'create'],
        ];

        foreach ($permissions as $group => $actions) {
            foreach ($actions as $action) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => "{$group}.{$action}"],
                    ['group' => $group, 'guard_name' => 'web', 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        $roles = [
            'Owner' => ['description' => 'Full system access for the business owner.', 'apps' => ['office']],
            'Office Staff' => ['description' => 'Office dashboard and operational data entry.', 'apps' => ['office']],
            'HR' => ['description' => 'Employee records, attendance, KPI preparation, and payroll drafts.', 'apps' => ['office']],
            'Accountant' => ['description' => 'Financial records, supplier payments, cash handovers, and payroll payment.', 'apps' => ['office']],
            'Finance Manager' => ['description' => 'Financial review and approval, KPI approval, payroll approval, and profit and loss.', 'apps' => ['office']],
            'Customer' => ['description' => 'Client mobile app access.', 'apps' => ['client']],
            'Sales Representative' => ['description' => 'Sales mobile app access.', 'apps' => ['sales']],
            'Sales Supervisor' => ['description' => 'Office access to sales dashboards, orders, and customers, without selling permissions.', 'apps' => ['office']],
            'Driver' => ['description' => 'Driver mobile delivery app access.', 'apps' => ['driver']],
        ];

        foreach ($roles as $name => $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name],
                [
                    'description' => $role['description'],
                    'guard_name' => 'web',
                    'allowed_apps' => json_encode($role['apps']),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $rolePermissions = [
            'Owner' => DB::table('permissions')->pluck('name')->all(),
            'Office Staff' => [
                'office.dashboard.view', 'office.customers.view', 'office.orders.view', 'office.orders.manage',
                'office.invoices.view', 'office.invoices.manage', 'office.inventory.view', 'office.inventory.manage',
                'office.deliveries.view', 'office.deliveries.manage', 'office.master-data.view', 'office.master-data.manage',
                'office.vehicle-costs.view', 'office.vehicle-costs.manage',
            ],
            'HR' => [
                'office.employees.view', 'office.employees.manage',
                'office.attendance.view', 'office.attendance.manage',
                'office.payroll.view', 'office.payroll.drafts.prepare', 'office.payroll.adjustments.manage',
                'office.kpi.view', 'office.kpi.manage',
            ],
            'Accountant' => [
                'office.suppliers.view', 'office.finance.collections.view', 'office.finance.collections.create',
                'office.finance.collections.receive', 'office.finance.receivables.view',
                'office.finance.expenses.view', 'office.finance.expenses.create',
                'office.finance.suppliers.view', 'office.finance.suppliers.pay',
                'office.finance.books.view', 'office.payroll.view', 'office.payroll.drafts.pay',
            ],
            'Finance Manager' => [
                'office.finance.collections.view', 'office.finance.collections.review',
                'office.finance.receivables.view', 'office.finance.expenses.view', 'office.finance.expenses.review',
                'office.finance.suppliers.view', 'office.finance.books.view', 'office.finance.profit-loss.view',
                'office.payroll.view', 'office.payroll.drafts.approve', 'office.kpi.view', 'office.kpi.approve',
                'office.reports.operations.view',
            ],
            'Customer' => [
                'client.home.view', 'client.orders.view', 'client.orders.create', 'client.deliveries.view',
                'client.profile.view', 'client.finance.view',
            ],
            'Sales Representative' => [
                'sales.home.view', 'sales.profile.view', 'sales.orders.view', 'sales.orders.create',
                'sales.customers.view', 'sales.customers.create', 'sales.attendance.view', 'sales.payroll.view',
                'sales.finance.view', 'sales.expenses.view', 'sales.expenses.create',
            ],
            'Sales Supervisor' => [
                'office.dashboard.view', 'office.customers.view', 'office.orders.view',
            ],
            'Driver' => [
                'driver.home.view', 'driver.profile.view', 'driver.vehicle.view', 'driver.load.view',
                'driver.route.view', 'driver.route.update', 'driver.confirm.view', 'driver.confirm.update',
                'driver.attendance.view', 'driver.payroll.view', 'driver.finance.view', 'driver.collections.view',
                'driver.collections.create', 'driver.expenses.view', 'driver.expenses.create',
                'driver.vehicle-costs.view', 'driver.vehicle-costs.create',
            ],
        ];

        foreach ($rolePermissions as $roleName => $names) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            $permissionIds = DB::table('permissions')->whereIn('name', $names)->pluck('id');

            DB::table('permission_role')->where('role_id', $roleId)->delete();
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }

        if (! DB::table('companies')->where('code', 'VALLEY')->exists()) {
            DB::table('companies')->insert([
                'code' => 'VALLEY',
                'name' => 'Valley Water Distribution',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $email = (string) env('VALLEY_ADMIN_EMAIL', '');
        $password = (string) env('VALLEY_ADMIN_PASSWORD', '');
        $localDefault = app()->environment(['local', 'testing']);

        if ($email === '' && $localDefault) {
            $email = 'owner@valley.test';
        }
        if ($password === '' && $localDefault) {
            $password = 'password';
        }
        if ($email === '' || $password === '') {
            throw new RuntimeException('Set VALLEY_ADMIN_EMAIL and VALLEY_ADMIN_PASSWORD before seeding the initial administrator.');
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('VALLEY_ADMIN_NAME', 'Administrator'),
                'role' => 'Owner',
                'locale' => 'en',
                'password' => Hash::make($password),
            ]
        );
    }
}
