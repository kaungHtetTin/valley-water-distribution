<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $permissions = [
                'supervisor.home.view' => 'supervisor.home',
                'supervisor.team.view' => 'supervisor.team',
                'supervisor.profile.view' => 'supervisor.profile',
                'supervisor.attendance.view' => 'supervisor.attendance',
                'supervisor.kpi.view' => 'supervisor.kpi',
            ];

            foreach ($permissions as $name => $group) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $name],
                    ['group' => $group, 'guard_name' => 'web', 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
                );
            }

            $roleId = DB::table('roles')->where('name', 'Sales Supervisor')->value('id');
            if (! $roleId) {
                return;
            }

            DB::table('roles')->where('id', $roleId)->update([
                'description' => 'Mobile team oversight for assigned sales representatives.',
                'allowed_apps' => json_encode(['supervisor']),
                'updated_at' => $now,
            ]);
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            foreach (DB::table('permissions')->whereIn('name', array_keys($permissions))->pluck('id') as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        });
    }

    public function down(): void
    {
        // Keep role access and permissions intact because they can be assigned business data.
    }
};
