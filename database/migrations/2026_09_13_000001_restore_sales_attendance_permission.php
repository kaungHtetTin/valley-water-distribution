<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'sales.attendance.view'],
            ['group' => 'sales.attendance', 'guard_name' => 'web', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        );

        $roleId = DB::table('roles')->where('name', 'Sales Representative')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'sales.attendance.view')->value('id');
        if ($roleId && $permissionId) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'sales.attendance.view')->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
