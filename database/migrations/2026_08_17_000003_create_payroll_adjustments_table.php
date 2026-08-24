<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('adjustment_type')->index();
            $table->string('title');
            $table->decimal('amount', 14, 2);
            $table->date('effective_date')->index();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
