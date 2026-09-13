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
            $table->string('document_code', 40)->nullable()->after('code')->index();
        });

        DB::table('stock_movements')
            ->whereIn('movement_type', ['opening', 'receive'])
            ->whereNull('document_code')
            ->orderBy('id')
            ->eachById(function ($movement) {
                DB::table('stock_movements')->where('id', $movement->id)->update([
                    'document_code' => $movement->code,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['document_code']);
            $table->dropColumn('document_code');
        });
    }
};
