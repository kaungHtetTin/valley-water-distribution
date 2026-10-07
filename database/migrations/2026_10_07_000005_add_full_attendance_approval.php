<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedBigInteger('full_attendance_approved_by')->nullable();
            $table->string('full_attendance_approver_name')->nullable();
            $table->timestamp('full_attendance_approved_at')->nullable();
            $table->unsignedInteger('original_late_minutes')->nullable();
            $table->decimal('waived_late_fine', 16, 2)->nullable();
        });
        DB::table('permissions')->updateOrInsert(['name' => 'office.attendance.approve-full'], [
            'group' => 'office.attendance', 'guard_name' => 'web', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ids = DB::table('permissions')->whereIn('name', ['office.attendance.view', 'office.attendance.approve-full'])->pluck('id');
        foreach (DB::table('roles')->whereIn('name', ['Owner', 'Finance Manager', 'Manager'])->pluck('id') as $roleId) {
            foreach ($ids as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', 'office.attendance.approve-full')->value('id');
        DB::table('permission_role')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
        Schema::table('attendance_records', fn (Blueprint $table) => $table->dropColumn([
            'full_attendance_approved_by', 'full_attendance_approver_name', 'full_attendance_approved_at',
            'original_late_minutes', 'waived_late_fine',
        ]));
    }
};
