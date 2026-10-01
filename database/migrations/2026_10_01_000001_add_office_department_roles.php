<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'HR' => [
                'office.employees.view', 'office.employees.manage', 'office.attendance.view', 'office.attendance.manage',
                'office.payroll.view', 'office.payroll.drafts.prepare', 'office.payroll.adjustments.manage',
                'office.kpi.view', 'office.kpi.manage',
            ],
            'Accountant' => [
                'office.suppliers.view', 'office.finance.collections.view', 'office.finance.collections.create',
                'office.finance.collections.receive', 'office.finance.receivables.view',
                'office.finance.expenses.view', 'office.finance.expenses.create',
                'office.finance.suppliers.view', 'office.finance.suppliers.pay', 'office.finance.books.view',
                'office.payroll.view', 'office.payroll.drafts.pay',
            ],
            'Finance Manager' => [
                'office.finance.collections.view', 'office.finance.collections.review',
                'office.finance.receivables.view', 'office.finance.expenses.view', 'office.finance.expenses.review',
                'office.finance.suppliers.view', 'office.finance.books.view', 'office.finance.profit-loss.view',
                'office.payroll.view', 'office.payroll.drafts.approve', 'office.kpi.view', 'office.kpi.approve',
            ],
        ];

        $catalog = array_unique(array_merge(
            array_merge(...array_values($defaults)),
            [
                'office.suppliers.manage', 'office.access.users.manage', 'office.access.roles.manage',
                'office.payroll.drafts.prepare', 'office.payroll.drafts.approve', 'office.payroll.drafts.pay',
                'office.payroll.adjustments.manage', 'office.kpi.view', 'office.kpi.manage', 'office.kpi.approve',
                'office.finance.suppliers.adjust',
            ]
        ));
        foreach ($catalog as $name) {
            DB::table('permissions')->updateOrInsert(['name' => $name], [
                'group' => substr($name, 0, strrpos($name, '.')),
                'guard_name' => 'web',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        $ownerId = DB::table('roles')->where('name', 'Owner')->value('id');
        if ($ownerId) {
            foreach (DB::table('permissions')->whereIn('name', $catalog)->pluck('id') as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $ownerId, 'permission_id' => $permissionId]);
            }
        }

        foreach ($defaults as $name => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $name)->value('id');
            if ($roleId) {
                continue;
            }

            $roleId = DB::table('roles')->insertGetId([
                'name' => $name,
                'description' => match ($name) {
                    'HR' => 'Employee records, attendance, KPI preparation, and payroll drafts.',
                    'Accountant' => 'Financial records, supplier payments, cash handovers, and payroll payment.',
                    default => 'Financial review and approval, KPI approval, payroll approval, and profit and loss.',
                },
                'guard_name' => 'web',
                'allowed_apps' => json_encode(['office']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id') as $permissionId) {
                DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        // Keep assigned roles and permissions intact when rolling back application code.
    }
};
