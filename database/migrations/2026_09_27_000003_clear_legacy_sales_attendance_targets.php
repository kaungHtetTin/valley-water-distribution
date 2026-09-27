<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $metricId = DB::table('kpi_template_metrics')->where('code', 'SAL-ATTENDANCE')->value('id');
        if ($metricId) {
            $draftResultIds = DB::table('kpi_results')->where('status', 'draft')->pluck('id');
            DB::table('kpi_result_items')
                ->whereIn('kpi_result_id', $draftResultIds)
                ->where('kpi_template_metric_id', $metricId)
                ->where('target_value', 100)
                ->update(['target_value' => null, 'updated_at' => now()]);
        }
    }

    public function down()
    {
        // Draft targets are user-entered values and are not restored automatically.
    }
};
