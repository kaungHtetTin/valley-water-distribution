<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeTypeFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_type_controls_route_and_vehicle_assignments(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $routeId = DB::table('routes')->value('id');
        $vehicleId = DB::table('vehicles')->insertGetId([
            'code' => 'TYPE-TEST', 'plate_no' => 'TYPE-TEST', 'vehicle_type' => 'truck',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $created = $this->postJson('/api/master-data/employees', [
            'name' => 'Type selection test', 'employee_type' => 'driver',
            'assigned_vehicle_id' => $vehicleId, 'assigned_route_id' => $routeId,
        ])->assertCreated()->assertJsonPath('data.item.assigned_route_id', null)
            ->assertJsonPath('data.item.assigned_vehicle_id', $vehicleId);
        $id = $created->json('data.item.id');
        $this->assertDatabaseHas('vehicles', ['id' => $vehicleId, 'assigned_driver_id' => $id]);
        $this->putJson('/api/master-data/employees/'.$id, [
            'name' => 'Type selection test', 'employee_type' => 'sales', 'assigned_route_id' => $routeId,
        ])->assertOk()->assertJsonPath('data.item.assigned_route_id', $routeId)
            ->assertJsonPath('data.item.assigned_vehicle_id', null);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicleId, 'assigned_driver_id' => null]);
        $this->putJson('/api/master-data/employees/'.$id, [
            'name' => 'Type selection test', 'employee_type' => 'office', 'assigned_route_id' => $routeId,
        ])->assertOk()->assertJsonPath('data.item.assigned_route_id', null);
    }

    public function test_employee_form_cannot_assign_a_vehicle_already_used_by_another_driver(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $vehicle = DB::table('vehicles')->whereNotNull('assigned_driver_id')->first();
        $count = DB::table('employees')->count();
        $this->postJson('/api/master-data/employees', [
            'name' => 'Conflicting driver', 'employee_type' => 'driver', 'assigned_vehicle_id' => $vehicle->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('assigned_vehicle_id');
        $this->assertSame($count, DB::table('employees')->count());
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'assigned_driver_id' => $vehicle->assigned_driver_id]);
    }
}
