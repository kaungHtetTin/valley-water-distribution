<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $metricId = DB::table('kpi_template_metrics')->where('code', 'DRV-ATTENDANCE')->value('id');
        if (! $metricId) {
            return;
        }

        DB::table('kpi_template_metrics')->where('id', $metricId)->update([
            'unit' => 'days',
            'default_target' => null,
            'updated_at' => now(),
        ]);

        $draftResultIds = DB::table('kpi_results')->where('status', 'draft')->pluck('id');
        DB::table('kpi_result_items')
            ->whereIn('kpi_result_id', $draftResultIds)
            ->where('kpi_template_metric_id', $metricId)
            ->where('target_value', 100)
            ->update(['target_value' => null, 'updated_at' => now()]);
    }

    public function down()
    {
        DB::table('kpi_template_metrics')->where('code', 'DRV-ATTENDANCE')->update([
            'unit' => 'percent',
            'default_target' => 100,
            'updated_at' => now(),
        ]);
    }
};
