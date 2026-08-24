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

        $this->getJson('/api/deliveries/meta')
            ->assertOk()
            ->assertJsonPath('data.invoices.0.code', 'INV-202608-0001')
            ->assertJsonPath('data.drivers.0.code', 'DRV-001')
            ->assertJsonPath('data.vehicles.0.code', 'VEH-001')
            ->assertJsonPath('data.statuses.0', 'planned')
            ->assertJsonPath('data.statuses.7', 'cancelled');

        $created = $this->postJson('/api/deliveries', [
            'invoice_id' => $invoiceId,
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
            ->assertJsonPath('data.items.0.product_sku', 'VAL-5G')
            ->assertJsonPath('data.items.0.planned_quantity', 12);

        $deliveryId = $created->json('data.delivery.id');
        $this->assertStringStartsWith('DEL-202608-', $created->json('data.delivery.code'));
        $this->assertDatabaseHas('orders', ['code' => 'ORD-202608-0002', 'status' => 'assigned']);

        $this->getJson("/api/deliveries/{$deliveryId}")
            ->assertOk()
            ->assertJsonPath('data.delivery.driver_code', 'DRV-001')
            ->assertJsonPath('data.delivery.vehicle_code', 'VEH-001');

        $this->getJson('/api/deliveries?status=assigned&search=INV-202608-0001')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->postJson('/api/deliveries', [
            'invoice_id' => $invoiceId,
            'warehouse_id' => DB::table('warehouses')->where('code', 'WH-TGI')->value('id'),
            'route_id' => DB::table('routes')->where('code', 'TGI-N')->value('id'),
            'driver_id' => DB::table('employees')->where('code', 'DRV-001')->value('id'),
            'vehicle_id' => DB::table('vehicles')->where('code', 'VEH-001')->value('id'),
            'planned_date' => '2026-08-21',
        ])->assertUnprocessable();
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
        $this->postJson("/api/mobile/deliveries/{$deliveryId}/confirm-loading", [
            'items' => [['id' => $itemId, 'loaded_quantity' => 18]],
            'notes' => 'Truck load checked by driver',
        ])->assertOk()
            ->assertJsonPath('data.delivery.status', 'loading')
            ->assertJsonPath('data.delivery.loaded_quantity', 18)
            ->assertJsonPath('data.items.0.loaded_quantity', 18);

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

    public function test_sales_and_client_can_view_only_delivery_status_in_their_scope()
    {
        $this->seed();
        $deliveryId = DB::table('deliveries')->where('code', 'DEL-202608-0001')->value('id');

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->getJson('/api/mobile/delivery-status')
            ->assertOk()
            ->assertJsonPath('data.app', 'sales')
            ->assertJsonPath('data.summary.deliveries_count', 3)
            ->assertJsonPath('data.items.0.code', 'DEL-202608-0001');
        $this->getJson("/api/mobile/delivery-status/{$deliveryId}")
            ->assertOk()
            ->assertJsonPath('data.delivery.route_code', 'TGI-N');

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
