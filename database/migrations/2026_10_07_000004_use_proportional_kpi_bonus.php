<?php

use App\Support\KpiBonus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_results', fn (Blueprint $table) => $table->string('bonus_calculation', 20)->default('proportional'));
        DB::table('kpi_results')->where('status', 'approved')->orWhereNotNull('payroll_adjustment_id')->update(['bonus_calculation' => 'bands']);
        DB::table('kpi_results')->whereIn('status', ['draft', 'submitted'])->whereNull('payroll_adjustment_id')->orderBy('id')->each(function ($result) {
            DB::table('kpi_results')->where('id', $result->id)->update([
                'bonus_amount' => KpiBonus::amount((float) $result->target_bonus, (float) $result->overall_score),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Restore a pre-migration backup to revert the bonus calculation and its historical amounts.');
    }
};
