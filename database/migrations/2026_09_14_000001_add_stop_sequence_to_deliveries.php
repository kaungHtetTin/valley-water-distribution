<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedInteger('stop_sequence')->default(1)->after('trip_id');
            $table->index(['trip_id', 'stop_sequence']);
        });

        DB::table('deliveries')->orderBy('trip_id')->orderBy('id')->get()->groupBy(fn ($delivery) => $delivery->trip_id ?: 'legacy-'.$delivery->id)->each(function ($deliveries) {
            foreach ($deliveries->values() as $index => $delivery) {
                DB::table('deliveries')->where('id', $delivery->id)->update(['stop_sequence' => $index + 1]);
            }
        });
    }

    public function down()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['trip_id', 'stop_sequence']);
            $table->dropColumn('stop_sequence');
        });
    }
};
