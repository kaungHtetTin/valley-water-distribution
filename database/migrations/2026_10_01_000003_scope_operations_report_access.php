<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(['name' => 'office.reports.operations.view'], [
            'group' => 'office.reports.operations',
            'guard_name' => 'web',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('name', 'office.reports.operations.view')->value('id');
        foreach (['Owner', 'Finance Manager'] as $name) {
            $roleId = DB::table('roles')->where('name', $name)->value('id');
            if ($roleId) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        // Preserve role assignments and access history on rollback.
    }
};
