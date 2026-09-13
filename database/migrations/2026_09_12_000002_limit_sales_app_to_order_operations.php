<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REMOVED_PERMISSIONS = [
        'sales.deliveries.view',
        'sales.finance.view',
        'sales.collections.view',
        'sales.collections.create',
        'sales.expenses.view',
        'sales.expenses.create',
        'sales.attendance.view',
        'sales.payroll.view',
    ];

    public function up(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', self::REMOVED_PERMISSIONS)->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    public function down(): void
    {
        $now = now();

        foreach (self::REMOVED_PERMISSIONS as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['group' => str($name)->beforeLast('.')->toString(), 'guard_name' => 'web', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $roleId = DB::table('roles')->where('name', 'Sales Representative')->value('id');
        if (! $roleId) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('name', self::REMOVED_PERMISSIONS)->pluck('id') as $permissionId) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }
};
