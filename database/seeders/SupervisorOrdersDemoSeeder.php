<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SupervisorOrdersDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data may only be seeded in local and testing environments.');
        }

        DB::transaction(function (): void {
            $supervisor = DB::table('employees')->where('code', 'SUP-001')
                ->where('employee_type', 'sales_supervisor')->whereNull('deleted_at')->first();
            $members = DB::table('employees')->whereIn('code', ['SAL-001', 'SAL-002'])
                ->where('supervisor_id', $supervisor?->id)->where('employee_type', 'sales')
                ->whereNull('deleted_at')->orderBy('code')->get();
            $product = DB::table('products')->where('sku', 'VAL-5G')->first();
            $warehouseId = DB::table('warehouses')->where('is_active', true)->value('id');
            $driverId = DB::table('employees')->where('employee_type', 'driver')->where('is_active', true)->value('id');
            $vehicleId = DB::table('vehicles')->where('is_active', true)->value('id');
            $priceTypeId = DB::table('price_types')->where('is_default', true)->value('id');
            if (! $supervisor || $members->count() !== 2 || ! $product || ! $warehouseId || ! $driverId || ! $vehicleId) {
                throw new RuntimeException('The existing supervisor demo team, product, warehouse, driver, and vehicle are required.');
            }

            $authors = [];
            $customers = [];
            foreach ($members as $member) {
                $user = User::where('employee_id', $member->id)->first();
                if (! $user) {
                    $user = User::create([
                        'name' => $member->name, 'email' => $member->email, 'phone' => $member->phone,
                        'employee_id' => $member->id, 'role' => 'Sales Representative',
                        'locale' => 'en', 'password' => Hash::make('password'),
                    ]);
                }
                $authors[$member->id] = $user->id;
                $customers[$member->id] = DB::table('customers')->where('route_id', $member->assigned_route_id)
                    ->where('is_active', true)->whereNull('deleted_at')->orderBy('code')->get();
                if ($customers[$member->id]->isEmpty()) {
                    throw new RuntimeException("No active demo customers exist for {$member->code}'s route.");
                }
            }

            // Stable demo codes allow reruns without replacing orders edited during testing.
            $scenarios = [
                ['draft', null, 'unsettled'],
                ['pending', null, 'unsettled'],
                ['pending', null, 'unsettled'],
                ['confirmed', null, 'unsettled'],
                ['invoiced', null, 'unsettled'],
                ['assigned', 'planned', 'unsettled'],
                ['assigned', 'assigned', 'unsettled'],
                ['assigned', 'loading', 'unsettled'],
                ['delivering', 'on_route', 'unsettled'],
                ['delivered', 'delivered', 'cash'],
                ['delivered', 'partially_delivered', 'credit'],
                ['delivering', 'failed', 'unsettled'],
                ['cancelled', null, 'unsettled'],
                ['draft', null, 'unsettled'],
                ['delivered', 'delivered', 'credit'],
                ['delivered', 'delivered', 'cash'],
            ];
            $anchor = Carbon::parse('2026-10-13', 'Asia/Rangoon');
            $now = now();
            foreach ($scenarios as $index => [$status, $deliveryStatus, $paymentType]) {
                $suffix = 'SUP-202610-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
                $code = 'DEMO-ORD-'.$suffix;
                if (DB::table('orders')->where('code', $code)->exists()) {
                    continue;
                }
                $member = $members[$index % $members->count()];
                $routeCustomers = $customers[$member->id];
                $customer = $routeCustomers[intdiv($index, 2) % $routeCustomers->count()];
                $orderDate = $anchor->copy()->subDays($index);
                $deliveryDate = $orderDate->copy()->addDays($deliveryStatus ? 1 : 2);
                $quantity = 2 + ($index * 2);
                $unitPrice = 3000;
                $subtotal = $quantity * $unitPrice;
                $discount = $index % 3 === 0 ? 1000 : 0;
                $total = $subtotal - $discount;
                $confirmed = ! in_array($status, ['draft', 'pending', 'cancelled'], true);
                $destination = [
                    'customer_id' => $customer->id, 'area_id' => $customer->area_id,
                    'route_id' => $member->assigned_route_id, 'recipient_name' => $customer->shop_name,
                    'recipient_phone' => $customer->phone, 'delivery_address' => $customer->address,
                ];
                $totals = ['subtotal' => $subtotal, 'discount_total' => $discount, 'tax_total' => 0, 'total' => $total];
                $orderId = DB::table('orders')->insertGetId($destination + $totals + [
                    'code' => $code, 'price_type_id' => $customer->price_type_id ?? $priceTypeId,
                    'source_app' => 'sales', 'order_date' => $orderDate->toDateString(),
                    'requested_delivery_date' => $deliveryDate->toDateString(),
                    'payment_type' => $paymentType, 'status' => $status,
                    'credit_due_date' => $paymentType === 'credit' ? $deliveryDate->copy()->addDays(7)->toDateString() : null,
                    'created_by' => $authors[$member->id],
                    'confirmed_at' => $confirmed ? $orderDate->copy()->setTime(10, 0) : null,
                    'notes' => "Supervisor order demo: {$member->code}; ".($deliveryStatus ?? $status),
                    'created_at' => $orderDate->copy()->setTime(9, 0), 'updated_at' => $now,
                ]);
                $item = [
                    'product_id' => $product->id, 'product_sku' => $product->sku,
                    'product_name' => $product->name, 'unit' => $product->unit,
                    'quantity' => $quantity, 'unit_price' => $unitPrice,
                    'discount_amount' => $discount, 'line_total' => $total,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                DB::table('order_items')->insert($item + ['order_id' => $orderId, 'item_type' => 'sale']);
                if ($index % 4 === 0) {
                    DB::table('order_items')->insert(array_replace($item, [
                        'order_id' => $orderId, 'item_type' => 'foc', 'quantity' => 1,
                        'unit_price' => 0, 'discount_amount' => 0, 'line_total' => 0,
                        'remarks' => 'Demo complimentary bottle',
                    ]));
                }
                if ($status !== 'invoiced' && ! $deliveryStatus) {
                    continue;
                }
                $invoiceId = DB::table('invoices')->insertGetId($destination + $totals + [
                    'code' => 'DEMO-INV-'.$suffix, 'order_id' => $orderId,
                    'invoice_date' => $orderDate->toDateString(),
                    'due_date' => $paymentType === 'credit' ? $deliveryDate->copy()->addDays(7)->toDateString() : null,
                    'status' => $status === 'delivered' ? 'delivered' : ($deliveryStatus ? 'issued' : 'draft'),
                    'created_by' => $authors[$member->id], 'notes' => 'Supervisor order demo invoice',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $invoiceItems = [];
                foreach (DB::table('order_items')->where('order_id', $orderId)->get() as $orderItem) {
                    $invoiceItem = [
                        'invoice_id' => $invoiceId, 'product_id' => $orderItem->product_id,
                        'product_sku' => $orderItem->product_sku, 'product_name' => $orderItem->product_name,
                        'unit' => $orderItem->unit, 'quantity' => $orderItem->quantity,
                        'unit_price' => $orderItem->unit_price, 'discount_amount' => $orderItem->discount_amount,
                        'line_total' => $orderItem->line_total, 'created_at' => $now, 'updated_at' => $now,
                    ];
                    $invoiceItems[] = $invoiceItem + ['id' => DB::table('invoice_items')->insertGetId($invoiceItem)];
                }
                if (! $deliveryStatus) {
                    continue;
                }
                $planned = collect($invoiceItems)->sum('quantity');
                $loaded = in_array($deliveryStatus, ['loading', 'on_route', 'delivered', 'partially_delivered', 'failed'], true) ? $planned : 0;
                $delivered = $deliveryStatus === 'delivered' ? $planned : ($deliveryStatus === 'partially_delivered' ? $planned - 2 : 0);
                $departed = in_array($deliveryStatus, ['on_route', 'delivered', 'partially_delivered', 'failed'], true);
                $completed = in_array($deliveryStatus, ['delivered', 'partially_delivered', 'failed'], true);
                $deliveryId = DB::table('deliveries')->insertGetId($destination + [
                    'code' => 'DEMO-DEL-'.$suffix, 'invoice_id' => $invoiceId, 'order_id' => $orderId,
                    'warehouse_id' => $warehouseId, 'driver_id' => $driverId, 'vehicle_id' => $vehicleId,
                    'planned_date' => $deliveryDate->toDateString(), 'status' => $deliveryStatus,
                    'total_quantity' => $planned, 'loaded_quantity' => $loaded, 'delivered_quantity' => $delivered,
                    'returned_quantity' => $deliveryStatus === 'partially_delivered' ? 2 : 0,
                    'assigned_at' => $deliveryStatus !== 'planned' ? $orderDate->copy()->setTime(11, 0) : null,
                    'loaded_at' => $loaded ? $deliveryDate->copy()->setTime(8, 0) : null,
                    'departed_at' => $departed ? $deliveryDate->copy()->setTime(9, 0) : null,
                    'completed_at' => $completed ? $deliveryDate->copy()->setTime(10, 30) : null,
                    'notes' => 'Supervisor order demo delivery', 'created_by' => $authors[$member->id],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach ($invoiceItems as $position => $invoiceItem) {
                    $returned = $deliveryStatus === 'partially_delivered' && $position === 0 ? 2 : 0;
                    DB::table('delivery_items')->insert([
                        'delivery_id' => $deliveryId, 'invoice_item_id' => $invoiceItem['id'],
                        'product_id' => $product->id, 'product_sku' => $product->sku,
                        'product_name' => $product->name, 'unit' => $product->unit,
                        'planned_quantity' => $invoiceItem['quantity'],
                        'loaded_quantity' => $loaded ? $invoiceItem['quantity'] : 0,
                        'delivered_quantity' => $delivered ? $invoiceItem['quantity'] - $returned : 0,
                        'returned_quantity' => $returned, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        });
    }
}
