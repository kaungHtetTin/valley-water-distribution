<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('document_code')->constrained()->restrictOnDelete();
        });

        Schema::table('supplier_ledger_entries', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('entry_type');
            $table->string('source_key')->nullable()->after('source_type');
            $table->unique(['source_type', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_ledger_entries', function (Blueprint $table) {
            $table->dropUnique(['source_type', 'source_key']);
            $table->dropColumn(['source_type', 'source_key']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
