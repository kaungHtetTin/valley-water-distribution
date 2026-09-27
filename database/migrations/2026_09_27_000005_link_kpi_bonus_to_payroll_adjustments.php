<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kpi_results', function (Blueprint $table) {
            $table->foreignId('payroll_adjustment_id')
                ->nullable()
                ->after('bonus_amount')
                ->unique()
                ->constrained('payroll_adjustments')
                ->restrictOnDelete();
            $table->foreignId('bonus_posted_by')
                ->nullable()
                ->after('payroll_adjustment_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('bonus_posted_at')->nullable()->after('bonus_posted_by');
        });
    }

    public function down()
    {
        Schema::table('kpi_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bonus_posted_by');
            $table->dropConstrainedForeignId('payroll_adjustment_id');
            $table->dropColumn('bonus_posted_at');
        });
    }
};
