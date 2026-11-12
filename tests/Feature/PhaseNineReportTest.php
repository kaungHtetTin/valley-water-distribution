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
        $this->seed([\Database\Seeders\SetupSeeder::class, \Database\Seeders\DemoSeeder::class]);
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
        $this->get('/api/reports/operations/export?section=customer_sales')->assertForbidden();
    }

    public function test_customer_sales_csv_exports_invoice_items_and_original_order_creator_with_all_filters(): void
    {
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $creator = User::where('email', 'sales@valley.test')->firstOrFail();
        $customer = DB::table('customers')->where('code', 'CUS-0001')->first();
        $warehouseId = DB::table('warehouses')->where('code', 'WH-TGI')->value('id');
        $driverId = DB::table('employees')->where('code', 'DRV-001')->value('id');
        $vehicleId = DB::table('vehicles')->where('code', 'VEH-001')->value('id');
        $match = $this->exportInvoice('MATCH', $customer->id, $creator->id, $customer->route_id);
        $differentCustomer = DB::table('customers')->where('code', 'CUS-0002')->value('id');
        $this->exportInvoice('OTHER-CUSTOMER', $differentCustomer, $creator->id, $customer->route_id);
        $this->exportInvoice('OTHER-CREATOR', $customer->id, User::where('email', 'owner@valley.test')->value('id'), $customer->route_id);
        $this->exportInvoice('OTHER-ROUTE', $customer->id, $creator->id, DB::table('routes')->where('id', '!=', $customer->route_id)->value('id'));
        $this->exportInvoice('OTHER-DATE', $customer->id, $creator->id, $customer->route_id, '2026-08-28');
        $this->exportInvoice('CANCELLED', $customer->id, $creator->id, $customer->route_id, '2026-08-29', 'cancelled');
        $this->exportInvoice('NO-DELIVERY', $customer->id, $creator->id, $customer->route_id);
        foreach (DB::table('invoices')->where('code', 'like', 'INV-CSV-%')->whereNotIn('id', [$match])
            ->where('code', '!=', 'INV-CSV-NO-DELIVERY')->pluck('id') as $invoiceId) {
            DB::table('deliveries')->insert([
                'code' => 'DEL-CSV-EXCLUDED-'.$invoiceId, 'invoice_id' => $invoiceId, 'customer_id' => $customer->id,
                'warehouse_id' => $warehouseId, 'route_id' => $customer->route_id, 'driver_id' => $driverId,
                'vehicle_id' => $vehicleId, 'planned_date' => '2026-08-29', 'status' => 'delivered',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // A replanned invoice can have multiple delivery records. It must export
        // each invoice item once, and all logistics filters must match one stop.
        foreach (['delivered', 'on_route'] as $index => $status) {
            DB::table('deliveries')->insert([
                'code' => 'DEL-CSV-'.$index, 'invoice_id' => $match, 'customer_id' => $customer->id,
                'warehouse_id' => $warehouseId, 'route_id' => $customer->route_id, 'driver_id' => $driverId,
                'vehicle_id' => $vehicleId, 'planned_date' => '2026-08-29', 'status' => $status,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $filters = ['date_from' => '2026-08-29', 'date_to' => '2026-08-29', 'route_id' => $customer->route_id,
            'customer_id' => $customer->id, 'employee_id' => $creator->employee_id, 'warehouse_id' => $warehouseId,
            'driver_id' => $driverId, 'vehicle_id' => $vehicleId];
        $query = http_build_query($filters);
        $report = $this->getJson('/api/reports/operations?'.$query)->assertOk();
        $report->assertJsonPath('data.summary.sales', 5500)->assertJsonCount(1, 'data.breakdowns.customer_sales')
            ->assertJsonPath('data.breakdowns.customer_sales.0.invoices', 1)
            ->assertJsonPath('data.breakdowns.product_sales.0.amount', 5500);
        $response = $this->get('/api/reports/operations/export?'.$query.'&section=customer_sales')->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('valley-customer_sales-items-2026-08-29-2026-08-29.csv');
        $rows = $this->csvRows($response->streamedContent());
        $this->assertCount(2, $rows);
        $this->assertEquals(['sale', 'foc'], array_column($rows, 'Item type'));
        $this->assertEquals(['2', '1'], array_map(fn ($row) => (string) (int) $row['Quantity'], $rows));
        foreach ($rows as $row) {
            $this->assertEquals('INV-CSV-MATCH', $row['Invoice code']);
            $this->assertEquals('ORD-CSV-MATCH', $row['Order code']);
            $this->assertEquals($customer->code, $row['Customer code']);
            $this->assertEquals('VAL-5G', $row['Product SKU']);
            $this->assertEquals('ရေသန့်, "5 Gallon"', $row['Product name']);
            $this->assertEquals($creator->name, $row['Order created by']);
            $this->assertEquals($creator->id, $row['Creator user ID']);
            $this->assertEquals($creator->role, $row['Creator role']);
            $this->assertEquals('sales', $row['Order source']);
        }
        $this->assertEquals(500, $rows[0]['Line discount (MMK)']);
        $this->assertEquals($report->json('data.summary.sales'), array_sum(array_column($rows, 'Line total (MMK)')));

        foreach (['warehouse_id' => DB::table('warehouses')->where('id', '!=', $warehouseId)->value('id'),
            'vehicle_id' => DB::table('vehicles')->where('id', '!=', $vehicleId)->value('id'),
            'driver_id' => DB::table('employees')->where('id', '!=', $driverId)->value('id')] as $key => $otherId) {
            $query = http_build_query(array_replace($filters, [$key => $otherId]));
            $response = $this->get('/api/reports/operations/export?'.$query.'&section=customer_sales')->assertOk();
            $this->assertSame([], $this->csvRows($response->streamedContent()));
            $this->getJson('/api/reports/operations?'.$query)->assertOk()->assertJsonPath('data.summary.sales', 0);
        }
    }

    public function test_customer_sales_csv_retains_headers_for_empty_exports_and_missing_creator(): void
    {
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $customer = DB::table('customers')->where('code', 'CUS-0001')->first();
        $invoiceId = $this->exportInvoice('NO-CREATOR', $customer->id, null, $customer->route_id);
        $response = $this->get('/api/reports/operations/export?section=customer_sales&date_from=2026-08-29&date_to=2026-08-29')->assertOk();
        $rows = $this->csvRows($response->streamedContent());
        $this->assertCount(2, $rows);
        $this->assertSame('', $rows[0]['Order created by']);
        $this->assertEquals('INV-CSV-NO-CREATOR', $rows[0]['Invoice code']);
        DB::table('invoices')->where('id', $invoiceId)->update(['status' => 'cancelled']);
        $response = $this->get('/api/reports/operations/export?section=customer_sales&date_from=2026-08-29&date_to=2026-08-29')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Product SKU"', $csv);
        $this->assertSame([], $this->csvRows($csv));
    }

    private function exportInvoice(string $suffix, int $customerId, ?int $creatorId, int $routeId, string $date = '2026-08-29', string $status = 'delivered'): int
    {
        $orderId = DB::table('orders')->insertGetId([
            'code' => 'ORD-CSV-'.$suffix, 'customer_id' => $customerId, 'route_id' => $routeId, 'source_app' => 'sales',
            'created_by' => $creatorId, 'order_date' => $date, 'status' => $status, 'payment_type' => 'cash', 'subtotal' => 6000,
            'discount_total' => 500, 'total' => 5500, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoiceId = DB::table('invoices')->insertGetId([
            'code' => 'INV-CSV-'.$suffix, 'order_id' => $orderId, 'customer_id' => $customerId, 'route_id' => null,
            'invoice_date' => $date, 'status' => $status, 'subtotal' => 6000, 'discount_total' => 500, 'total' => 5500,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['sale', 'foc'] as $type) {
            DB::table('invoice_items')->insert([
                'invoice_id' => $invoiceId, 'product_id' => DB::table('products')->where('sku', 'VAL-5G')->value('id'),
                'product_sku' => 'VAL-5G', 'product_name' => 'ရေသန့်, "5 Gallon"', 'unit' => 'bottle', 'item_type' => $type,
                'quantity' => $type === 'sale' ? 2 : 1, 'unit_price' => $type === 'sale' ? 3000 : 0,
                'discount_amount' => $type === 'sale' ? 500 : 0, 'line_total' => $type === 'sale' ? 5500 : 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return $invoiceId;
    }

    private function csvRows(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $headers = fgetcsv($stream, escape: '');
        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = array_combine($headers, $row);
        }
        fclose($stream);
        return $rows;
    }
}
