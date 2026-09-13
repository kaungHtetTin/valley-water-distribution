<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseSixDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_assign_an_issued_invoice_for_delivery()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $invoiceId = DB::table('invoices')->where('code', 'INV-202608-0001')->value('id');
        $this->postJson("/api/invoices/{$invoiceId}/issue")->assertOk();

        $customerId = DB::table('invoices')->where('id', $invoiceId)->value('customer_id');
        $routeId = DB::table('routes')->where('code', 'TGI-N')->value('id');
        $product = DB::table('products')->where('sku', 'VAL-5G')->first();
        $secondOrderId = DB::table('orders')->insertGetId([
            'code' => 'ORD-TRIP-TEST', 'customer_id' => $customerId, 'route_id' => $routeId,
            'source_app' => 'office', 'order_date' => '2026-08-19', 'payment_type' => 'cash',
            'status' => 'invoiced', 'subtotal' => 12000, 'total' => 12000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $secondInvoiceId = DB::table('invoices')->insertGetId([
            'code' => 'INV-TRIP-TEST', 'order_id' => $secondOrderId, 'customer_id' => $customerId,
            'invoice_date' => '2026-08-19', 'status' => 'issued', 'subtotal' => 12000, 'total' => 12000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('invoice_items')->insert([
            'invoice_id' => $secondInvoiceId, 'product_id' => $product->id, 'product_sku' => $product->sku,
            'product_name' => $product->name, 'unit' => $product->unit, 'quantity' => 4,
            'unit_price' => 3000, 'line_total' => 12000, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/api/deliveries/meta')
            ->assertOk()
            ->assertJsonCount(2, 'data.invoices')
            ->assertJsonStructure(['data' => ['invoices' => [['delivery_address', 'recipient_phone']]]])
            ->assertJsonPath('data.drivers.0.code', 'DRV-001')
            ->assertJsonPath('data.vehicles.0.code', 'VEH-001')
            ->assertJsonPath('data.statuses.0', 'planned')
            ->assertJsonPath('data.statuses.7', 'cancelled');

        $created = $this->postJson('/api/deliveries', [
            'invoice_ids' => [$invoiceId, $secondInvoiceId],
            'warehouse_id' => DB::table('warehouses')->where('code', 'WH-TGI')->value('id'),
            'route_id' => DB::table('routes')->where('code', 'TGI-N')->value('id'),
            'driver_id' => DB::table('employees')->where('code', 'DRV-001')->value('id'),
            'vehicle_id' => DB::table('vehicles')->where('code', 'VEH-001')->value('id'),
            'planned_date' => '2026-08-20',
            'delivery_address' => 'Demo Shop, North Route',
            'notes' => 'Feature delivery assignment',
        ])->assertCreated()
            ->assertJsonPath('data.delivery.invoice_code', 'INV-202608-0001')
            ->assertJsonPath('data.delivery.status', 'assigned')
            ->assertJsonPath('data.delivery.total_quantity', 12)
            ->assertJsonPath('data.trip.orders_count', 2)
            ->assertJsonPath('data.trip.total_quantity', 16)
            ->assertJsonPath('data.items.0.product_sku', 'VAL-5G')
            ->assertJsonPath('data.items.0.planned_quantity', 12);

        $deliveryId = $created->json('data.delivery.id');
        $tripId = $created->json('data.trip.id');
        $this->assertStringStartsWith('DEL-202608-', $created->json('data.delivery.code'));
        $this->assertStringStartsWith('TRIP-202608-', $created->json('data.trip.code'));
        $this->assertSame(2, DB::table('deliveries')->where('trip_id', $tripId)->count());
        $this->assertDatabaseHas('orders', ['code' => 'ORD-202608-0002', 'status' => 'assigned']);

        $this->getJson("/api/deliveries/{$deliveryId}")
            ->assertOk()
            ->assertJsonPath('data.delivery.driver_code', 'DRV-001')
            ->assertJsonPath('data.delivery.vehicle_code', 'VEH-001')
            ->assertJsonPath('data.trip.code', $created->json('data.trip.code'))
            ->assertJsonPath('data.trip.orders_count', 2)
            ->assertJsonCount(2, 'data.stops')
            ->assertJsonCount(1, 'data.stock')
            ->assertJsonPath('data.stock.0.product_sku', 'VAL-5G')
            ->assertJsonPath('data.stock.0.planned_quantity', 16)
            ->assertJsonStructure(['data' => ['stops' => [['order_code', 'shop_name', 'delivery_address', 'items' => [['product_name', 'planned_quantity']]]]]]);

        $this->getJson('/api/deliveries?status=assigned&search=INV-202608-0001')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->postJson('/api/deliveries', [
            'invoice_ids' => [$invoiceId, $secondInvoiceId],
            'warehouse_id' => DB::table('warehouses')->where('code', 'WH-TGI')->value('id'),
            'route_id' => DB::table('routes')->where('code', 'TGI-N')->value('id'),
            'driver_id' => DB::table('employees')->where('code', 'DRV-001')->value('id'),
            'vehicle_id' => DB::table('vehicles')->where('code', 'VEH-001')->value('id'),
            'planned_date' => '2026-08-21',
        ])->assertUnprocessable();

        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail());
        $deliveryItemId = DB::table('delivery_items')->where('delivery_id', $deliveryId)->value('id');
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/confirm-loading", [
            'items' => [['id' => $deliveryItemId, 'loaded_quantity' => 12]],
        ])->assertOk();
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'delivery_issue',
            'reference_id' => $deliveryId,
            'reference_code' => $created->json('data.trip.code'),
            'signed_quantity' => -12,
        ]);
    }

    public function test_delivery_api_requires_delivery_permission()
    {
        $this->seed();
        $this->getJson('/api/deliveries')->assertUnauthorized();
        $this->getJson('/api/deliveries/meta')->assertUnauthorized();
        $this->postJson('/api/deliveries', [])->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->getJson('/api/deliveries')->assertForbidden();
        $this->postJson('/api/deliveries', [])->assertForbidden();
        $deliveryId = DB::table('deliveries')->value('id');
        $this->patchJson("/api/deliveries/{$deliveryId}/trip", [])->assertForbidden();
        $this->postJson("/api/deliveries/{$deliveryId}/cancel")->assertForbidden();
    }

    public function test_office_can_edit_and_cancel_a_trip_before_loading_starts()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $delivery = DB::table('deliveries')->where('code', 'DEL-202608-0001')->first();
        $warehouseId = DB::table('warehouses')->where('code', 'WH-NSN')->value('id');
        $routeId = DB::table('routes')->where('id', $delivery->route_id)->value('id');
        $driverId = DB::table('employees')->where('code', 'DRV-001')->value('id');
        $vehicleId = DB::table('vehicles')->where('code', 'VEH-001')->value('id');

        $this->patchJson("/api/deliveries/{$delivery->id}/trip", [
            'warehouse_id' => $warehouseId,
            'route_id' => $routeId,
            'driver_id' => $driverId,
            'vehicle_id' => $vehicleId,
            'planned_date' => '2026-08-25',
            'notes' => 'Office rescheduled trip',
        ])->assertOk()
            ->assertJsonPath('data.delivery.planned_date', '2026-08-25')
            ->assertJsonPath('data.delivery.warehouse_id', $warehouseId);

        $this->postJson("/api/deliveries/{$delivery->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.delivery.status', 'cancelled');

        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('orders', ['id' => $delivery->order_id, 'status' => 'invoiced']);
        $this->getJson('/api/deliveries/meta')
            ->assertOk()
            ->assertJsonFragment(['id' => $delivery->invoice_id]);
    }

    public function test_driver_can_view_assignment_and_confirm_loading_quantities()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail());

        $list = $this->getJson('/api/mobile/deliveries')
            ->assertOk()
            ->assertJsonPath('data.summary.deliveries_count', 3)
            ->assertJsonPath('data.items.0.code', 'DEL-202608-0001')
            ->assertJsonPath('data.items.0.status', 'assigned');

        $deliveryId = $list->json('data.items.0.id');
        $detail = $this->getJson("/api/mobile/deliveries/{$deliveryId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.planned_quantity', 18);

        $itemId = $detail->json('data.items.0.id');
        $warehouseId = DB::table('deliveries')->where('id', $deliveryId)->value('warehouse_id');
        $productId = DB::table('delivery_items')->where('id', $itemId)->value('product_id');
        $startingBalance = (float) DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->value('quantity');
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/confirm-loading", [
            'items' => [['id' => $itemId, 'loaded_quantity' => 18]],
            'notes' => 'Truck load checked by driver',
        ])->assertOk()
            ->assertJsonPath('data.delivery.status', 'loading')
            ->assertJsonPath('data.delivery.loaded_quantity', 18)
            ->assertJsonPath('data.items.0.loaded_quantity', 18);

        $this->assertSame($startingBalance - 18, (float) DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'movement_type' => 'delivery_issue',
            'reference_type' => 'delivery',
            'reference_id' => $deliveryId,
            'reference_code' => 'DEL-202608-0001',
            'signed_quantity' => -18,
        ]);

        $this->postJson("/api/mobile/deliveries/{$deliveryId}/confirm-loading", [
            'items' => [['id' => $itemId, 'loaded_quantity' => 18]],
        ])->assertOk();
        $this->assertSame(1, DB::table('stock_movements')
            ->where('reference_type', 'delivery')->where('reference_id', $deliveryId)->count());
        $this->assertSame($startingBalance - 18, (float) DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity'));

        $this->assertDatabaseHas('orders', ['code' => 'ORD-202608-0003', 'status' => 'loading']);

        $this->postJson("/api/mobile/deliveries/{$deliveryId}/confirm-loading", [
            'items' => [['id' => $itemId, 'loaded_quantity' => 19]],
        ])->assertUnprocessable();

        $this->postJson("/api/mobile/deliveries/{$deliveryId}/start-route")
            ->assertOk()
            ->assertJsonPath('data.delivery.status', 'on_route');
        $this->assertDatabaseHas('orders', ['code' => 'ORD-202608-0003', 'status' => 'delivering']);

        $this->postJson("/api/mobile/deliveries/{$deliveryId}/complete", [
            'status' => 'partially_delivered',
            'items' => [['id' => $itemId, 'delivered_quantity' => 15, 'returned_quantity' => 1, 'damaged_quantity' => 1]],
        ])->assertUnprocessable();

        $this->postJson("/api/mobile/deliveries/{$deliveryId}/complete", [
            'status' => 'partially_delivered',
            'items' => [['id' => $itemId, 'delivered_quantity' => 15, 'returned_quantity' => 2, 'damaged_quantity' => 1]],
            'notes' => 'Two returned and one damaged bottle',
        ])->assertOk()
            ->assertJsonPath('data.delivery.status', 'partially_delivered')
            ->assertJsonPath('data.delivery.delivered_quantity', 15)
            ->assertJsonPath('data.delivery.returned_quantity', 2)
            ->assertJsonPath('data.delivery.damaged_quantity', 1)
            ->assertJsonPath('data.items.0.delivered_quantity', 15);

        $this->assertDatabaseHas('orders', ['code' => 'ORD-202608-0003', 'status' => 'partially_delivered']);
        $this->assertDatabaseHas('invoices', ['code' => 'INV-202608-0002', 'status' => 'partially_delivered']);
        $this->assertSame($startingBalance - 16, (float) DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'delivery_return',
            'reference_id' => $deliveryId,
            'signed_quantity' => 2,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'delivery_damage',
            'reference_id' => $deliveryId,
            'quantity' => 1,
            'signed_quantity' => 0,
        ]);

        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $this->getJson('/api/stock/movements?type_group=issue&search=DEL-202608-0001')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.movement_type', 'delivery_issue')
            ->assertJsonPath('data.items.0.reference_code', 'DEL-202608-0001');
    }

    public function test_loading_changes_reconcile_stock_and_insufficient_stock_rolls_back()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail());

        $deliveryId = DB::table('deliveries')->where('code', 'DEL-202608-0001')->value('id');
        $itemId = DB::table('delivery_items')->where('delivery_id', $deliveryId)->value('id');
        $warehouseId = DB::table('deliveries')->where('id', $deliveryId)->value('warehouse_id');
        $productId = DB::table('delivery_items')->where('id', $itemId)->value('product_id');
        $startingBalance = (float) DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity');

        $this->postJson("/api/mobile/deliveries/{$deliveryId}/confirm-loading", [
            'items' => [['id' => $itemId, 'loaded_quantity' => 18]],
        ])->assertOk();
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/confirm-loading", [
            'items' => [['id' => $itemId, 'loaded_quantity' => 15]],
        ])->assertOk();

        $this->assertSame($startingBalance - 15, (float) DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'delivery_issue_reversal',
            'reference_id' => $deliveryId,
            'signed_quantity' => 3,
        ]);

        DB::table('stock_balances')->where('warehouse_id', $warehouseId)->where('product_id', $productId)->update(['quantity' => 1]);
        $secondDeliveryId = DB::table('deliveries')->where('code', 'DEL-202608-0002')->value('id');
        $secondItemId = DB::table('delivery_items')->where('delivery_id', $secondDeliveryId)->value('id');
        DB::table('deliveries')->where('id', $secondDeliveryId)->update(['status' => 'assigned', 'loaded_quantity' => 0, 'loaded_at' => null]);
        DB::table('delivery_items')->where('id', $secondItemId)->update(['loaded_quantity' => 0]);

        $this->postJson("/api/mobile/deliveries/{$secondDeliveryId}/confirm-loading", [
            'items' => [['id' => $secondItemId, 'loaded_quantity' => 6]],
        ])->assertStatus(409);

        $this->assertDatabaseHas('deliveries', ['id' => $secondDeliveryId, 'status' => 'assigned', 'loaded_quantity' => 0]);
        $this->assertDatabaseHas('delivery_items', ['id' => $secondItemId, 'loaded_quantity' => 0]);
        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'delivery_issue',
            'reference_id' => $secondDeliveryId,
        ]);
    }

    public function test_non_driver_cannot_access_driver_delivery_api()
    {
        $this->seed();
        $deliveryId = DB::table('deliveries')->value('id');

        $this->getJson('/api/mobile/deliveries')->assertUnauthorized();
        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->getJson('/api/mobile/deliveries')->assertForbidden();
        $this->getJson("/api/mobile/deliveries/{$deliveryId}")->assertForbidden();
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/confirm-loading", [])->assertForbidden();
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/start-route")->assertForbidden();
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/complete", [])->assertForbidden();
    }

    public function test_only_customer_can_view_mobile_delivery_status_in_their_scope()
    {
        $this->seed();
        $deliveryId = DB::table('deliveries')->where('code', 'DEL-202608-0001')->value('id');

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->getJson('/api/mobile/delivery-status')->assertForbidden();
        $this->getJson("/api/mobile/delivery-status/{$deliveryId}")->assertForbidden();

        $client = User::where('email', 'client@valley.test')->firstOrFail();
        $this->actingAs($client);
        $this->getJson('/api/mobile/delivery-status')
            ->assertOk()
            ->assertJsonPath('data.app', 'client')
            ->assertJsonPath('data.summary.deliveries_count', 1)
            ->assertJsonPath('data.items.0.code', 'DEL-202608-0002');
        $this->getJson("/api/mobile/delivery-status/{$deliveryId}")->assertNotFound();

        DB::table('deliveries')->where('id', $deliveryId)->update(['customer_id' => $client->customer_id]);
        $this->getJson('/api/mobile/delivery-status')
            ->assertOk()
            ->assertJsonPath('data.summary.deliveries_count', 2)
            ->assertJsonPath('data.items.0.code', 'DEL-202608-0001');
        $this->getJson("/api/mobile/delivery-status/{$deliveryId}")
            ->assertOk()
            ->assertJsonPath('data.delivery.customer_code', 'CUS-0001');
    }

    public function test_delivery_status_api_rejects_unauthorized_apps()
    {
        $this->seed();
        $deliveryId = DB::table('deliveries')->value('id');

        $this->getJson('/api/mobile/delivery-status')->assertUnauthorized();

        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $this->getJson('/api/mobile/delivery-status')->assertForbidden();
        $this->getJson("/api/mobile/delivery-status/{$deliveryId}")->assertForbidden();

        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail());
        $this->getJson('/api/mobile/delivery-status')->assertForbidden();
    }

    public function test_driver_can_share_active_route_location_and_office_can_track_it()
    {
        $this->seed();
        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $deliveryId = DB::table('deliveries')->where('code', 'DEL-202608-0001')->value('id');
        $itemId = DB::table('delivery_items')->where('delivery_id', $deliveryId)->value('id');

        $this->actingAs($driver);
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/location", [
            'latitude' => 20.7892,
            'longitude' => 97.0378,
        ])->assertStatus(409);

        $this->postJson("/api/mobile/deliveries/{$deliveryId}/confirm-loading", [
            'items' => [['id' => $itemId, 'loaded_quantity' => 18]],
        ])->assertOk();
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/start-route")->assertOk();

        $this->postJson("/api/mobile/deliveries/{$deliveryId}/location", [
            'latitude' => 20.7892,
            'longitude' => 97.0378,
            'accuracy_m' => 8.5,
            'heading' => 125,
            'speed_kmh' => 24.5,
        ])->assertCreated()
            ->assertJsonPath('data.location.latitude', 20.7892)
            ->assertJsonPath('data.location.accuracy_m', 8.5);

        $this->assertDatabaseHas('delivery_locations', [
            'delivery_id' => $deliveryId,
            'driver_id' => $driver->employee_id,
            'latitude' => 20.7892,
            'longitude' => 97.0378,
        ]);

        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $this->getJson('/api/deliveries/live-map')
            ->assertOk()
            ->assertJsonPath('data.summary.active_count', 2)
            ->assertJsonPath('data.summary.live_count', 2)
            ->assertJsonPath('data.items.0.tracking_state', 'live')
            ->assertJsonPath('data.items.0.location.longitude', 97.0378);
        $this->getJson("/api/deliveries/{$deliveryId}/locations")
            ->assertOk()
            ->assertJsonPath('data.delivery.code', 'DEL-202608-0001')
            ->assertJsonPath('data.locations.0.speed_kmh', 24.5);

        $this->getJson('/api/deliveries?history=1')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.code', 'DEL-202608-0003')
            ->assertJsonPath('data.items.0.status', 'delivered');

        DB::table('deliveries')->where('id', $deliveryId)->update(['status' => 'delivered']);
        $this->actingAs($driver);
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/location", [
            'latitude' => 20.79,
            'longitude' => 97.04,
        ])->assertStatus(409);
    }

    public function test_location_tracking_endpoints_enforce_app_permissions()
    {
        $this->seed();
        $deliveryId = DB::table('deliveries')->value('id');

        $this->getJson('/api/deliveries/live-map')->assertUnauthorized();
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/location", [])->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->getJson('/api/deliveries/live-map')->assertForbidden();
        $this->getJson("/api/deliveries/{$deliveryId}/locations")->assertForbidden();
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/location", [])->assertForbidden();
    }

    public function test_driver_can_record_delivered_and_failed_terminal_results()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail());

        $deliveredId = DB::table('deliveries')->where('code', 'DEL-202608-0002')->value('id');
        $deliveredItemId = DB::table('delivery_items')->where('delivery_id', $deliveredId)->value('id');
        $this->postJson("/api/mobile/deliveries/{$deliveredId}/complete", [
            'status' => 'delivered',
            'items' => [[
                'id' => $deliveredItemId,
                'delivered_quantity' => 6,
                'returned_quantity' => 0,
                'damaged_quantity' => 0,
            ]],
            'notes' => 'All bottles delivered',
        ])->assertOk()
            ->assertJsonPath('data.delivery.status', 'delivered')
            ->assertJsonPath('data.delivery.delivered_quantity', 6);

        $failedId = DB::table('deliveries')->where('code', 'DEL-202608-0003')->value('id');
        DB::table('deliveries')->where('id', $failedId)->update(['status' => 'on_route', 'delivered_quantity' => 0, 'completed_at' => null]);
        $failedItemId = DB::table('delivery_items')->where('delivery_id', $failedId)->value('id');
        $this->postJson("/api/mobile/deliveries/{$failedId}/complete", [
            'status' => 'failed',
            'items' => [[
                'id' => $failedItemId,
                'delivered_quantity' => 0,
                'returned_quantity' => 9,
                'damaged_quantity' => 0,
            ]],
            'notes' => 'Customer unavailable',
        ])->assertOk()
            ->assertJsonPath('data.delivery.status', 'failed')
            ->assertJsonPath('data.delivery.returned_quantity', 9);

        $this->assertDatabaseHas('orders', ['code' => 'ORD-202608-0004', 'status' => 'delivered']);
        $this->assertDatabaseHas('invoices', ['code' => 'INV-202608-0004', 'status' => 'failed']);
    }
}
