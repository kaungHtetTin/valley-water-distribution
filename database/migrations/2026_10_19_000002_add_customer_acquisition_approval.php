<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('acquisition_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acquisition_approved_at')->nullable();
        });
        DB::table('permissions')->updateOrInsert(['name' => 'supervisor.customers.review'], [
            'group' => 'supervisor.customers', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $roleId = DB::table('roles')->where('name', 'Sales Supervisor')->value('id');
        if ($roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => DB::table('permissions')->where('name', 'supervisor.customers.review')->value('id'),
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('name', 'supervisor.customers.review')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acquisition_approved_by');
            $table->dropColumn('acquisition_approved_at');
        });
    }
};
