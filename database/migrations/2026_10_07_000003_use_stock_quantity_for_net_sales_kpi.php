<?php

use App\Support\NetSalesQuantity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_result_items', fn (Blueprint $table) => $table->string('unit_snapshot', 30)->nullable());
        $metric = DB::table('kpi_template_metrics')->where('code', 'SAL-NET-SALES')->first();
        if (! $metric) {
            return;
        }
        DB::transaction(function () use ($metric) {
            // Keep finalized currency-based reviews identifiable in mixed-period reports.
            DB::table('kpi_result_items')->where('kpi_template_metric_id', $metric->id)->update(['unit_snapshot' => $metric->unit]);
            DB::table('kpi_template_metrics')->where('id', $metric->id)->update(['unit' => 'units', 'default_target' => 4000, 'updated_at' => now()]);
            DB::table('kpi_staff_target_items')->where('kpi_template_metric_id', $metric->id)->update(['target_value' => 4000, 'updated_at' => now()]);

            $drafts = DB::table('kpi_results')->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
                ->where('kpi_results.kpi_template_id', $metric->kpi_template_id)->where('kpi_results.status', 'draft')
                ->select('kpi_results.*', 'kpi_periods.period_start', 'kpi_periods.period_end')->get();
            foreach ($drafts as $draft) {
                $source = NetSalesQuantity::forEmployee($draft->employee_id, $draft->period_start, $draft->period_end);
                $achievement = min($source['quantity'] / 4000 * 100, 100);
                DB::table('kpi_result_items')->where('kpi_result_id', $draft->id)->where('kpi_template_metric_id', $metric->id)->update([
                    'unit_snapshot' => 'units', 'target_value' => 4000, 'actual_value' => $source['quantity'],
                    'achievement_percent' => round($achievement, 2), 'weighted_score' => round($achievement * $metric->weight / 100, 2),
                    'source_count' => $source['count'], 'source_note' => $source['note'], 'synced_at' => now(), 'updated_at' => now(),
                ]);
                $score = (float) DB::table('kpi_result_items')->where('kpi_result_id', $draft->id)->sum('weighted_score');
                $payout = DB::table('kpi_bonus_rules')->where('kpi_template_id', $draft->kpi_template_id)
                    ->where('minimum_score', '<=', $score)->where(fn ($query) => $query->whereNull('maximum_score')->orWhere('maximum_score', '>', $score))
                    ->orderByDesc('minimum_score')->value('payout_percent') ?? 0;
                DB::table('kpi_results')->where('id', $draft->id)->update([
                    'overall_score' => round($score, 2), 'bonus_amount' => round($draft->target_bonus * $payout / 100, 2), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Quantity targets cannot safely be converted back to monetary targets. Restore a pre-migration backup to revert this change.');
    }
};
