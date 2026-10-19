<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeCustomerVisitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visits_are_scoped_to_employee_route_and_selected_date(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());
        $employee = DB::table('employees')->where('code', 'SAL-001')->first();
        $otherEmployee = DB::table('employees')->where('code', 'SAL-002')->first();
        $customers = DB::table('customers')->where('route_id', $employee->assigned_route_id)->orderBy('code')->get();
        $date = '2026-10-19';

        foreach ([
            [$employee->id, $customers[0]->id, $date, 'completed'],
            [$employee->id, $customers[1]->id, $date, 'in_progress'],
            [$employee->id, $customers[2]->id, '2026-10-18', 'completed'],
            [$otherEmployee->id, $customers[2]->id, $date, 'completed'],
        ] as [$employeeId, $customerId, $visitDate, $status]) {
            DB::table('sales_route_visits')->insert([
                'employee_id' => $employeeId, 'route_id' => $employee->assigned_route_id,
                'customer_id' => $customerId, 'visit_date' => $visitDate, 'status' => $status,
                'started_at' => $visitDate.' 09:00:00',
                'completed_at' => $status === 'completed' ? $visitDate.' 09:20:00' : null,
                'notes' => 'Visit note', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $response = $this->getJson("/api/master-data/employees/{$employee->id}/customer-visits?date={$date}")
            ->assertOk()->assertJsonPath('data.date', $date)
            ->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.summary.visited', 1)
            ->assertJsonPath('data.summary.not_visited', 2);
        $rows = collect($response->json('data.customers'))->keyBy('id');
        $this->assertSame('completed', $rows[$customers[0]->id]['visit_status']);
        $this->assertSame('Visit note', $rows[$customers[0]->id]['notes']);
        $this->assertSame('in_progress', $rows[$customers[1]->id]['visit_status']);
        $this->assertSame('planned', $rows[$customers[2]->id]['visit_status']);

        DB::table('sales_route_visits')->where('employee_id', $employee->id)->where('visit_date', $date)
            ->where('customer_id', $customers[1]->id)->update(['status' => 'skipped']);
        DB::table('customers')->where('id', $customers[0]->id)->update(['is_active' => false]);
        DB::table('customers')->where('id', $customers[2]->id)->update(['deleted_at' => now()]);
        $this->getJson("/api/master-data/employees/{$employee->id}/customer-visits?date={$date}")
            ->assertOk()->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.visited', 0)
            ->assertJsonPath('data.summary.not_visited', 1)
            ->assertJsonPath('data.customers.0.visit_status', 'skipped');
    }

    public function test_default_date_empty_route_validation_and_access(): void
    {
        $this->seed();
        $employee = DB::table('employees')->where('code', 'SAL-001')->first();
        $url = "/api/master-data/employees/{$employee->id}/customer-visits";
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::where('email', 'sales@valley.test')->first());
        $this->getJson($url)->assertForbidden();
        $this->actingAs(User::where('email', 'owner@valley.test')->first());
        $this->getJson($url.'?date=invalid')->assertUnprocessable();
        DB::table('employees')->where('id', $employee->id)->update(['assigned_route_id' => null]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.date', now()->toDateString())
            ->assertJsonPath('data.customers', [])->assertJsonPath('data.summary.total', 0);
        DB::table('employees')->where('id', $employee->id)->update(['employee_type' => 'office']);
        $this->getJson($url)->assertUnprocessable();
        $this->getJson('/api/master-data/employees/999999/customer-visits')->assertNotFound();
    }
}
