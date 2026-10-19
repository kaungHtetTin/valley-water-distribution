<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_click_captures_missing_location_and_approves_once(): void
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->first();
        $this->actingAs($sales);
        $customerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        DB::table('customers')->where('id', $customerId)->update(['latitude' => null, 'longitude' => null]);
        $url = "/api/mobile/sales-visits/customers/{$customerId}";
        $position = ['latitude' => 20.78, 'longitude' => 97.04, 'accuracy_m' => 15];
        $visitId = $this->postJson($url, $position)->assertOk()
            ->assertJsonPath('data.visit.status', 'completed')->json('data.visit.id');
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'latitude' => 20.78, 'longitude' => 97.04, 'gps_accuracy_m' => 15]);
        $visit = DB::table('sales_route_visits')->find($visitId);
        $this->assertNotNull($visit->started_at);
        $this->assertNotNull($visit->completed_at);
        $this->assertEquals(20.78, $visit->completed_latitude);
        $this->postJson($url, $position)->assertOk()->assertJsonPath('data.visit.id', $visitId);
        $this->assertSame(1, DB::table('sales_route_visits')->where('employee_id', $sales->employee_id)->where('customer_id', $customerId)->whereDate('visit_date', now()->toDateString())->count());
    }

    public function test_existing_location_requires_500_m_proximity_and_is_preserved(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'sales@valley.test')->first());
        $customerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        DB::table('customers')->where('id', $customerId)->update([
            'latitude' => 20.78, 'longitude' => 97.04, 'gps_captured_at' => '2026-10-01 09:00:00',
        ]);
        $url = "/api/mobile/sales-visits/customers/{$customerId}";
        // About 501 m north, then about 489 m north of the customer.
        $this->postJson($url, ['latitude' => 20.78451, 'longitude' => 97.04])->assertUnprocessable();
        $this->assertDatabaseMissing('sales_route_visits', ['customer_id' => $customerId]);
        $this->postJson($url, ['latitude' => 20.7844, 'longitude' => 97.04])->assertOk()
            ->assertJsonPath('data.visit.status', 'completed');
        $this->assertDatabaseHas('customers', [
            'id' => $customerId, 'latitude' => 20.78, 'longitude' => 97.04, 'gps_captured_at' => '2026-10-01 09:00:00',
        ]);
    }

    public function test_visit_requires_gps_and_an_assigned_customer(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'sales@valley.test')->first());
        $customerId = DB::table('customers')->where('code', 'CUS-0001')->value('id');
        $url = "/api/mobile/sales-visits/customers/{$customerId}";
        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->postJson($url, ['latitude' => 91, 'longitude' => 181])->assertUnprocessable();
        $this->postJson($url, ['status' => 'skipped'])->assertUnprocessable();
        $outsideId = DB::table('customers')->where('code', 'CUS-0004')->value('id');
        $this->postJson("/api/mobile/sales-visits/customers/{$outsideId}", ['latitude' => 20.78, 'longitude' => 97.04])->assertNotFound();
        $this->actingAs(User::where('email', 'client@valley.test')->first());
        $this->postJson($url, ['latitude' => 20.78, 'longitude' => 97.04])->assertForbidden();
    }
}
