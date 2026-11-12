<?php

// Local verification only. Never refreshes, truncates, or seeds the working database.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\DB;

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    throw new RuntimeException('Sale lifecycle verification is restricted to APP_ENV=local.');
}

$auditRun = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--audit=')) {
        $auditRun = substr($argument, 8);
        if (! preg_match('/^E2E-\d{6}-\d{6}$/', $auditRun)) { throw new RuntimeException('Invalid audit run ID.'); }
    }
}
if (! in_array('--run', $argv, true) && ! $auditRun) {
    echo json_encode([
        'database_driver' => config('database.default'),
        'users' => User::query()->get(['id', 'role', 'employee_id', 'customer_id']),
        'employees' => DB::table('employees')->get(['id', 'code', 'employee_type', 'assigned_route_id', 'is_active']),
        'products' => DB::table('products')->where('is_active', true)->get(['id', 'sku', 'name']),
        'routes' => DB::table('routes')->where('is_active', true)->get(['id', 'area_id', 'code']),
        'warehouses' => DB::table('warehouses')->get(['id', 'code', 'name']),
        'vehicles' => DB::table('vehicles')->where('is_active', true)->get(['id', 'code']),
    ], JSON_PRETTY_PRINT).PHP_EOL;
    exit;
}

class SaleLifecycleProbe
{
    use \Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
    use \Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;

    public function __construct(public $app) {}

    public function requestAs(User $actor, string $method, string $path, array $payload = [])
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($actor);
        return $this->json($method, $path, $payload);
    }
}

$probe = new SaleLifecycleProbe($app);
// Authentication, authorization, binding, validation, transactions, and audit middleware
// remain enabled. Only browser CSRF transport is excluded from this CLI harness.
$probe->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
config(['session.driver' => 'array', 'cache.default' => 'array']);
// Requests dispatch directly through the kernel; Apache's /valley/public prefix
// is not part of Laravel's internal route paths.
config(['app.url' => 'http://localhost']);
$app['url']->forceRootUrl('http://localhost');
$run = $auditRun ?: 'E2E-'.now('Asia/Yangon')->format('ymd-His');
$reportDirectory = dirname(__DIR__).'/artifacts/verification/'.$run;
if (! is_dir($reportDirectory)) { mkdir($reportDirectory, 0777, true); }
$report = ['run' => $run, 'started_at' => now('Asia/Yangon')->toIso8601String(), 'database_driver' => config('database.default'),
    'mode' => 'Persistent local database; real HTTP kernel; authenticated actors; CSRF transport excluded',
    'checks' => [], 'records' => [], 'limitations' => ['Browser tool connection failed: missing sandboxPolicy. UI clicks, message colors, printing dialogs, and device GPS were not verified.']];
