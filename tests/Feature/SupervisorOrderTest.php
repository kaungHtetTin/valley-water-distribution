<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupervisorOrderTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(string $email = 'sales@valley.test', array $overrides = []): array
    {
        $sales = User::where('email', $email)->first();
        if (! $sales) {
            $employee = DB::table('employees')->where('email', $email)->first();
            $sales = User::factory()->create(['email' => $email, 'name' => $employee->name,
                'role' => 'Sales Representative', 'employee_id' => $employee->id]);
        }
        $routeId = DB::table('employees')->where('id', $sales->employee_id)->value('assigned_route_id');
        $this->actingAs($sales);

        return $this->postJson('/api/mobile/orders', array_replace([
            'customer_id' => DB::table('customers')->where('route_id', $routeId)->value('id'),
            'order_date' => '2026-10-13',
            'requested_delivery_date' => '2026-10-15',
            'items' => [['product_id' => DB::table('products')->where('sku', 'VAL-5G')->value('id'), 'quantity' => 2, 'item_type' => 'sale']],
        ], $overrides))->assertCreated()->json('data.order');
    }

    private function supervisor(): User
    {
        return User::where('email', 'sales.supervisor@valley.test')->firstOrFail();
    }

    public function test_supervisor_sees_orders_from_both_team_routes_and_only_their_authors(): void
    {
        $this->seed();
        $first = $this->createOrder();
        $second = $this->createOrder('nandar@valley.test');
        // A client/office order on the same route must not become a team order.
        $outsideId = DB::table('orders')->where('code', 'ORD-202608-0001')->value('id');
        DB::table('orders')->where('id', $outsideId)->update(['route_id' => $first['route_id']]);
        $this->actingAs($this->supervisor())->getJson('/api/mobile/orders?per_page=1')
            ->assertOk()->assertJsonPath('data.meta.total', 2)->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonPath('data.items.0.id', $second['id'])->assertJsonPath('data.items.0.representative_code', 'SAL-002')
            ->assertJsonPath('data.summary.total_amount', 12000);
        $this->getJson('/api/mobile/orders?per_page=1&page=2')->assertOk()->assertJsonPath('data.items.0.id', $first['id']);
        $this->getJson("/api/mobile/orders/{$outsideId}")->assertNotFound();

        // Removing a team assignment immediately revokes list, detail, and filter access.
        DB::table('employees')->where('code', 'SAL-002')->update(['supervisor_id' => null]);
        $this->getJson('/api/mobile/orders')->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->getJson('/api/mobile/orders?representative_id='.$second['representative_id'])->assertOk()->assertJsonPath('data.meta.total', 0);
        $this->getJson('/api/mobile/orders/'.$second['id'])->assertNotFound();
        $this->getJson('/api/mobile/orders/meta')->assertOk()->assertJsonCount(1, 'data.representatives');
    }

    public function test_supervisor_filters_team_orders_and_gets_matching_summaries(): void
    {
        $this->seed();
        $order = $this->createOrder();
        $this->createOrder('nandar@valley.test', ['save_as' => 'draft', 'order_date' => '2026-10-01']);
        $this->actingAs($this->supervisor());
        $filters = [
            'search' => $order['code'], 'representative_id' => $order['representative_id'],
            'route_id' => $order['route_id'], 'status' => 'pending', 'payment_type' => 'unsettled',
            'date_from' => '2026-10-13', 'date_to' => '2026-10-13',
            'delivery_from' => '2026-10-15', 'delivery_to' => '2026-10-15',
            'min_total' => 6000, 'max_total' => 6000, 'delivery_status' => 'none',
        ];
        $this->getJson('/api/mobile/orders?'.http_build_query($filters))->assertOk()
            ->assertJsonPath('data.meta.total', 1)->assertJsonPath('data.summary.orders_count', 1)
            ->assertJsonPath('data.summary.total_amount', 6000)->assertJsonPath('data.items.0.id', $order['id']);
        foreach (['representative_id' => 99999, 'route_id' => 99999, 'status' => 'confirmed',
            'payment_type' => 'cash', 'date_from' => '2026-10-14', 'delivery_from' => '2026-10-16',
            'max_total' => 5999, 'delivery_status' => 'on_route'] as $key => $value) {
            $this->getJson('/api/mobile/orders?'.http_build_query([$key => $value]))->assertOk()->assertJsonPath('data.meta.total', 0);
        }
        $this->getJson('/api/mobile/orders?date_to=2026-10-01')->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->getJson('/api/mobile/orders?date_from=2026-10-15&date_to=2026-10-01')->assertUnprocessable();
        $this->getJson('/api/mobile/orders?min_total=6000&max_total=5000')->assertUnprocessable();
        $this->getJson('/api/mobile/orders/meta')->assertOk()->assertJsonPath('data.app', 'supervisor')->assertJsonCount(2, 'data.representatives');
    }

    public function test_supervisor_order_details_include_items_invoice_and_delivery_progress(): void
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $invoiceOrderId = DB::table('orders')->where('code', 'ORD-202608-0002')->value('id');
        $deliveryOrderId = DB::table('orders')->where('code', 'ORD-202608-0003')->value('id');
        DB::table('orders')->whereIn('id', [$invoiceOrderId, $deliveryOrderId])->update(['created_by' => $sales->id]);
        $this->actingAs($this->supervisor())->getJson('/api/mobile/orders/'.$invoiceOrderId)->assertOk()
            ->assertJsonPath('data.order.representative_code', 'SAL-001')
            ->assertJsonPath('data.order.invoice_code', 'INV-202608-0001')
            ->assertJsonPath('data.items.0.quantity', 12);
        $response = $this->getJson('/api/mobile/orders/'.$deliveryOrderId)->assertOk();
        $this->assertNotNull($response->json('data.order.delivery'));
        $status = $response->json('data.order.delivery.status');
        $this->getJson('/api/mobile/orders?delivery_status='.$status)->assertOk()->assertJsonPath('data.items.0.id', $deliveryOrderId);
    }

    public function test_supervisor_cannot_mutate_orders_and_needs_permission_and_linked_profile(): void
    {
        $this->seed();
        $order = $this->createOrder();
        $supervisor = $this->supervisor();
        $this->actingAs($supervisor)->postJson('/api/mobile/orders', [])->assertForbidden();
        $this->putJson('/api/mobile/orders/'.$order['id'], [])->assertForbidden();
        $this->postJson('/api/mobile/orders/'.$order['id'].'/cancel')->assertForbidden();
        $supervisor->update(['employee_id' => null]);
        $this->actingAs($supervisor->fresh())->getJson('/api/mobile/orders')->assertNotFound();
        $supervisor->update(['employee_id' => DB::table('employees')->where('code', 'SUP-001')->value('id')]);
        $permissionId = DB::table('permissions')->where('name', 'supervisor.orders.view')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        $this->actingAs($supervisor->fresh())->getJson('/api/mobile/orders')->assertForbidden();
        $this->getJson('/api/mobile/orders/meta')->assertForbidden();
        $this->getJson('/api/mobile/orders/'.$order['id'])->assertForbidden();
    }
}
