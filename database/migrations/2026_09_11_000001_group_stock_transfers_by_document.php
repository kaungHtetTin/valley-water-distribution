<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stock_movements')
            ->whereIn('movement_type', ['transfer_out', 'transfer_in'])
            ->whereNotNull('reference_code')
            ->update(['document_code' => DB::raw('reference_code'), 'reference_type' => 'stock_transfer']);
    }

    public function down(): void
    {
        // Document grouping is retained because removing it would lose transfer-level history.
    }
};
