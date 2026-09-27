<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'driver_modified')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('driver_modified')->default(false)->after('status')->index();
                $table->text('driver_modification_note')->nullable()->after('driver_modified');
                $table->foreignId('driver_modified_by')->nullable()->after('driver_modification_note')->constrained('users')->nullOnDelete();
                $table->timestamp('driver_modified_at')->nullable()->after('driver_modified_by');
            });
        }

        if (! Schema::hasColumn('deliveries', 'order_modified')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->boolean('order_modified')->default(false)->after('settled_at')->index();
                $table->text('order_modification_note')->nullable()->after('order_modified');
                $table->foreignId('order_modified_by')->nullable()->after('order_modification_note')->constrained('users')->nullOnDelete();
                $table->timestamp('order_modified_at')->nullable()->after('order_modified_by');
            });
        }

        if (! Schema::hasColumn('invoice_items', 'item_type')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->string('item_type')->default('sale')->after('unit')->index();
            });
        }

        if (! Schema::hasColumn('delivery_items', 'item_type')) {
            Schema::table('delivery_items', function (Blueprint $table) {
                $table->string('item_type')->default('sale')->after('unit')->index();
                $table->decimal('unit_price', 14, 2)->default(0)->after('item_type');
                $table->decimal('discount_amount', 14, 2)->default(0)->after('unit_price');
            });
        }

        DB::table('invoice_items')->where('unit_price', 0)->update(['item_type' => 'foc']);
        DB::table('delivery_items')->orderBy('id')->eachById(function ($item) {
            $invoiceItem = $item->invoice_item_id ? DB::table('invoice_items')->find($item->invoice_item_id) : null;
            DB::table('delivery_items')->where('id', $item->id)->update([
                'item_type' => $invoiceItem?->item_type ?? 'sale',
                'unit_price' => $invoiceItem?->unit_price ?? 0,
                'discount_amount' => $invoiceItem?->discount_amount ?? 0,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropIndex(['item_type']);
            $table->dropColumn(['item_type', 'unit_price', 'discount_amount']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex(['item_type']);
            $table->dropColumn('item_type');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['order_modified_by']);
            $table->dropIndex(['order_modified']);
            $table->dropColumn(['order_modified', 'order_modification_note', 'order_modified_by', 'order_modified_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['driver_modified_by']);
            $table->dropIndex(['driver_modified']);
            $table->dropColumn(['driver_modified', 'driver_modification_note', 'driver_modified_by', 'driver_modified_at']);
        });
    }
};
