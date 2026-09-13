<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('adjustment_reason', 40)->nullable()->after('movement_type')->index();
        });

        DB::table('stock_movements')
            ->where('movement_type', 'damage')
            ->whereNull('adjustment_reason')
            ->update(['adjustment_reason' => 'damage']);
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['adjustment_reason']);
            $table->dropColumn('adjustment_reason');
        });
    }
};
