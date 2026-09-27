<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseTenDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-17 12:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_dashboard_contains_all_required_kpis_and_cached_charts()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $response = $this->getJson('/api/dashboards/owner?date=2026-08-17')
            ->assertOk()
            ->assertJsonPath('data.kpis.monthly_sales', 117000)
            ->assertJsonPath('data.kpis.annual_sales', 117000)
            ->assertJsonPath('data.kpis.outstanding_credit', 111000)
            ->assertJsonPath('data.kpis.warehouse_value', 1070000)
            ->assertJsonPath('data.kpis.vehicle_cost', 645000)
            ->assertJsonPath('data.kpis.salary_cost', 775000)
            ->assertJsonPath('data.kpis.outdoor_expense', 3000)
            ->assertJsonPath('data.kpis.net_profit', -1314000)
            ->assertJsonStructure(['data' => ['kpis' => ['today_sales', 'cash_balance', 'bank_balance', 'target_achievement', 'customer_count', 'field_collection'], 'recent_orders', 'attention']]);

        DB::table('invoices')->where('code', 'INV-202608-0001')->update(['total' => 46000]);
        $this->getJson('/api/dashboards/owner?date=2026-08-17')->assertJsonPath('data.kpis.annual_sales', $response->json('data.kpis.annual_sales'));
        Cache::flush();
        $this->getJson('/api/dashboards/owner?date=2026-08-17')->assertJsonPath('data.kpis.annual_sales', 127000);

        $this->getJson('/api/dashboard-charts/owner?date=2026-08-17')
            ->assertOk()
            ->assertJsonCount(6, 'data.sales_trend')
            ->assertJsonCount(7, 'data.cash_flow')
            ->assertJsonStructure(['data' => ['sales_trend' => [['label', 'value']], 'cash_flow' => [['label', 'inflow', 'outflow']], 'mix']]);
    }

    public function test_office_kpi_dashboards_cover_sales_stock_delivery_and_finance()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());

        $this->getJson('/api/dashboards/sales?month=2026-08-01')
            ->assertOk()
            ->assertJsonPath('data.summary.sales', 117000)
            ->assertJsonPath('data.summary.target', 150000)
            ->assertJsonPath('data.summary.achievement', 78)
            ->assertJsonPath('data.ranking.0.name', 'Sales Demo')
            ->assertJsonPath('data.top_customers.0.shop_name', 'Cherry Mini Mart');

        $this->getJson('/api/dashboards/stock?month=2026-08-01')
            ->assertOk()
            ->assertJsonPath('data.summary.quantity', 440)
            ->assertJsonPath('data.summary.stock_value', 1070000)
            ->assertJsonStructure(['data' => ['fast_moving', 'slow_moving', 'alerts']]);

        $this->getJson('/api/dashboards/delivery?month=2026-08-01')
            ->assertOk()
            ->assertJsonPath('data.summary.deliveries', 3)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.summary.delivered_quantity', 9)
            ->assertJsonPath('data.summary.delivery_cost', 620000)
            ->assertJsonStructure(['data' => ['vehicle_usage', 'drivers']]);

        $this->getJson('/api/dashboards/finance?month=2026-08-01')
            ->assertOk()
            ->assertJsonPath('data.summary.collections', 6000)
            ->assertJsonPath('data.summary.field_collections', 17000)
            ->assertJsonPath('data.summary.debt_balance', 111000)
            ->assertJsonPath('data.summary.profit', -539000)
            ->assertJsonCount(4, 'data.expense_analysis');

        foreach (['sales', 'stock', 'delivery', 'finance'] as $dashboard) {
            $this->getJson("/api/dashboard-charts/{$dashboard}?month=2026-08-01&date=2026-08-17")->assertOk();
        }
    }

    public function test_client_home_dashboard_is_scoped_to_own_orders_and_balance()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'client@valley.test')->firstOrFail());

        $this->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('data.app', 'client')
            ->assertJsonPath('data.summary.current_order_code', 'ORD-202608-0004')
            ->assertJsonPath('data.summary.outstanding_balance', 30000)
            ->assertJsonPath('data.recent_orders.0.code', 'ORD-202608-0004');
    }

    public function test_sales_home_dashboard_is_focused_on_orders_and_assigned_route()
    {
        $this->seed();
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        DB::table('orders')->where('code', 'ORD-202608-0001')->update(['source_app' => 'sales', 'created_by' => $sales->id]);
        $this->actingAs($sales);

        $response = $this->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('data.app', 'sales')
            ->assertJsonPath('data.summary.monthly_sales', 117000)
            ->assertJsonPath('data.summary.yearly_sales', 117000)
            ->assertJsonPath('data.summary.target', 150000)
            ->assertJsonPath('data.summary.achievement', 78)
            ->assertJsonPath('data.summary.orders_count', 1)
            ->assertJsonPath('data.summary.assigned_route', 'Taunggyi North')
            ->assertJsonCount(6, 'data.trend')
            ->assertJsonCount(5, 'data.month_trend')
            ->assertJsonCount(12, 'data.year_trend')
            ->assertJsonPath('data.month_trend.2.value', 117000)
            ->assertJsonPath('data.year_trend.7.value', 117000);
        $this->assertArrayNotHasKey('collections', $response->json('data.summary'));
    }

    public function test_driver_home_dashboard_shows_delivery_and_submission_kpis()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'driver@valley.test')->firstOrFail());

        $this->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('data.app', 'driver')
            ->assertJsonPath('data.summary.today_deliveries', 1)
            ->assertJsonPath('data.summary.completed_deliveries', 1)
            ->assertJsonPath('data.summary.delivered_quantity', 9)
            ->assertJsonPath('data.summary.submitted_expenses', 1)
            ->assertJsonPath('data.summary.submitted_collections', 1)
            ->assertJsonPath('data.summary.assigned_route', 'Taunggyi North');
    }

    public function test_dashboard_endpoints_enforce_app_permissions()
    {
        $this->seed();
        $this->getJson('/api/dashboards/owner')->assertUnauthorized();
        $this->getJson('/api/mobile/dashboard')->assertUnauthorized();

        $this->actingAs(User::where('email', 'client@valley.test')->firstOrFail());
        $this->getJson('/api/dashboards/owner')->assertForbidden();

        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $this->getJson('/api/mobile/dashboard')->assertForbidden();
        $this->getJson('/api/dashboards/unknown')->assertNotFound();
    }

    public function test_office_dashboards_accept_a_date_range()
    {
        $this->seed();
        $this->actingAs(User::where('email', 'office@valley.test')->firstOrFail());
        $query = '?date_from=2026-08-01&date_to=2026-08-31';

        $this->getJson('/api/dashboards/sales'.$query)
            ->assertOk()
            ->assertJsonPath('data.summary.sales', 117000)
            ->assertJsonPath('data.summary.target', 150000);
        $this->getJson('/api/dashboards/delivery'.$query)
            ->assertOk()
            ->assertJsonPath('data.summary.deliveries', 3);
        $this->getJson('/api/dashboards/finance'.$query)
            ->assertOk()
            ->assertJsonPath('data.summary.revenue', 117000);
        $this->getJson('/api/dashboard-charts/owner'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data.sales_trend')
            ->assertJsonCount(31, 'data.cash_flow');
        $this->getJson('/api/dashboards/sales?date_from=2026-08-31&date_to=2026-08-01')
            ->assertUnprocessable();
    }
}
