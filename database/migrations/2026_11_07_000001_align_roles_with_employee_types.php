<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach ([
                'Sales Representative' => 'Sale Representative',
                'Sales Supervisor' => 'Sale Manager',
                'Finance Manager' => 'Manager',
                'Office Staff' => 'Other',
            ] as $old => $new) {
                $source = DB::table('roles')->where('name', $old)->first();
                if ($source) {
                    $target = DB::table('roles')->where('name', $new)->first();
                    if ($target) {
                        // Never combine different permissions: that could grant extra access.
                        $sourcePermissions = DB::table('permission_role')->where('role_id', $source->id)->orderBy('permission_id')->pluck('permission_id')->all();
                        $targetPermissions = DB::table('permission_role')->where('role_id', $target->id)->orderBy('permission_id')->pluck('permission_id')->all();
                        $sourceApps = json_decode($source->allowed_apps ?? '[]', true);
                        $targetApps = json_decode($target->allowed_apps ?? '[]', true);
                        sort($sourceApps);
                        sort($targetApps);
                        if ($sourcePermissions !== $targetPermissions || $sourceApps !== $targetApps || $source->is_active != $target->is_active) {
                            throw new RuntimeException("Cannot align roles: '$old' and '$new' have different access settings. Rename the conflicting '$new' role to a custom name before retrying.");
                        }
                        DB::table('roles')->where('id', $source->id)->delete();
                    } else {
                        DB::table('roles')->where('id', $source->id)->update(['name' => $new, 'updated_at' => now()]);
                    }
                }
                DB::table('users')->where('role', $old)->update(['role' => $new, 'updated_at' => now()]);
            }

            foreach ([
                'Storekeeper' => ['apps' => ['office'], 'description' => 'Office inventory viewing, stock receiving, and stock issue.', 'permissions' => ['office.inventory.view', 'office.inventory.manage']],
                'Sale Helper' => ['apps' => [], 'description' => 'Supporting sales employee; no app access by default.', 'permissions' => []],
            ] as $name => $defaults) {
                if (DB::table('roles')->where('name', $name)->exists()) {
                    continue;
                }
                $roleId = DB::table('roles')->insertGetId([
                    'name' => $name, 'guard_name' => 'web', 'description' => $defaults['description'],
                    'allowed_apps' => json_encode($defaults['apps']), 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach (DB::table('permissions')->whereIn('name', $defaults['permissions'])->pluck('id') as $permissionId) {
                    DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
        });
    }

    public function down(): void
    {
        // Preserve assigned roles and any permissions customized after deployment.
    }
};
