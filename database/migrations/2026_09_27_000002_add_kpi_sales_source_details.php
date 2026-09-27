<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kpi_result_items', function (Blueprint $table) {
            $table->unsignedInteger('source_count')->default(0)->after('weighted_score');
            $table->string('source_note', 500)->nullable()->after('source_count');
            $table->timestamp('synced_at')->nullable()->after('source_note');
        });

        DB::table('kpi_template_metrics')
            ->where('code', 'SAL-ATTENDANCE')
            ->update(['unit' => 'days', 'default_target' => null, 'updated_at' => now()]);
    }

    public function down()
    {
        DB::table('kpi_template_metrics')
            ->where('code', 'SAL-ATTENDANCE')
            ->update(['unit' => 'percent', 'default_target' => 100, 'updated_at' => now()]);

        Schema::table('kpi_result_items', function (Blueprint $table) {
            $table->dropColumn(['source_count', 'source_note', 'synced_at']);
        });
    }
};
