<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->date('count_date')->index();
            $table->string('reference_code', 80)->nullable();
            $table->string('status', 20)->default('completed')->index();
            $table->unsignedInteger('products_count')->default(0);
            $table->decimal('system_quantity', 14, 2)->default(0);
            $table->decimal('counted_quantity', 14, 2)->default(0);
            $table->decimal('quantity_added', 14, 2)->default(0);
            $table->decimal('quantity_removed', 14, 2)->default(0);
            $table->decimal('variance_value', 16, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'count_date']);
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_quantity', 14, 2)->default(0);
            $table->decimal('counted_quantity', 14, 2)->default(0);
            $table->decimal('variance_quantity', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('variance_value', 16, 2)->default(0);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();

            $table->unique(['stock_count_id', 'product_id']);
        });

        DB::table('stock_movements')
            ->where('reference_type', 'closing_count')
            ->orderBy('id')
            ->eachById(function ($movement) {
                $countId = DB::table('stock_counts')->insertGetId([
                    'code' => 'CNT-LEGACY-'.str_pad((string) $movement->id, 6, '0', STR_PAD_LEFT),
                    'warehouse_id' => $movement->warehouse_id,
                    'count_date' => $movement->movement_date,
                    'reference_code' => $movement->reference_code,
                    'status' => 'completed',
                    'products_count' => 1,
                    'system_quantity' => $movement->balance_before ?? 0,
                    'counted_quantity' => $movement->balance_after ?? 0,
                    'quantity_added' => max((float) $movement->signed_quantity, 0),
                    'quantity_removed' => abs(min((float) $movement->signed_quantity, 0)),
                    'variance_value' => (float) $movement->signed_quantity * (float) $movement->unit_cost,
                    'created_by' => $movement->created_by,
                    'notes' => $movement->notes,
                    'created_at' => $movement->created_at,
                    'updated_at' => $movement->updated_at,
                ]);

                DB::table('stock_count_items')->insert([
                    'stock_count_id' => $countId,
                    'product_id' => $movement->product_id,
                    'system_quantity' => $movement->balance_before ?? 0,
                    'counted_quantity' => $movement->balance_after ?? 0,
                    'variance_quantity' => $movement->signed_quantity,
                    'unit_cost' => $movement->unit_cost,
                    'variance_value' => (float) $movement->signed_quantity * (float) $movement->unit_cost,
                    'stock_movement_id' => $movement->id,
                    'created_at' => $movement->created_at,
                    'updated_at' => $movement->updated_at,
                ]);

                DB::table('stock_movements')->where('id', $movement->id)->update([
                    'reference_id' => $countId,
                    'document_code' => DB::table('stock_counts')->where('id', $countId)->value('code'),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
    }
};
