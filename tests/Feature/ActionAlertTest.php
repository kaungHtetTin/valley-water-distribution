<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActionAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_alerts_combine_actionable_queues()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $this->getJson('/api/action-alerts?app=office')
            ->assertOk()
            ->assertJsonPath('data.total', 7)
            ->assertJsonFragment(['id' => 'pending-orders', 'count' => 1])
            ->assertJsonFragment(['id' => 'collection-reviews', 'count' => 2])
            ->assertJsonFragment(['id' => 'expense-reviews', 'count' => 1])
            ->assertJsonFragment(['id' => 'vehicle-reviews', 'count' => 2])
            ->assertJsonFragment(['id' => 'stock-alerts', 'count' => 1]);
    }

    public function test_each_mobile_app_receives_only_its_own_action_count()
    {
        $this->seed();

        $client = User::where('email', 'client@valley.test')->firstOrFail();
        $this->actingAs($client)
            ->getJson('/api/action-alerts?app=client')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonFragment(['id' => 'pending-orders', 'count' => 1]);

        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        DB::table('orders')->where('code', 'ORD-202608-0001')->update(['created_by' => $sales->id, 'status' => 'draft']);
        $this->actingAs($sales)
            ->getJson('/api/action-alerts?app=sales')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonFragment(['id' => 'draft-orders', 'count' => 1]);

        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $this->actingAs($driver)
            ->getJson('/api/action-alerts?app=driver')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonFragment(['id' => 'driver-tasks', 'count' => 2, 'path' => '/tasks']);

        $this->getJson('/api/action-alerts?app=office')->assertForbidden();
    }

    public function test_supervisor_approval_counts_include_only_unapproved_customers_created_by_their_team(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        DB::table('employees')->where('id', $sales->employee_id)->update(['supervisor_id' => $supervisor->employee_id]);
        DB::table('customers')->update(['acquisition_approved_at' => now()]);
        DB::table('customers')->insert(['code' => 'PENDING-OWN', 'shop_name' => 'My team customer', 'contact_name' => 'Customer', 'phone' => '091111111', 'address' => 'Main street', 'created_by' => $sales->id, 'is_active' => true]);
        DB::table('customers')->insert(['code' => 'PENDING-OTHER', 'shop_name' => 'Other customer', 'contact_name' => 'Other', 'phone' => '092222222', 'address' => 'Other street', 'created_by' => $supervisor->id, 'is_active' => true]);

        $this->actingAs($supervisor)->getJson('/api/action-alerts?app=supervisor')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonFragment(['id' => 'customer-approvals', 'count' => 1]);
        $this->getJson('/api/mobile/supervisor')->assertOk()->assertJsonPath('data.summary.pending_approvals', 1);
    }
}
