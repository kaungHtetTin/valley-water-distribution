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
}
