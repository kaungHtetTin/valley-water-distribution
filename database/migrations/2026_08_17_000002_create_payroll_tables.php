<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('month', 7)->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('employee_type')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['month', 'employee_type']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code');
            $table->string('employee_name');
            $table->string('employee_type')->index();
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('gps_denied_count')->default(0);
            $table->unsignedInteger('outside_radius_count')->default(0);
            $table->timestamp('first_attendance_at')->nullable();
            $table->timestamp('last_attendance_at')->nullable();
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->decimal('allowance_amount', 14, 2)->default(0);
            $table->decimal('incentive_amount', 14, 2)->default(0);
            $table->decimal('ot_amount', 14, 2)->default(0);
            $table->decimal('advance_deduction', 14, 2)->default(0);
            $table->decimal('other_deduction', 14, 2)->default(0);
            $table->decimal('gross_pay', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['payroll_id', 'employee_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
    }
};
