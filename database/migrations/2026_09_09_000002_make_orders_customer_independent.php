<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('orders', 'area_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('area_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
                $table->string('recipient_name')->nullable()->after('price_type_id');
                $table->string('recipient_phone')->nullable()->after('recipient_name');
                $table->text('delivery_address')->nullable()->after('recipient_phone');
            });
        }

        if (! Schema::hasColumn('invoices', 'area_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('area_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
                $table->foreignId('route_id')->nullable()->after('area_id')->constrained('routes')->nullOnDelete();
                $table->string('recipient_name')->nullable()->after('route_id');
                $table->string('recipient_phone')->nullable()->after('recipient_name');
                $table->text('delivery_address')->nullable()->after('recipient_phone');
            });
        }

        if (! Schema::hasColumn('deliveries', 'area_id')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->foreignId('area_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
                $table->string('recipient_name')->nullable()->after('vehicle_id');
                $table->string('recipient_phone')->nullable()->after('recipient_name');
            });
        }

        DB::table('orders')->whereNull('recipient_name')->orderBy('id')->eachById(function ($order) {
            $customer = $order->customer_id ? DB::table('customers')->find($order->customer_id) : null;
            $route = $order->route_id ? DB::table('routes')->find($order->route_id) : null;
            DB::table('orders')->where('id', $order->id)->update([
                'area_id' => $order->area_id ?? $customer?->area_id ?? $route?->area_id,
                'recipient_name' => $customer?->shop_name ?? 'Guest customer',
                'recipient_phone' => $customer?->phone,
                'delivery_address' => $order->delivery_address ?? $customer?->address ?? 'Address not recorded',
            ]);
        });

        DB::table('invoices')->whereNull('recipient_name')->orderBy('id')->eachById(function ($invoice) {
            $order = $invoice->order_id ? DB::table('orders')->find($invoice->order_id) : null;
            $customer = $invoice->customer_id ? DB::table('customers')->find($invoice->customer_id) : null;
            DB::table('invoices')->where('id', $invoice->id)->update([
                'area_id' => $order?->area_id ?? $customer?->area_id,
                'route_id' => $order?->route_id ?? $customer?->route_id,
                'recipient_name' => $order?->recipient_name ?? $customer?->shop_name ?? 'Guest customer',
                'recipient_phone' => $order?->recipient_phone ?? $customer?->phone,
                'delivery_address' => $order?->delivery_address ?? $customer?->address ?? 'Address not recorded',
            ]);
        });

        DB::table('deliveries')->whereNull('recipient_name')->orderBy('id')->eachById(function ($delivery) {
            $order = $delivery->order_id ? DB::table('orders')->find($delivery->order_id) : null;
            $customer = $delivery->customer_id ? DB::table('customers')->find($delivery->customer_id) : null;
            DB::table('deliveries')->where('id', $delivery->id)->update([
                'area_id' => $order?->area_id ?? $customer?->area_id,
                'recipient_name' => $order?->recipient_name ?? $customer?->shop_name ?? 'Guest customer',
                'recipient_phone' => $order?->recipient_phone ?? $customer?->phone,
                'delivery_address' => $delivery->delivery_address ?? $order?->delivery_address ?? $customer?->address ?? 'Address not recorded',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY customer_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE invoices MODIFY customer_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE deliveries MODIFY customer_id BIGINT UNSIGNED NULL');
        }
    }

    public function down()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['recipient_name', 'recipient_phone']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('route_id');
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['recipient_name', 'recipient_phone', 'delivery_address']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['recipient_name', 'recipient_phone', 'delivery_address']);
        });
    }
};
