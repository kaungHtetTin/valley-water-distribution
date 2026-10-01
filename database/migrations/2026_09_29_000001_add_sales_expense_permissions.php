<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $roleId = DB::table('roles')->where('name', 'Sales Representative')->value('id');

        $permissions = [
            'sales.finance.view' => 'sales.finance',
            'sales.expenses.view' => 'sales.expenses',
            'sales.expenses.create' => 'sales.expenses',
        ];

        foreach ($permissions as $name => $group) {

            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'group' => $group,
                    'guard_name' => 'web',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $permissionId = DB::table('permissions')->where('name', $name)->value('id');
            if ($roleId && $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['sales.finance.view', 'sales.expenses.view', 'sales.expenses.create'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
