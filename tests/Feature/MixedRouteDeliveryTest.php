<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MixedRouteDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_assign_and_edit_a_mixed_route_trip_and_driver_can_operate_it(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $firstInvoice = DB::table('invoices')->where('code', 'INV-202608-0001')->first();
        $this->actingAs($owner)->postJson("/api/invoices/{$firstInvoice->id}/issue")->assertOk();
        $firstRoute = DB::table('routes')->where('code', 'TGI-N')->value('id');
        $customer = DB::table('customers')->where('code', 'CUS-0004')->first();
        $this->assertNotEquals($firstRoute, $customer->route_id);
        $product = DB::table('products')->where('sku', 'VAL-5G')->first();
        $order = DB::table('orders')->insertGetId([
            'code' => 'ORD-CROSS-ROUTE', 'customer_id' => $customer->id, 'route_id' => $customer->route_id,
            'source_app' => 'office', 'order_date' => '2026-08-19', 'payment_type' => 'credit',
            'status' => 'invoiced', 'subtotal' => 6000, 'total' => 6000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoice = DB::table('invoices')->insertGetId([
            'code' => 'INV-CROSS-ROUTE', 'order_id' => $order, 'customer_id' => $customer->id,
            'route_id' => $customer->route_id, 'invoice_date' => '2026-08-19', 'status' => 'issued',
            'subtotal' => 6000, 'total' => 6000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('invoice_items')->insert([
            'invoice_id' => $invoice, 'product_id' => $product->id, 'product_sku' => $product->sku,
            'product_name' => $product->name, 'unit' => $product->unit, 'quantity' => 2,
            'unit_price' => 3000, 'line_total' => 6000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $assignment = [
            'warehouse_id' => DB::table('warehouses')->where('code', 'WH-TGI')->value('id'),
            'driver_id' => $driver->employee_id,
            'vehicle_id' => DB::table('vehicles')->where('code', 'VEH-001')->value('id'),
            'planned_date' => '2026-08-20',
        ];
        // No route selection is needed, and the driver's profile route is irrelevant.
        DB::table('employees')->where('id', $driver->employee_id)->update(['assigned_route_id' => null]);
        $response = $this->actingAs($owner)->postJson('/api/deliveries', $assignment + [
            'invoice_ids' => [$firstInvoice->id, $invoice],
        ])->assertCreated()->assertJsonPath('data.trip.orders_count', 2)->assertJsonCount(2, 'data.trip.routes');
        $id = $response->json('data.delivery.id');
        $tripId = $response->json('data.trip.id');
        $this->assertDatabaseHas('deliveries', ['trip_id' => $tripId, 'invoice_id' => $firstInvoice->id, 'route_id' => $firstRoute]);
        $this->assertDatabaseHas('deliveries', ['trip_id' => $tripId, 'invoice_id' => $invoice, 'route_id' => $customer->route_id]);
        $this->getJson('/api/deliveries?route_id='.$customer->route_id.'&search=INV-CROSS-ROUTE')
            ->assertOk()->assertJsonPath('data.items.0.orders_count', 2)->assertJsonCount(2, 'data.items.0.routes');
        $this->patchJson("/api/deliveries/{$id}/trip", $assignment + ['notes' => 'Any route assignment'])
            ->assertOk()->assertJsonCount(2, 'data.trip.routes');
        $this->assertDatabaseHas('deliveries', ['trip_id' => $tripId, 'invoice_id' => $invoice, 'route_id' => $customer->route_id]);

        // Even with a different profile route, finance options follow assigned delivery stops.
        DB::table('employees')->where('id', $driver->employee_id)->update(['assigned_route_id' => $firstRoute]);
        $this->actingAs($driver)->getJson('/api/mobile/deliveries')->assertOk()->assertJsonFragment(['orders_count' => 2, 'stops_count' => 2]);
        $finance = $this->getJson('/api/mobile/finance/meta')->assertOk();
        $this->assertContains($customer->id, collect($finance->json('data.customers'))->pluck('id')->all());
        $items = DB::table('delivery_items')->join('deliveries', 'delivery_items.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.trip_id', $tripId)->get(['delivery_items.id', 'delivery_items.planned_quantity']);
        $this->postJson("/api/mobile/deliveries/{$id}/confirm-loading", [
            'items' => $items->map(fn ($item) => ['id' => $item->id, 'loaded_quantity' => $item->planned_quantity])->all(),
        ])->assertOk();
        $this->postJson("/api/mobile/deliveries/{$id}/start-route")->assertOk();
        $this->assertSame(2, DB::table('deliveries')->where('trip_id', $tripId)->where('status', 'on_route')->count());
    }
}
