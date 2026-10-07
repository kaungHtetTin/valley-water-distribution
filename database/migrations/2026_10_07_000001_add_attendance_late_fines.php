<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('attendance_start_time', 5)->default('08:00');
        });
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedInteger('late_minutes')->default(0);
            $table->decimal('late_fine', 16, 2)->default(0);
            $table->decimal('salary_snapshot', 14, 2)->nullable();
            $table->string('start_time_snapshot', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', fn (Blueprint $table) => $table->dropColumn(['late_minutes', 'late_fine', 'salary_snapshot', 'start_time_snapshot']));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('attendance_start_time'));
    }
};