if ($auditRun) {
    $report = json_decode(file_get_contents($reportDirectory.'/report.json'), true, flags: JSON_THROW_ON_ERROR);
    $previousAudit = array_values(array_filter($report['checks'], fn ($check) => str_starts_with($check['label'], 'Audit ')));
    if ($previousAudit) {
        $report['previous_audit_attempts'][] = ['checks' => $previousAudit, 'failure' => $report['failure'] ?? null];
        $report['checks'] = array_values(array_filter($report['checks'], fn ($check) => ! str_starts_with($check['label'], 'Audit ')));
    }
    unset($report['failure']);
}
$saveReport = function () use (&$report, $reportDirectory) {
    file_put_contents($reportDirectory.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
};
$check = function (string $label, bool $passed, mixed $details = null) use (&$report, $saveReport) {
    $report['checks'][] = ['label' => $label, 'passed' => $passed, 'details' => $details];
    $saveReport();
    if (! $passed) { throw new RuntimeException($label.' failed: '.json_encode($details)); }
};
$request = function (string $label, User $actor, string $method, string $path, array $payload = [], int $expected = 200) use ($probe, $check) {
    $response = $probe->requestAs($actor, $method, $path, $payload);
    $check($label, $response->status() === $expected, ['method' => $method, 'path' => $path, 'expected' => $expected, 'actual' => $response->status(), 'message' => $response->json('message'), 'errors' => $response->json('errors')]);
    return $response->json('data');
};
$equal = function (string $label, mixed $actual, mixed $expected) use ($check) {
    $check($label, is_numeric($actual) && is_numeric($expected) ? abs((float) $actual - (float) $expected) < 0.001 : $actual === $expected, compact('actual', 'expected'));
};

try {
    $owner = User::where('role', 'Owner')->firstOrFail();
    $sales = User::where('role', 'Sale Representative')->whereNotNull('employee_id')->firstOrFail();
    $driver = User::where('role', 'Driver')->whereNotNull('employee_id')->firstOrFail();
    if ($auditRun) {
        $warehouseId = $report['warehouse_id'];
        $customers = collect($report['records'])->where('type', 'customer');
        $customerIds = $customers->pluck('id')->all();
        $orders = DB::table('orders')->whereIn('customer_id', $customerIds)->get();
        $invoices = DB::table('invoices')->whereIn('order_id', $orders->pluck('id'))->get();
        // Later driver receipts are customer-level; the mobile endpoint does not
        // allocate them to an invoice. Include those along with delivery receipts.
        $collections = DB::table('collections')->whereIn('customer_id', $customerIds)->get();
        $transactions = DB::table('financial_transactions')->where(function ($query) use ($collections, $orders) {
            $query->where(fn ($query) => $query->where('reference_type', 'collection')->whereIn('reference_id', $collections->pluck('id')))
                ->orWhere(fn ($query) => $query->where('reference_type', 'sales_return')->whereIn('reference_id', $orders->whereNotNull('original_order_id')->pluck('id')));
        })->get();
        foreach ($collections as $collection) {
            $equal('Audit collection '.$collection->code.' approved', $collection->status, 'approved');
            $posts = $transactions->where('reference_type', 'collection')->where('reference_id', $collection->id);
            $equal('Audit collection '.$collection->code.' posts exactly once', $posts->count(), 1);
            $equal('Audit collection '.$collection->code.' posted amount matches', (float) $posts->sum('amount'), (float) $collection->amount);
            $equal('Audit collection '.$collection->code.' posted to correct book', $posts->first()->book_type, $collection->payment_method);
        }
        $equal('Audit total cash inflow', (float) $transactions->where('book_type', 'cash')->where('direction', 'in')->sum('amount'), 62500);
        $equal('Audit total bank inflow', (float) $transactions->where('book_type', 'bank')->where('direction', 'in')->sum('amount'), 9000);
        $equal('Audit bank refund posted once', $transactions->where('reference_type', 'sales_return')->where('direction', 'out')->count(), 1);
        $equal('Audit bank refund amount', (float) $transactions->where('reference_type', 'sales_return')->where('direction', 'out')->sum('amount'), 6000);
        $equal('Audit pending driver cash for test invoices', $collections->where('status', 'submitted')->count(), 0);
        $movements = DB::table('stock_movements')->where('warehouse_id', $warehouseId)->get();
        $balance = (float) DB::table('stock_balances')->where('warehouse_id', $warehouseId)->sum('quantity');
        $equal('Audit inventory ledger reconciles to warehouse balance', (float) $movements->sum('signed_quantity'), $balance);
        $equal('Audit final isolated warehouse stock', $balance, 175);
        $equal('Audit delivery damage is not sellable stock', (float) $movements->where('movement_type', 'delivery_damage')->sum('signed_quantity'), 0);
        $equal('Audit returned damage is not sellable stock', (float) $movements->where('movement_type', 'sales_return_damage')->sum('signed_quantity'), 0);
        foreach ($customers as $customerRecord) {
            $ledger = $request('Audit customer ledger '.$customerRecord['scenario'], $owner, 'GET', '/api/finance/customers/'.$customerRecord['id'].'/ledger');
            $equal('Audit customer '.$customerRecord['scenario'].' outstanding balance', $ledger['summary']['outstanding_amount'], 0);
            $equal('Audit customer '.$customerRecord['scenario'].' available credit', $ledger['summary']['customer_credit_amount'], $customerRecord['scenario'] === 'CREDIT' ? 3000 : 0);
        }
        foreach (collect($report['records'])->where('type', 'trip') as $tripRecord) {
            $data = $request('Audit reload trip '.$tripRecord['scenario'], $driver, 'GET', '/api/mobile/deliveries/'.$tripRecord['delivery_id']);
            $equal('Audit trip '.$tripRecord['scenario'].' no longer active', in_array($data['trip']['status'], ['delivered', 'partially_delivered', 'failed', 'cancelled']), true);
        }
        $logs = DB::table('audit_logs')->where('changes', 'like', '%'.$run.'%')->get(['id', 'app', 'method', 'path', 'status_code', 'created_at']);
        foreach (['sales', 'client', 'driver', 'office'] as $source) {
            $equal('Audit persisted mutation events for '.$source, $logs->where('app', $source)->isNotEmpty(), true);
        }
        $request('Audit office live delivery map', $owner, 'GET', '/api/deliveries/live-map');
        $supervisor = User::where('role', 'Sale Manager')->first();
        if ($supervisor) {
            $request('Audit supervisor team order visibility', $supervisor, 'GET', '/api/mobile/orders?search='.$run);
        }
        foreach (['owner', 'sales', 'stock', 'delivery', 'finance'] as $dashboard) {
            $request('Audit '.$dashboard.' dashboard', $owner, 'GET', '/api/dashboards/'.$dashboard);
        }
        $report['accounting_audit'] = ['collections' => $collections, 'transactions' => $transactions, 'customer_count' => count($customerIds),
            'order_count_including_returns' => $orders->count(), 'invoice_count' => $invoices->count(), 'audit_events' => $logs, 'stock_balance' => $balance];
        $report['audited_at'] = now('Asia/Yangon')->toIso8601String();
        $report['result'] = 'passed';
        $saveReport();
        echo json_encode(['run' => $run, 'result' => 'passed', 'checks' => count($report['checks']), 'report' => $reportDirectory.'/report.json'], JSON_PRETTY_PRINT).PHP_EOL;
        exit;
    }
    $route = DB::table('routes')->find(DB::table('employees')->where('id', $sales->employee_id)->value('assigned_route_id'));
    $vehicle = DB::table('vehicles')->where('is_active', true)->first();
    $product = DB::table('products')->where('sku', 'VAL-5G')->where('is_active', true)->first();
    $priceType = DB::table('price_types')->where('is_default', true)->first();
    if (! $route || ! $vehicle || ! $product || ! $priceType) { throw new RuntimeException('Active route, vehicle, VAL-5G product and default price are required.'); }
    $today = now('Asia/Yangon')->toDateString();
    $warehouseId = DB::table('warehouses')->insertGetId(['area_id' => $route->area_id, 'code' => $run, 'name' => 'TEST ONLY '.$run,
        'address' => 'Isolated lifecycle test warehouse', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $report['warehouse_id'] = $warehouseId;
    $report['actors'] = ['owner_id' => $owner->id, 'sales_id' => $sales->id, 'driver_id' => $driver->id];
    $request('Opening stock in isolated test warehouse', $owner, 'POST', '/api/stock/receipts', [
        'movement_type' => 'opening', 'warehouse_id' => $warehouseId, 'movement_date' => $today, 'reference_code' => $run,
        'notes' => $run.' TEST ONLY opening stock', 'items' => [['product_id' => $product->id, 'quantity' => 200, 'unit_cost' => 1000]],
    ], 201);
    $stock = fn () => (float) DB::table('stock_balances')->where('warehouse_id', $warehouseId)->where('product_id', $product->id)->value('quantity');
    $equal('Opening balance is 200 units', $stock(), 200);
    $customer = function (string $scenario) use ($run, $route, $priceType, &$report) {
        $id = DB::table('customers')->insertGetId(['code' => $run.'-'.$scenario, 'shop_name' => 'TEST '.$scenario.' '.$run,
            'contact_name' => 'Lifecycle test', 'phone' => 'TEST-ONLY', 'address' => 'Test address '.$run,
            'area_id' => $route->area_id, 'route_id' => $route->id, 'price_type_id' => $priceType->id, 'credit_limit' => 1000000,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $report['records'][] = ['type' => 'customer', 'scenario' => $scenario, 'id' => $id];
        return $id;
    };
    $createOrder = function (string $scenario, User $actor, int $customerId, int $quantity = 10, bool $draft = false, bool $foc = false, bool $office = false) use ($request, $run, $today, $product, &$report, $equal) {
        $payload = ['customer_id' => $customerId, 'order_date' => $today, 'requested_delivery_date' => $today, 'notes' => $run.' TEST '.$scenario,
            'save_as' => $draft ? 'draft' : 'pending', 'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'item_type' => 'sale']]];
        if ($foc) { $payload['items'][] = ['product_id' => $product->id, 'quantity' => 2, 'item_type' => 'foc']; }
        $data = $request($scenario.' order submission', $actor, 'POST', $office ? '/api/orders' : '/api/mobile/orders', $payload, 201);
        $equal($scenario.' initial order state', $data['order']['status'], $draft ? 'draft' : 'pending');
        $report['records'][] = ['type' => 'order', 'scenario' => $scenario, 'id' => $data['order']['id'], 'code' => $data['order']['code']];
        return [$data, $payload];
    };
    $confirm = function (string $scenario, int $id) use ($request, $owner, $equal, &$report) {
        $data = $request($scenario.' office confirmation and invoice issue', $owner, 'POST', "/api/orders/$id/confirm");
        $equal($scenario.' order invoiced', $data['order']['status'], 'invoiced');
        $equal($scenario.' invoice issued', $data['financial_record']['status'], 'issued');
        $request($scenario.' duplicate confirmation rejected', $owner, 'POST', "/api/orders/$id/confirm", [], 409);
        $report['records'][] = ['type' => 'invoice', 'scenario' => $scenario, 'id' => $data['financial_record']['id'], 'code' => $data['financial_record']['code']];
        return $data['financial_record']['id'];
    };
    $assign = function (string $scenario, array $invoiceIds) use ($request, $owner, $driver, $vehicle, $warehouseId, $today, $run, &$report) {
        $data = $request($scenario.' trip assignment', $owner, 'POST', '/api/deliveries', ['invoice_ids' => $invoiceIds,
            'warehouse_id' => $warehouseId, 'driver_id' => $driver->employee_id, 'vehicle_id' => $vehicle->id, 'planned_date' => $today, 'notes' => $run.' TEST '.$scenario], 201);
        $report['records'][] = ['type' => 'trip', 'scenario' => $scenario, 'id' => $data['trip']['id'], 'code' => $data['trip']['code'], 'delivery_id' => $data['delivery']['id']];
        return $data;
    };
    $start = function (string $scenario, array $trip, int $loaded) use ($request, $driver, $sales, $equal, $stock) {
        $id = $trip['delivery']['id']; $before = $stock();
        $request($scenario.' sales cannot confirm driver load', $sales, 'POST', "/api/mobile/deliveries/$id/confirm-loading", [], 403);
        $request($scenario.' cannot end unfinished trip', $driver, 'POST', "/api/mobile/deliveries/$id/complete-trip", [], 409);
        $data = $request($scenario.' driver confirms loading', $driver, 'POST', "/api/mobile/deliveries/$id/confirm-loading");
        $equal($scenario.' load deducted from warehouse', $stock(), $before - $loaded);
        $request($scenario.' repeat loading does not double-issue', $driver, 'POST', "/api/mobile/deliveries/$id/confirm-loading");
        $equal($scenario.' loading is idempotent', $stock(), $before - $loaded);
        $data = $request($scenario.' starts route', $driver, 'POST', "/api/mobile/deliveries/$id/start-route");
        $equal($scenario.' trip is on route', $data['trip']['status'], 'on_route');
        return $data;
    };
    $finish = function (string $scenario, array $trip, int $sold, int $damaged, ?string $method, ?string $status = null, int $foc = 0, int $discount = 0) use ($request, $driver, $product, $run, $equal) {
        $id = $trip['delivery']['id']; $item = $trip['items'][0];
        $payload = ['final_sale' => true, 'status' => $status ?? 'delivered', 'settlement_method' => $method, 'payment_reference' => $method === 'bank_office' ? $run.'-BANK' : null,
            'notes' => $run.' TEST '.($status === 'failed' ? 'shop refused entire delivery' : $scenario), 'modification_note' => $run.' TEST final quantities', 'order_discount' => $discount, 'items' => []];
        if ($sold > 0 || $damaged > 0) { $payload['items'][] = ['id' => $item['id'], 'product_id' => $product->id, 'item_type' => 'sale', 'unit_price' => 3000, 'delivered_quantity' => $sold, 'damaged_quantity' => $damaged]; }
        if ($foc > 0) { $payload['items'][] = ['product_id' => $product->id, 'item_type' => 'foc', 'unit_price' => 0, 'delivered_quantity' => $foc, 'damaged_quantity' => 0]; }
        $data = $request($scenario.' complete stop', $driver, 'POST', "/api/mobile/deliveries/$id/complete", $payload);
        $equal($scenario.' stays active pending closeout', $data['trip']['status'], 'on_route');
        $equal($scenario.' final amount', $data['delivery']['settlement_amount'], $sold * 3000 - $discount);
        $request($scenario.' duplicate completion rejected', $driver, 'POST', "/api/mobile/deliveries/$id/complete", $payload, 409);
        return $data;
    };
    $close = function (string $scenario, array $trip, int $returned, string $result) use ($request, $driver, $equal, $stock) {
        $id = $trip['delivery']['id']; $before = $stock();
        $data = $request($scenario.' confirms stock return and ends trip', $driver, 'POST', "/api/mobile/deliveries/$id/complete-trip");
        $equal($scenario.' final trip state', $data['trip']['status'], $result);
        $equal($scenario.' only returned good stock restored', $stock(), $before + $returned);
        $request($scenario.' duplicate closeout rejected', $driver, 'POST', "/api/mobile/deliveries/$id/complete-trip", [], 409);
        $history = $request($scenario.' history after closeout', $driver, 'GET', '/api/mobile/deliveries?scope=history');
        $equal($scenario.' appears in history', in_array($trip['delivery']['trip_id'], array_column($history['items'], 'trip_id')), true);
        return $data;
    };

    // Draft -> edit -> submit -> approve -> load -> partial sale/FOC/damage -> cash handover.
    $cid = $customer('PARTIAL');
    [$order, $payload] = $createOrder('PARTIAL', $sales, $cid, 10, true, true);
    $oid = $order['order']['id'];
    $request('Draft cannot be approved', $owner, 'POST', "/api/orders/$oid/confirm", [], 409);
    $payload['save_as'] = 'pending';
    $request('Edit draft and submit', $sales, 'PUT', "/api/mobile/orders/$oid", $payload);
    $invoice = $confirm('PARTIAL', $oid);
    $trip = $start('PARTIAL', $assign('PARTIAL', [$invoice]), 12);
    $id = $trip['delivery']['id'];
    $request('Synthetic driver location event', $driver, 'POST', "/api/mobile/deliveries/$id/location", ['latitude' => 20.78, 'longitude' => 97.03, 'accuracy_m' => 5], 201);
    $request('Synthetic test-customer GPS correction', $driver, 'POST', "/api/mobile/deliveries/$id/customer-location", ['latitude' => 20.78, 'longitude' => 97.03, 'accuracy_m' => 5]);
    $before = $stock();
    $trip = $finish('PARTIAL', $trip, 7, 1, 'cash_driver', 'partially_delivered', 2, 500);
    $equal('Refused goods stay off warehouse balance before closeout', $stock(), $before);
    $equal('Two good units remain on vehicle', $trip['stock'][0]['returned_quantity'], 2);
    $close('PARTIAL', $trip, 2, 'partially_delivered');
    $collection = DB::table('collections')->where('delivery_id', $id)->first();
    $equal('Driver cash held pending office receipt', $collection->status, 'submitted');
    $request('Cash handover wrong amount rejected', $owner, 'POST', "/api/finance/cash-handovers/{$driver->employee_id}/receive", ['collection_ids' => [$collection->id], 'received_amount' => 1], 422);
    $handover = ['collection_ids' => [$collection->id], 'received_amount' => 20500, 'notes' => $run.' TEST cash handover'];
    $request('Office receives exact driver cash', $owner, 'POST', "/api/finance/cash-handovers/{$driver->employee_id}/receive", $handover);
    $request('Duplicate cash handover rejected', $owner, 'POST', "/api/finance/cash-handovers/{$driver->employee_id}/receive", $handover, 409);
    $equal('Cash receipt approved', DB::table('collections')->where('id', $collection->id)->value('status'), 'approved');

    // Client submission and direct office cash settlement.
    $cid = $customer('CLIENT');
    $client = User::create(['name' => 'TEST '.$run, 'email' => strtolower($run).'@example.invalid', 'role' => 'Customer', 'customer_id' => $cid, 'password' => \Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(24)))]);
    [$order] = $createOrder('CLIENT', $client, $cid, 4);
    $clientOid = $order['order']['id'];
    $invoice = $confirm('CLIENT', $clientOid);
    $trip = $start('CLIENT', $assign('CLIENT', [$invoice]), 4);
    $trip = $finish('CLIENT', $trip, 4, 0, 'cash_office');
    $close('CLIENT', $trip, 0, 'delivered');
    $request('Client can view own delivery', $client, 'GET', '/api/mobile/delivery-status/'.$trip['delivery']['id']);
    $request('Client cannot view another customer order', $client, 'GET', "/api/mobile/orders/$oid", [], 404);

    // Bank sale and original-sale refund return.
    $cid = $customer('BANK');
    [$order] = $createOrder('BANK', $owner, $cid, 3, false, false, true);
    $bankOid = $order['order']['id'];
    $invoice = $confirm('BANK', $bankOid);
    $trip = $start('BANK', $assign('BANK', [$invoice]), 3);
    $bid = $trip['delivery']['id'];
    $request('Bank reference required', $driver, 'POST', "/api/mobile/deliveries/$bid/complete", ['final_sale' => true, 'status' => 'delivered', 'settlement_method' => 'bank_office',
        'items' => [['id' => $trip['items'][0]['id'], 'delivered_quantity' => 3, 'damaged_quantity' => 0]]], 422);
    $trip = $finish('BANK', $trip, 3, 0, 'bank_office');
    $close('BANK', $trip, 0, 'delivered');
    $original = DB::table('order_items')->where('order_id', $bankOid)->where('item_type', 'sale')->first();
    $before = $stock();
    $ret = $request('Previously sold goods returned with bank refund', $owner, 'POST', '/api/order-adjustments', ['type' => 'sales_return', 'original_order_id' => $bankOid,
        'return_warehouse_id' => $warehouseId, 'return_settlement_method' => 'bank_refund', 'entry_date' => $today, 'notes' => $run.' TEST sale return',
        'items' => [['original_order_item_id' => $original->id, 'good_quantity' => 1, 'damaged_quantity' => 1]]], 201);
    $equal('Good return restored; damaged return not sellable', $stock(), $before + 1);
    $equal('Refund covers both returned sold units', $ret['record']['refund_amount'], 6000);
    $report['records'][] = ['type' => 'sales_return', 'scenario' => 'BANK', 'id' => $ret['record']['id'], 'code' => $ret['record']['code']];
    $request('Cannot return more than remaining original quantity', $owner, 'POST', '/api/order-adjustments', ['type' => 'sales_return', 'original_order_id' => $bankOid,
        'return_warehouse_id' => $warehouseId, 'return_settlement_method' => 'bank_refund', 'items' => [['original_order_item_id' => $original->id, 'good_quantity' => 2, 'damaged_quantity' => 0]]], 422);

    // Credit limit validation, delivery on credit, later collection, and office handover.
    $cid = $customer('CREDIT');
    [$order] = $createOrder('CREDIT', $sales, $cid, 5);
    $creditOid = $order['order']['id'];
    $invoice = $confirm('CREDIT', $creditOid);
    $trip = $start('CREDIT', $assign('CREDIT', [$invoice]), 5);
    DB::table('customers')->where('id', $cid)->update(['credit_limit' => 1]);
    $id = $trip['delivery']['id'];
    $request('Over-limit credit sale rejected', $driver, 'POST', "/api/mobile/deliveries/$id/complete", ['final_sale' => true, 'status' => 'delivered', 'settlement_method' => 'credit',
        'items' => [['id' => $trip['items'][0]['id'], 'delivered_quantity' => 5, 'damaged_quantity' => 0]]], 422);
    $equal('Rejected credit completion rolls back stop state', DB::table('deliveries')->where('id', $id)->value('status'), 'on_route');
    DB::table('customers')->where('id', $cid)->update(['credit_limit' => 1000000]);
    $trip = $finish('CREDIT', $trip, 5, 0, 'credit');
    $close('CREDIT', $trip, 0, 'delivered');
    $credit = app(\App\Services\CustomerCreditService::class);
    $equal('Credit sale creates receivable', $credit->outstanding($cid), 15000);
    $col = $request('Driver collects later credit payment', $driver, 'POST', '/api/mobile/finance/collections', ['customer_id' => $cid, 'collection_date' => $today,
        'amount' => 15000, 'payment_method' => 'cash', 'reference_no' => $run, 'notes' => $run.' TEST credit collection'], 201);
    $request('Office receives credit collection handover', $owner, 'POST', "/api/finance/cash-handovers/{$driver->employee_id}/receive", ['collection_ids' => [$col['collection_id']], 'received_amount' => 15000, 'notes' => $run]);
    $equal('Paid credit customer balance is zero', $credit->outstanding($cid), 0);
    $original = DB::table('order_items')->where('order_id', $creditOid)->where('item_type', 'sale')->first();
    $request('Paid credit sale return creates customer credit', $owner, 'POST', '/api/order-adjustments', ['type' => 'sales_return', 'original_order_id' => $creditOid,
        'return_warehouse_id' => $warehouseId, 'return_settlement_method' => 'customer_credit', 'entry_date' => $today, 'notes' => $run,
        'items' => [['original_order_item_id' => $original->id, 'good_quantity' => 1, 'damaged_quantity' => 0]]], 201);
    $equal('Overpayment after return becomes customer credit', $credit->customerCredit($cid), 3000);

    // One-stop full refusal: no sale, no collection, stays active until closeout.
    $cid = $customer('REFUSAL');
    [$order] = $createOrder('REFUSAL', $sales, $cid, 6);
    $invoice = $confirm('REFUSAL', $order['order']['id']);
    $trip = $start('REFUSAL', $assign('REFUSAL', [$invoice]), 6);
    $before = $stock();
    $trip = $finish('REFUSAL', $trip, 0, 0, null, 'failed');
    $equal('Full refusal stock remains on vehicle', $stock(), $before);
    $equal('No collection for refused delivery', DB::table('collections')->where('delivery_id', $trip['delivery']['id'])->count(), 0);
    $tasks = $request('Single fully returned stop remains an active task', $driver, 'GET', '/api/mobile/deliveries');
    $equal('Full refusal task available for closeout', in_array($trip['delivery']['id'], array_column($tasks['items'], 'id')), true);
    $close('REFUSAL', $trip, 6, 'failed');

    // Multi-stop trip: rejected first stop forwards stock to the next shop.
    $cid = $customer('MULTI');
    [$o1] = $createOrder('MULTI-FIRST', $sales, $cid, 4);
    [$o2] = $createOrder('MULTI-SECOND', $sales, $cid, 3);
    $trip = $start('MULTI', $assign('MULTI', [$confirm('MULTI-FIRST', $o1['order']['id']), $confirm('MULTI-SECOND', $o2['order']['id'])]), 7);
    $firstId = $trip['delivery']['id']; $secondId = $trip['stops'][1]['id'];
    $finish('MULTI-FIRST', $trip, 0, 0, null, 'failed');
    $request('Cannot close trip while second shop is pending', $driver, 'POST', "/api/mobile/deliveries/$firstId/complete-trip", [], 409);
    $second = $request('Reload second stop after refusal', $driver, 'GET', "/api/mobile/deliveries/$secondId");
    $equal('Second stop can use all seven loaded units', $second['delivery']['loaded_quantity'], 7);
    $trip = $finish('MULTI-SECOND', $second, 5, 0, 'cash_office', 'partially_delivered');
    $close('MULTI', $trip, 2, 'partially_delivered');

    // Cancellation before dispatch leaves stock unchanged.
    $cid = $customer('CANCEL');
    [$order] = $createOrder('CANCEL-PENDING', $sales, $cid, 2);
    $request('Cancel pending sales order', $sales, 'POST', '/api/mobile/orders/'.$order['order']['id'].'/cancel');
    $request('Cancelled order cannot be approved', $owner, 'POST', '/api/orders/'.$order['order']['id'].'/confirm', [], 409);
    [$order] = $createOrder('CANCEL-TRIP', $sales, $cid, 2);
    $trip = $assign('CANCEL-TRIP', [$confirm('CANCEL-TRIP', $order['order']['id'])]);
    $before = $stock();
    $request('Office edits assigned trip', $owner, 'PATCH', '/api/deliveries/'.$trip['delivery']['id'].'/trip', ['warehouse_id' => $warehouseId, 'driver_id' => $driver->employee_id, 'vehicle_id' => $vehicle->id, 'planned_date' => $today, 'notes' => $run]);
    $request('Office cancels trip before loading', $owner, 'POST', '/api/deliveries/'.$trip['delivery']['id'].'/cancel');
    $equal('Cancelling unloaded trip does not change stock', $stock(), $before);

    foreach (['/api/finance/books/cash', '/api/finance/books/bank', '/api/finance/receivables', '/api/stock/card?warehouse_id='.$warehouseId.'&product_id='.$product->id,
        '/api/deliveries?search='.$run] as $path) {
        $request('Read report '.$path, $owner, 'GET', $path);
    }
    $equal('Final warehouse stock reconciles across every scenario', $stock(), 175);
    $report['stock_movements'] = DB::table('stock_movements')->where('warehouse_id', $warehouseId)->get(['id', 'movement_type', 'quantity', 'signed_quantity', 'reference_type', 'reference_id', 'reference_code']);
    $report['finished_at'] = now('Asia/Yangon')->toIso8601String();
    $report['result'] = 'passed';
    $saveReport();
    echo json_encode(['run' => $run, 'result' => 'passed', 'checks' => count($report['checks']), 'warehouse_id' => $warehouseId, 'final_stock' => $stock(), 'report' => $reportDirectory.'/report.json'], JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    $report['result'] = 'failed';
    $report['failure'] = ['class' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
    $saveReport();
    fwrite(STDERR, json_encode(['run' => $run, 'failure' => $report['failure'], 'checks' => count($report['checks']), 'report' => $reportDirectory.'/report.json'], JSON_PRETTY_PRINT).PHP_EOL);
    exit(1);
}
