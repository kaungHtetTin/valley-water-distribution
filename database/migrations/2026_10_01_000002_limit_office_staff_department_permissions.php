<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'Office Staff')->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')->whereIn('name', [
            'office.attendance.view', 'office.attendance.manage',
            'office.payroll.view', 'office.payroll.manage',
            'office.finance.view', 'office.finance.manage',
            'office.uat.view', 'office.uat.manage',
        ])->pluck('id');

        DB::table('permission_role')->where('role_id', $roleId)->whereIn('permission_id', $permissionIds)->delete();
    }

    public function down(): void
    {
        // Do not silently restore broad access when application code is rolled back.
    }
};
