<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $roleId = DB::table('roles')->where('name', 'Sales Supervisor')->value('id');
            foreach (['supervisor.finance.view', 'supervisor.expenses.view', 'supervisor.expenses.create'] as $name) {
                DB::table('permissions')->updateOrInsert(['name' => $name], [
                    'group' => substr($name, 0, strrpos($name, '.')), 'guard_name' => 'web',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($roleId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => DB::table('permissions')->where('name', $name)->value('id'),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', [
            'supervisor.finance.view', 'supervisor.expenses.view', 'supervisor.expenses.create',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
