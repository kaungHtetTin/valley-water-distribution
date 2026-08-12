<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['brands', 'units_of_measure', 'product_categories', 'price_types'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedInteger('lock_version')->default(1)->before('status');
            });
        }
    }

    public function down(): void
    {
        foreach (['brands', 'units_of_measure', 'product_categories', 'price_types'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('lock_version');
            });
        }
    }
};
