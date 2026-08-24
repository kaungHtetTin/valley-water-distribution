<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseEightVehicleCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_can_create_update_and_delete_vehicle_cost_with_book_posting()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $vehicleId = DB::table('vehicles')->where('code', 'VEH-001')->value('id');

        $created = $this->postJson('/api/vehicle-costs', [
            'vehicle_id' => $vehicleId, 'cost_date' => '2026-08-17', 'cost_type' => 'fuel',
            'description' => 'Test diesel refill', 'vendor' => 'Test Fuel', 'quantity' => 20,
            'unit_price' => 1000, 'amount' => 20000, 'payment_method' => 'cash', 'reference_no' => 'TEST-FUEL-01',
        ])->assertCreated();
        $id = $created->json('data.id');
        $this->assertDatabaseHas('vehicle_costs', ['id' => $id, 'status' => 'approved', 'amount' => 20000]);
        $this->assertDatabaseHas('financial_transactions', ['reference_type' => 'vehicle_cost', 'reference_id' => $id, 'amount' => 20000]);

        $this->putJson("/api/vehicle-costs/{$id}", [
            'vehicle_id' => $vehicleId, 'cost_date' => '2026-08-17', 'cost_type' => 'maintenance',
            'description' => 'Updated service', 'amount' => 25000, 'payment_method' => 'bank',
        ])->assertOk();
        $this->assertDatabaseHas('financial_transactions', ['reference_type' => 'vehicle_cost', 'reference_id' => $id, 'book_type' => 'bank', 'amount' => 25000]);

        $this->deleteJson("/api/vehicle-costs/{$id}")->assertOk();
        $this->assertDatabaseMissing('vehicle_costs', ['id' => $id]);
        $this->assertDatabaseMissing('financial_transactions', ['reference_type' => 'vehicle_cost', 'reference_id' => $id]);
    }

    public function test_office_can_filter_every_vehicle_cost_type_and_view_monthly_summary()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        foreach (['fuel', 'maintenance', 'insurance', 'license', 'engine_oil', 'tyre', 'other'] as $type) {
            $this->getJson("/api/vehicle-costs?cost_type={$type}")
                ->assertOk()
                ->assertJsonPath('data.items.0.cost_type', $type);
        }

        $this->getJson('/api/vehicle-reports/monthly-costs?year=2026')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 620000)
            ->assertJsonPath('data.items.0.month', '2026-08')
            ->assertJsonPath('data.items.0.types.fuel', 85000)
            ->assertJsonPath('data.items.0.types.tyre', 160000);
    }

    public function test_route_history_distance_drives_cost_per_kilometre_and_performance()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $deliveryId = DB::table('deliveries')->where('code', 'DEL-202608-0001')->value('id');

        $this->putJson("/api/vehicle-reports/route-history/{$deliveryId}/distance", [
            'start_odometer_km' => 12502, 'end_odometer_km' => 12532,
        ])->assertOk()->assertJsonPath('data.distance_km', 30);

        $this->getJson('/api/vehicle-reports/route-history?date_from=2026-08-01&date_to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.summary.routes_count', 3)
            ->assertJsonPath('data.summary.distance_km', 90.5);

        $report = $this->getJson('/api/vehicle-reports/cost-per-km?date_from=2026-08-01&date_to=2026-08-31')->assertOk();
        $this->assertEqualsWithDelta(6850.83, $report->json('data.summary.cost_per_km'), 0.01);
        $this->getJson('/api/vehicle-reports/performance?date_from=2026-08-01&date_to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.items.0.deliveries_count', 3)
            ->assertJsonPath('data.items.0.completed_count', 1)
            ->assertJsonPath('data.items.0.delivered_quantity', 9);
    }

    public function test_driver_can_view_assigned_vehicle_and_submit_issue_and_cost()
    {
        $this->seed();
        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $deliveryId = DB::table('deliveries')->where('code', 'DEL-202608-0002')->value('id');
        $this->actingAs($driver);

        $this->getJson('/api/mobile/vehicle-operations')
            ->assertOk()
            ->assertJsonPath('data.vehicle.code', 'VEH-001')
            ->assertJsonPath('data.summary.submitted_count', 2);

        $issue = $this->postJson('/api/mobile/vehicle-operations', [
            'delivery_id' => $deliveryId, 'cost_date' => '2026-08-17', 'record_type' => 'issue',
            'cost_type' => 'maintenance', 'description' => 'Steering vibration', 'issue_severity' => 'medium', 'payment_method' => 'cash',
        ])->assertCreated();
        $this->assertDatabaseHas('vehicle_costs', ['id' => $issue->json('data.id'), 'record_type' => 'issue', 'amount' => 0, 'status' => 'submitted']);

        $cost = $this->postJson('/api/mobile/vehicle-operations', [
            'delivery_id' => $deliveryId, 'cost_date' => '2026-08-17', 'record_type' => 'cost',
            'cost_type' => 'fuel', 'description' => 'Route fuel', 'amount' => 9000, 'payment_method' => 'cash',
        ])->assertCreated();
        $this->assertDatabaseMissing('financial_transactions', ['reference_type' => 'vehicle_cost', 'reference_id' => $cost->json('data.id')]);

        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $this->postJson('/api/vehicle-costs/'.$cost->json('data.id').'/review', ['status' => 'approved'])->assertOk();
        $this->assertDatabaseHas('financial_transactions', ['reference_type' => 'vehicle_cost', 'reference_id' => $cost->json('data.id'), 'amount' => 9000]);
    }

    public function test_vehicle_cost_permissions_and_driver_scope_are_enforced()
    {
        $this->seed();
        $this->getJson('/api/vehicle-costs')->assertUnauthorized();
        $this->getJson('/api/mobile/vehicle-operations')->assertUnauthorized();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $this->getJson('/api/vehicle-costs')->assertForbidden();
        $this->getJson('/api/mobile/vehicle-operations')->assertForbidden();

        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail());
        $outsideDelivery = DB::table('deliveries')->where('code', 'DEL-202608-0001')->first();
        DB::table('deliveries')->where('id', $outsideDelivery->id)->update(['driver_id' => DB::table('employees')->where('code', 'SAL-001')->value('id')]);
        $this->postJson('/api/mobile/vehicle-operations', [
            'delivery_id' => $outsideDelivery->id, 'cost_date' => '2026-08-17', 'record_type' => 'cost',
            'cost_type' => 'fuel', 'description' => 'Invalid assignment', 'amount' => 1000, 'payment_method' => 'cash',
        ])->assertForbidden();
    }
}
