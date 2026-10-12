<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('permissions')->updateOrInsert(['name' => 'supervisor.orders.view'], [
                'group' => 'supervisor.orders', 'guard_name' => 'web', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $roleId = DB::table('roles')->where('name', 'Sales Supervisor')->value('id');
            if ($roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => DB::table('permissions')->where('name', 'supervisor.orders.view')->value('id'),
                ]);
            }
        });
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'supervisor.orders.view')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
