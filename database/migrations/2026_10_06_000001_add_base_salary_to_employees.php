<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('base_salary', 14, 2)->nullable()->after('employee_type');
        });

        // Preserve the salaries used by payroll before employee-level editing.
        DB::table('employees')->update(['base_salary' => DB::raw("CASE employee_type
            WHEN 'office' THEN 450000
            WHEN 'sales' THEN 380000
            WHEN 'sales_supervisor' THEN 450000
            WHEN 'driver' THEN 360000
            WHEN 'warehouse' THEN 330000
            ELSE 300000 END")]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('base_salary');
        });
    }
};
