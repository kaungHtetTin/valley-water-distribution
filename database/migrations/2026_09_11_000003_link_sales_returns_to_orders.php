<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('original_order_id')->nullable()->after('id')->constrained('orders')->nullOnDelete();
            $table->foreignId('return_warehouse_id')->nullable()->after('original_order_id')->constrained('warehouses')->nullOnDelete();
            $table->string('return_settlement_method')->nullable()->after('payment_type')->index();
            $table->decimal('refund_amount', 14, 2)->default(0)->after('return_settlement_method');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('original_order_item_id')->nullable()->after('order_id')->constrained('order_items')->nullOnDelete();
            $table->string('return_condition')->nullable()->after('item_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['original_order_item_id']);
            $table->dropColumn(['original_order_item_id', 'return_condition']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['original_order_id']);
            $table->dropForeign(['return_warehouse_id']);
            $table->dropColumn(['original_order_id', 'return_warehouse_id', 'return_settlement_method', 'refund_amount']);
        });
    }
};
