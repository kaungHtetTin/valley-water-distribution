<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseNineReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-31 12:00:00');
        $this->seed();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_office_can_run_consolidated_monthly_operations_report(): void
    {
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());

        $this->getJson('/api/reports/operations?date_from=2026-08-01&date_to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.period.grouping', 'day')
            ->assertJsonPath('data.summary.sales', 117000)
            ->assertJsonPath('data.summary.orders', 5)
            ->assertJsonPath('data.summary.stock_value', 1070000)
            ->assertJsonStructure(['data' => [
                'summary' => ['sales', 'orders', 'average_order_value', 'stock_in', 'stock_out', 'cash_in', 'cash_out', 'expenses', 'payroll', 'deliveries', 'delivery_completion', 'new_customers'],
                'trend' => [['label', 'sales', 'cash_in', 'cash_out', 'expenses']],
                'breakdowns' => ['route_sales', 'customer_sales', 'product_sales', 'expenses', 'payroll', 'drivers', 'stock'],
                'meta' => ['routes', 'customers', 'warehouses', 'employees', 'drivers', 'vehicles'],
            ]]);
    }

    public function test_report_filters_and_csv_export_use_the_same_scope(): void
    {
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $routeId = DB::table('routes')->where('code', 'TGI-N')->value('id');
        $warehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');

        $response = $this->getJson("/api/reports/operations?date_from=2026-08-01&date_to=2026-08-31&route_id={$routeId}&warehouse_id={$warehouseId}")
            ->assertOk()
            ->assertJsonPath('data.filters.route_id', (int) $routeId)
            ->assertJsonPath('data.filters.warehouse_id', (int) $warehouseId);

        $this->assertNotEmpty($response->json('data.breakdowns.route_sales'));

        $this->get("/api/reports/operations/export?date_from=2026-08-01&date_to=2026-08-31&route_id={$routeId}&section=route_sales")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload();
    }

    public function test_reports_require_office_dashboard_permission(): void
    {
        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());

        $this->getJson('/api/reports/operations')->assertForbidden();
        $this->get('/api/reports/operations/export')->assertForbidden();
    }
}
