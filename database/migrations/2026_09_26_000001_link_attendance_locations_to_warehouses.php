<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_locations', function (Blueprint $table) {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('warehouses')
                ->nullOnDelete();
        });

        $defaultWarehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');

        if ($defaultWarehouseId) {
            DB::table('attendance_locations')
                ->where('code', 'ATT-OFFICE')
                ->whereNull('warehouse_id')
                ->update(['warehouse_id' => $defaultWarehouseId]);
        }
    }

    public function down(): void
    {
        Schema::table('attendance_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
