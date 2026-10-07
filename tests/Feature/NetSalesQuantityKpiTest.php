<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NetSalesQuantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NetSalesQuantityKpiTest extends TestCase
{
    use RefreshDatabase;

    private function salesFixture(): array
    {
        $sales = User::where('email', 'sales@valley.test')->firstOrFail();
        $product = DB::table('products')->first();
        $orderId = DB::table('orders')->insertGetId(['code' => 'QTY-SALE', 'order_date' => '2026-10-07', 'created_by' => $sales->id, 'status' => 'confirmed']);
        $item = ['order_id' => $orderId, 'product_id' => $product->id, 'product_sku' => $product->sku, 'product_name' => $product->name, 'quantity' => 10.5, 'item_type' => 'sale', 'unit_price' => 90000];
        $saleItemId = DB::table('order_items')->insertGetId($item);
        $focItemId = DB::table('order_items')->insertGetId(array_replace($item, ['item_type' => 'foc', 'quantity' => 9, 'unit_price' => 0]));
        $invoiceId = DB::table('invoices')->insertGetId(['code' => 'QTY-INVOICE', 'order_id' => $orderId, 'invoice_date' => '2026-10-07', 'status' => 'issued', 'total' => 945000]);
        $invoiceItem = ['invoice_id' => $invoiceId, 'product_id' => $product->id, 'product_sku' => $product->sku, 'product_name' => $product->name, 'quantity' => 10.5, 'item_type' => 'sale', 'unit_price' => 90000];
        DB::table('invoice_items')->insert($invoiceItem);
        DB::table('invoice_items')->insert(array_replace($invoiceItem, ['item_type' => 'foc', 'quantity' => 9, 'unit_price' => 0]));
        $returnId = DB::table('orders')->insertGetId(['code' => 'QTY-RETURN', 'original_order_id' => $orderId, 'order_date' => '2026-10-08', 'status' => 'confirmed']);
        DB::table('order_items')->insert(array_replace($item, ['order_id' => $returnId, 'original_order_item_id' => $saleItemId, 'item_type' => 'sales_return', 'quantity' => 2.25]));
        DB::table('order_items')->insert(array_replace($item, ['order_id' => $returnId, 'original_order_item_id' => $focItemId, 'item_type' => 'sales_return', 'quantity' => 3, 'unit_price' => 0]));

        return compact('sales', 'invoiceId', 'returnId', 'orderId');
    }

    public function test_net_quantity_excludes_foc_cancelled_invoices_and_out_of_period_returns(): void
    {
        $this->seed();
        $fixture = $this->salesFixture();
        $source = NetSalesQuantity::forEmployee($fixture['sales']->employee_id, '2026-10-01', '2026-10-31');
        $this->assertEquals(8.25, $source['quantity']);
        $this->assertSame(2, $source['count']);
        DB::table('invoices')->where('id', $fixture['invoiceId'])->update(['total' => 1]);
        $this->assertEquals(8.25, NetSalesQuantity::forEmployee($fixture['sales']->employee_id, '2026-10-01', '2026-10-31')['quantity']);
        DB::table('orders')->where('id', $fixture['returnId'])->update(['order_date' => '2026-11-01']);
        $this->assertEquals(10.5, NetSalesQuantity::forEmployee($fixture['sales']->employee_id, '2026-10-01', '2026-10-31')['quantity']);
        DB::table('invoices')->where('id', $fixture['invoiceId'])->update(['status' => 'cancelled']);
        $this->assertEquals(0, NetSalesQuantity::forEmployee($fixture['sales']->employee_id, '2026-10-01', '2026-10-31')['quantity']);
        $this->assertEquals(0, NetSalesQuantity::forEmployee($fixture['sales']->employee_id, '2026-11-01', '2026-11-30')['quantity']);
    }

    public function test_quantity_targets_actuals_scores_and_mobile_and_reports_use_units(): void
    {
        $this->seed();
        $fixture = $this->salesFixture();
        $metric = DB::table('kpi_template_metrics')->where('code', 'SAL-NET-SALES')->first();
        $this->assertSame('units', $metric->unit);
        $this->assertEquals(4000, $metric->default_target);
        DB::table('kpi_staff_target_items')->where('kpi_template_metric_id', $metric->id)->update(['target_value' => 20]);
        DB::table('sales_targets')->insert(['employee_id' => $fixture['sales']->employee_id, 'target_month' => '2026-10-01', 'target_amount' => 900000]);
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $this->postJson('/api/kpi-reviews/generate', ['month' => '2026-10', 'employee_type' => 'sales'])->assertOk();
        $resultId = DB::table('kpi_results')->where('employee_id', $fixture['sales']->employee_id)->value('id');
        $item = DB::table('kpi_result_items')->where('kpi_result_id', $resultId)->where('kpi_template_metric_id', $metric->id)->first();
        $this->assertEquals(20, $item->target_value);
        $this->assertEquals(8.25, $item->actual_value);
        $this->assertEquals(41.25, $item->achievement_percent);
        $this->assertEquals(14.44, $item->weighted_score);
        $this->getJson('/api/kpi-reviews/'.$resultId)->assertOk()->assertJsonFragment(['code' => 'SAL-NET-SALES', 'unit' => 'units']);
        $report = $this->getJson('/api/kpi-reports?month=2026-10&employee_id='.$fixture['sales']->employee_id)->assertOk();
        $metricReport = collect($report->json('data.metric_breakdown'))->firstWhere('code', 'SAL-NET-SALES');
        $this->assertSame('units', $metricReport['unit']);
        $this->assertEquals(8.25, $metricReport['actual_average']);
        $this->actingAs($fixture['sales'])->getJson('/api/mobile/kpi?month=2026-10')->assertOk()->assertJsonFragment(['code' => 'SAL-NET-SALES', 'unit' => 'units']);
    }

    public function test_migration_converts_drafts_and_targets_and_preserves_finalized_currency_reviews(): void
    {
        $this->seed();
        $fixture = $this->salesFixture();
        $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail());
        $this->postJson('/api/kpi-reviews/generate', ['month' => '2026-10', 'employee_type' => 'sales'])->assertOk();
        $metric = DB::table('kpi_template_metrics')->where('code', 'SAL-NET-SALES')->first();
        $draftId = DB::table('kpi_results')->where('employee_id', $fixture['sales']->employee_id)->value('id');
        $finalId = DB::table('kpi_results')->where('id', '!=', $draftId)->value('id');
        DB::table('kpi_results')->where('id', $finalId)->update(['status' => 'approved', 'overall_score' => 80, 'bonus_amount' => 32000]);
        DB::table('kpi_template_metrics')->where('id', $metric->id)->update(['unit' => 'MMK', 'default_target' => 4000000]);
        DB::table('kpi_staff_target_items')->where('kpi_template_metric_id', $metric->id)->update(['target_value' => 4000000]);
        DB::table('kpi_result_items')->where('kpi_template_metric_id', $metric->id)->update(['target_value' => 4000000, 'actual_value' => 2000000]);
        Schema::table('kpi_result_items', fn ($table) => $table->dropColumn('unit_snapshot'));
        $migration = require database_path('migrations/2026_10_07_000003_use_stock_quantity_for_net_sales_kpi.php');
        $migration->up();
        $this->assertDatabaseHas('kpi_result_items', ['kpi_result_id' => $draftId, 'kpi_template_metric_id' => $metric->id, 'target_value' => 4000, 'actual_value' => 8.25, 'unit_snapshot' => 'units']);
        $this->assertDatabaseHas('kpi_result_items', ['kpi_result_id' => $finalId, 'kpi_template_metric_id' => $metric->id, 'target_value' => 4000000, 'actual_value' => 2000000, 'unit_snapshot' => 'MMK']);
        $this->assertDatabaseHas('kpi_results', ['id' => $finalId, 'overall_score' => 80, 'bonus_amount' => 32000]);
        $report = $this->getJson('/api/kpi-reports?period=year&year=2026')->assertOk();
        $this->assertEqualsCanonicalizing(['MMK', 'units'], collect($report->json('data.metric_breakdown'))->where('code', 'SAL-NET-SALES')->pluck('unit')->all());
    }
}
