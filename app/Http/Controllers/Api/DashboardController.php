<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerCreditService;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const DASHBOARDS = ['owner', 'sales', 'stock', 'delivery', 'finance'];

    public function __construct(private readonly CustomerCreditService $customerCredit)
    {
    }

    public function summary(Request $request, string $dashboard)
    {
        abort_unless(in_array($dashboard, self::DASHBOARDS, true), 404);
        $this->authorizePermission($request, 'office.dashboard.view');
        $this->validatePeriod($request);
        $data = $this->cached($request, "summary:{$dashboard}", fn () => match ($dashboard) {
            'owner' => $this->owner($request),
            'sales' => $this->sales($request),
            'stock' => $this->stock($request),
            'delivery' => $this->delivery($request),
            'finance' => $this->finance($request),
        });

        return ApiResponse::success(ucfirst($dashboard).' dashboard loaded.', $data);
    }

    public function charts(Request $request, string $dashboard)
    {
        abort_unless(in_array($dashboard, self::DASHBOARDS, true), 404);
        $this->authorizePermission($request, 'office.dashboard.view');
        $this->validatePeriod($request);
        $data = $this->cached($request, "charts:{$dashboard}", fn () => $this->chartData($request, $dashboard));

        return ApiResponse::success(ucfirst($dashboard).' KPI charts loaded.', $data);
    }

    public function mobile(Request $request)
    {
        $user = $request->user();
        $app = match ($user->role) {
            'Customer' => 'client',
            'Sales Representative' => 'sales',
            'Driver' => 'driver',
            default => null,
        };
        abort_unless($app, 403);
        $this->authorizePermission($request, "{$app}.home.view");
        $data = $this->cached($request, "mobile:{$app}:{$user->id}", fn () => match ($app) {
            'client' => $this->clientMobile($user),
            'sales' => $this->salesMobile($user),
            'driver' => $this->driverMobile($user),
        });

        return ApiResponse::success(ucfirst($app).' dashboard loaded.', ['app' => $app] + $data);
    }

    private function owner(Request $request): array
    {
        $date = Carbon::parse($request->query('date_to', $request->query('date', now()->toDateString())));
        $rangeStart = $request->filled('date_from') ? Carbon::parse($request->query('date_from'))->toDateString() : null;
        $monthStart = $rangeStart ?: $date->copy()->startOfMonth()->toDateString();
        $yearStart = $rangeStart ?: $date->copy()->startOfYear()->toDateString();
        $through = $date->toDateString();
        $todaySales = $this->salesBetween($through, $through);
        $monthlySales = $this->salesBetween($monthStart, $through);
        $annualSales = $this->salesBetween($yearStart, $through);
        $cashBalance = $this->bookBalance('cash', $through);
        $bankBalance = $this->bookBalance('bank', $through);
        $outstanding = $this->outstanding($through);
        $warehouseValue = (float) DB::table('stock_balances')->sum('stock_value');
        $vehicleCost = (float) DB::table('vehicle_costs')->where('status', 'approved')->whereBetween('cost_date', [$yearStart, $through])->sum('amount') + (float) DB::table('expenses')->where('status', 'approved')->where('category', 'vehicle_cost')->whereBetween('expense_date', [$yearStart, $through])->sum('amount');
        $salaryCost = (float) DB::table('payrolls')->whereIn('status', ['approved', 'paid'])->whereDate('period_end', '>=', $yearStart)->whereDate('period_start', '<=', $through)->sum('total_net');
        $outdoorExpense = (float) DB::table('expenses')->where('status', 'approved')->where('expense_type', 'outdoor')->whereBetween('expense_date', [$yearStart, $through])->sum('amount');
        $dailyExpense = (float) DB::table('expenses')->where('status', 'approved')->where('expense_type', 'daily')->where('category', '!=', 'vehicle_cost')->whereBetween('expense_date', [$yearStart, $through])->sum('amount');
        $netProfit = $annualSales - $vehicleCost - $salaryCost - $outdoorExpense - $dailyExpense;
        $targetQuery = DB::table('sales_targets');
        $target = $rangeStart
            ? (float) $targetQuery->whereBetween('target_month', [Carbon::parse($rangeStart)->startOfMonth()->toDateString(), $date->copy()->startOfMonth()->toDateString()])->sum('target_amount')
            : (float) $targetQuery->whereDate('target_month', $date->copy()->startOfMonth())->sum('target_amount');
        $fieldCollections = (float) DB::table('collections')->whereNotNull('employee_id')->whereBetween('collection_date', [$monthStart, $through])->sum('amount');
        $recentOrders = DB::table('orders')->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')->whereExists(function ($query) {
            $query->selectRaw('1')->from('order_items')->whereColumn('order_items.order_id', 'orders.id')->whereIn('order_items.item_type', ['sale', 'foc']);
        })->when($rangeStart, fn ($query) => $query->whereBetween('orders.order_date', [$rangeStart, $through]))->orderByDesc('orders.order_date')->orderByDesc('orders.id')->limit(6)->get(['orders.id', 'orders.code', 'orders.order_date', 'orders.status', 'orders.total', DB::raw('COALESCE(orders.recipient_name, customers.shop_name) as shop_name')])->map(fn ($item) => ['id' => $item->id, 'code' => $item->code, 'date' => $item->order_date, 'status' => $item->status, 'amount' => (float) $item->total, 'shop_name' => $item->shop_name]);

        return ['as_of' => $through, 'kpis' => ['today_sales' => $todaySales, 'monthly_sales' => $monthlySales, 'annual_sales' => $annualSales, 'cash_balance' => $cashBalance, 'bank_balance' => $bankBalance, 'outstanding_credit' => $outstanding, 'warehouse_value' => $warehouseValue, 'vehicle_cost' => $vehicleCost, 'salary_cost' => $salaryCost, 'outdoor_expense' => $outdoorExpense, 'net_profit' => $netProfit, 'target_amount' => $target, 'target_achievement' => $target > 0 ? round($monthlySales / $target * 100, 1) : null, 'customer_count' => DB::table('customers')->where('is_active', true)->count(), 'field_collection' => $fieldCollections], 'recent_orders' => $recentOrders, 'attention' => ['pending_orders' => DB::table('orders')->where('status', 'pending')->count(), 'submitted_collections' => DB::table('collections')->where('status', 'submitted')->count(), 'submitted_expenses' => DB::table('expenses')->where('status', 'submitted')->count(), 'submitted_vehicle_records' => DB::table('vehicle_costs')->where('status', 'submitted')->count(), 'stock_alerts' => DB::table('stock_balances')->where('quantity', '<=', 100)->count()]];
    }

    private function sales(Request $request): array
    {
        [$from, $to] = $this->periodRange($request);
        $month = Carbon::parse($from)->startOfMonth()->toDateString();
        $invoices = $this->invoiceRows($from, $to);
        $targets = DB::table('sales_targets')->whereBetween('target_month', [$month, Carbon::parse($to)->startOfMonth()->toDateString()])->get()->groupBy('employee_id');
        $areaSales = $invoices->groupBy('area_name')->map(fn ($rows, $area) => ['label' => $area ?: 'Unassigned', 'value' => (float) $rows->sum('total')])->sortByDesc('value')->values();
        $employees = DB::table('employees')->leftJoin('routes', 'employees.assigned_route_id', '=', 'routes.id')->where('employees.employee_type', 'sales')->where('employees.is_active', true)->get(['employees.id', 'employees.code', 'employees.name', 'employees.assigned_route_id', 'routes.name as route_name']);
        $ranking = $employees->map(function ($employee) use ($invoices, $targets) {
            $actual = (float) $invoices->where('route_id', $employee->assigned_route_id)->sum('total');
            $target = (float) ($targets->get($employee->id)?->sum('target_amount') ?? 0);

            return ['employee_id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'route_name' => $employee->route_name, 'actual' => $actual, 'target' => $target, 'achievement' => $target > 0 ? round($actual / $target * 100, 1) : null];
        })->sortByDesc('actual')->values()->map(fn ($item, $index) => $item + ['rank' => $index + 1]);
        $topCustomers = $invoices->groupBy('customer_id')->map(function ($rows) {
            $first = $rows->first();

            return ['customer_id' => $first->customer_id, 'code' => $first->customer_code, 'shop_name' => $first->shop_name, 'route_name' => $first->route_name, 'sales' => (float) $rows->sum('total')];
        })->sortByDesc('sales')->take(8)->values();

        $targetTotal = (float) $targets->flatten()->sum('target_amount');

        return ['month' => $month, 'period' => ['date_from' => $from, 'date_to' => $to], 'summary' => ['sales' => (float) $invoices->sum('total'), 'orders' => $invoices->pluck('order_id')->filter()->unique()->count(), 'new_customers' => DB::table('customers')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(), 'target' => $targetTotal, 'achievement' => $targetTotal > 0 ? round($invoices->sum('total') / $targetTotal * 100, 1) : null], 'area_sales' => $areaSales, 'ranking' => $ranking, 'top_customers' => $topCustomers];
    }

    private function stock(Request $request): array
    {
        [$from, $to] = $this->periodRange($request);
        $month = Carbon::parse($from)->startOfMonth()->toDateString();
        $balances = DB::table('stock_balances')->join('products', 'stock_balances.product_id', '=', 'products.id')->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')->get(['stock_balances.product_id', 'stock_balances.quantity', 'stock_balances.stock_value', 'products.sku', 'products.name as product_name', 'warehouses.name as warehouse_name']);
        $demand = DB::table('invoice_items')->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')->where('invoices.status', '!=', 'cancelled')->whereBetween('invoices.invoice_date', [$from, $to])->groupBy('invoice_items.product_id', 'invoice_items.product_sku', 'invoice_items.product_name')->selectRaw('invoice_items.product_id, invoice_items.product_sku sku, invoice_items.product_name product_name, SUM(invoice_items.quantity) quantity')->get()->keyBy('product_id');
        $products = $balances->groupBy('product_id')->map(function ($rows, $productId) use ($demand) {
            $first = $rows->first();

            return ['product_id' => $productId, 'sku' => $first->sku, 'product_name' => $first->product_name, 'quantity' => (float) $rows->sum('quantity'), 'stock_value' => (float) $rows->sum('stock_value'), 'monthly_demand' => (float) ($demand->get($productId)->quantity ?? 0), 'warehouses' => $rows->count(), 'alert' => (float) $rows->sum('quantity') <= 100];
        })->values();

        return ['month' => $month, 'summary' => ['quantity' => (float) $balances->sum('quantity'), 'stock_value' => (float) $balances->sum('stock_value'), 'products' => $products->count(), 'warehouses' => $balances->pluck('warehouse_name')->unique()->count(), 'alerts' => $products->where('alert', true)->count()], 'fast_moving' => $products->sortByDesc('monthly_demand')->take(8)->values(), 'slow_moving' => $products->sortBy('monthly_demand')->take(8)->values(), 'alerts' => $products->where('alert', true)->sortBy('quantity')->values()];
    }

    private function delivery(Request $request): array
    {
        [$from, $to] = $this->periodRange($request);
        $month = Carbon::parse($from)->startOfMonth()->toDateString();
        $deliveries = DB::table('deliveries')->join('vehicles', 'deliveries.vehicle_id', '=', 'vehicles.id')->join('employees', 'deliveries.driver_id', '=', 'employees.id')->join('routes', 'deliveries.route_id', '=', 'routes.id')->whereBetween('deliveries.planned_date', [$from, $to])->get(['deliveries.*', 'vehicles.code as vehicle_code', 'vehicles.plate_no', 'employees.code as driver_code', 'employees.name as driver_name', 'routes.name as route_name']);
        $completedStatuses = ['delivered', 'partially_delivered'];
        $vehicleUsage = $deliveries->groupBy('vehicle_id')->map(function ($rows) {
            $first = $rows->first();

            return ['vehicle_id' => $first->vehicle_id, 'code' => $first->vehicle_code, 'plate_no' => $first->plate_no, 'deliveries' => $rows->count(), 'distance_km' => (float) $rows->sum('distance_km'), 'delivered_quantity' => (float) $rows->sum('delivered_quantity')];
        })->sortByDesc('deliveries')->values();
        $drivers = $deliveries->groupBy('driver_id')->map(function ($rows) use ($completedStatuses) {
            $first = $rows->first();

            return ['driver_id' => $first->driver_id, 'code' => $first->driver_code, 'name' => $first->driver_name, 'route_name' => $first->route_name, 'deliveries' => $rows->count(), 'completed' => $rows->whereIn('status', $completedStatuses)->count(), 'delivered_quantity' => (float) $rows->sum('delivered_quantity'), 'completion_rate' => $rows->count() ? round($rows->whereIn('status', $completedStatuses)->count() / $rows->count() * 100, 1) : 0];
        })->sortByDesc('delivered_quantity')->values();
        $deliveryCost = (float) DB::table('vehicle_costs')->where('status', 'approved')->whereBetween('cost_date', [$from, $to])->sum('amount');

        return ['month' => $month, 'summary' => ['deliveries' => $deliveries->count(), 'completed' => $deliveries->whereIn('status', $completedStatuses)->count(), 'active' => $deliveries->whereIn('status', ['assigned', 'loading', 'on_route'])->count(), 'delivered_quantity' => (float) $deliveries->sum('delivered_quantity'), 'distance_km' => (float) $deliveries->sum('distance_km'), 'delivery_cost' => $deliveryCost, 'vehicles_used' => $deliveries->pluck('vehicle_id')->unique()->count()], 'vehicle_usage' => $vehicleUsage, 'drivers' => $drivers];
    }

    private function finance(Request $request): array
    {
        [$from, $to] = $this->periodRange($request);
        $month = Carbon::parse($from)->startOfMonth()->toDateString();
        $collections = (float) DB::table('collections')->where('status', 'approved')->whereBetween('collection_date', [$from, $to])->sum('amount');
        $fieldCollections = (float) DB::table('collections')->whereNotNull('employee_id')->whereBetween('collection_date', [$from, $to])->sum('amount');
        $expenses = DB::table('expenses')->where('status', 'approved')->whereBetween('expense_date', [$from, $to])->get();
        $vehicle = (float) DB::table('vehicle_costs')->where('status', 'approved')->whereBetween('cost_date', [$from, $to])->sum('amount') + (float) $expenses->where('category', 'vehicle_cost')->sum('amount');
        $payroll = (float) DB::table('payrolls')->whereIn('status', ['approved', 'paid'])->whereDate('period_end', '>=', $from)->whereDate('period_start', '<=', $to)->sum('total_net');
        $revenue = $this->salesBetween($from, $to);
        $daily = (float) $expenses->where('expense_type', 'daily')->where('category', '!=', 'vehicle_cost')->sum('amount');
        $outdoor = (float) $expenses->where('expense_type', 'outdoor')->sum('amount');
        $totalExpense = $vehicle + $payroll + $daily + $outdoor;

        return ['month' => $month, 'summary' => ['collections' => $collections, 'field_collections' => $fieldCollections, 'debt_balance' => $this->outstanding($to), 'revenue' => $revenue, 'expenses' => $totalExpense, 'profit' => $revenue - $totalExpense, 'cash_balance' => $this->bookBalance('cash', $to), 'bank_balance' => $this->bookBalance('bank', $to)], 'expense_analysis' => [['label' => 'Payroll', 'value' => $payroll], ['label' => 'Vehicle', 'value' => $vehicle], ['label' => 'Outdoor employee', 'value' => $outdoor], ['label' => 'Daily expense', 'value' => $daily]], 'pending' => ['collections' => DB::table('collections')->where('status', 'submitted')->count(), 'expenses' => DB::table('expenses')->where('status', 'submitted')->count(), 'vehicle_records' => DB::table('vehicle_costs')->where('status', 'submitted')->count()]];
    }

    private function chartData(Request $request, string $dashboard): array
    {
        $date = Carbon::parse($request->query('date_to', $request->query('date', now()->toDateString())));
        $hasRange = $request->filled('date_from') || $request->filled('date_to');
        [$rangeFrom, $rangeTo] = $hasRange ? $this->periodRange($request) : [$date->copy()->subMonths(5)->startOfMonth()->toDateString(), $date->toDateString()];
        $months = $hasRange
            ? collect(Carbon::parse($rangeFrom)->startOfMonth()->monthsUntil(Carbon::parse($rangeTo)->startOfMonth()))
            : collect(range(5, 0))->map(fn ($offset) => $date->copy()->subMonths($offset)->startOfMonth());
        $salesTrend = $months->map(function ($month) use ($hasRange, $rangeFrom, $rangeTo) {
            $from = $hasRange ? max($rangeFrom, $month->toDateString()) : $month->toDateString();
            $to = $hasRange ? min($rangeTo, $month->copy()->endOfMonth()->toDateString()) : $month->copy()->endOfMonth()->toDateString();

            return ['label' => $month->format('M'), 'value' => $this->salesBetween($from, $to)];
        });
        if ($dashboard === 'owner') {
            return ['sales_trend' => $salesTrend, 'cash_flow' => $hasRange ? $this->cashFlowRange($rangeFrom, $rangeTo) : $this->cashFlow($date), 'mix' => [['label' => 'Sales', 'value' => (float) $salesTrend->sum('value')], ['label' => 'Collections', 'value' => (float) DB::table('collections')->where('status', 'approved')->whereBetween('collection_date', [$hasRange ? $rangeFrom : $date->copy()->startOfYear()->toDateString(), $date->toDateString()])->sum('amount')], ['label' => 'Receivable', 'value' => $this->outstanding($date->toDateString())]]];
        }
        if ($dashboard === 'sales') {
            $sales = $this->sales($request);

            return ['sales_trend' => $salesTrend, 'area_sales' => $sales['area_sales'], 'target' => $sales['ranking']->map(fn ($item) => ['label' => $item['name'], 'actual' => $item['actual'], 'target' => $item['target']])];
        }
        if ($dashboard === 'stock') {
            $stock = $this->stock($request);

            return ['stock_by_product' => $stock['fast_moving']->map(fn ($item) => ['label' => $item['sku'], 'value' => $item['quantity']]), 'movement' => $stock['fast_moving']->map(fn ($item) => ['label' => $item['sku'], 'value' => $item['monthly_demand']])];
        }
        if ($dashboard === 'delivery') {
            $delivery = $this->delivery($request);

            return ['driver_output' => $delivery['drivers']->map(fn ($item) => ['label' => $item['name'], 'value' => $item['delivered_quantity']]), 'vehicle_usage' => $delivery['vehicle_usage']->map(fn ($item) => ['label' => $item['code'], 'value' => $item['deliveries']])];
        }
        $profitTrend = $months->map(function ($month) {
            $from = $month->toDateString();
            $to = $month->copy()->endOfMonth()->toDateString();
            $revenue = $this->salesBetween($from, $to);
            $expense = (float) DB::table('expenses')->where('status', 'approved')->whereBetween('expense_date', [$from, $to])->sum('amount') + (float) DB::table('vehicle_costs')->where('status', 'approved')->whereBetween('cost_date', [$from, $to])->sum('amount') + (float) DB::table('payrolls')->whereIn('status', ['approved', 'paid'])->whereDate('period_end', '>=', $from)->whereDate('period_start', '<=', $to)->sum('total_net');

            return ['label' => $month->format('M'), 'revenue' => $revenue, 'expense' => $expense, 'profit' => $revenue - $expense];
        });

        return ['profit_trend' => $profitTrend, 'cash_flow' => $this->cashFlow($date), 'expense_analysis' => $this->finance($request)['expense_analysis']];
    }

    private function clientMobile(User $user): array
    {
        abort_unless($user->customer_id, 404);
        $orders = DB::table('orders')->where('customer_id', $user->customer_id)->whereExists(function ($query) {
            $query->selectRaw('1')->from('order_items')->whereColumn('order_items.order_id', 'orders.id')->whereIn('order_items.item_type', ['sale', 'foc']);
        })->orderByDesc('order_date')->orderByDesc('id')->limit(4)->get(['id', 'code', 'order_date', 'status', 'total'])->map(fn ($item) => ['id' => $item->id, 'code' => $item->code, 'date' => $item->order_date, 'status' => $item->status, 'amount' => (float) $item->total]);

        return ['summary' => ['current_order_status' => data_get($orders->first(), 'status'), 'current_order_code' => data_get($orders->first(), 'code'), 'outstanding_balance' => $this->customerOutstanding($user->customer_id), 'recent_orders_count' => $orders->count()], 'recent_orders' => $orders];
    }

    private function salesMobile(User $user): array
    {
        abort_unless($user->employee_id, 403);
        $employee = DB::table('employees')->leftJoin('routes', 'employees.assigned_route_id', '=', 'routes.id')->where('employees.id', $user->employee_id)->first(['employees.id', 'employees.assigned_route_id', 'routes.code as route_code', 'routes.name as route_name']);
        $month = now()->startOfMonth();
        $from = $month->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();
        $sales = (float) $this->invoiceRows($from, $to)->where('route_id', $employee->assigned_route_id)->sum('total');
        $target = (float) DB::table('sales_targets')->where('employee_id', $employee->id)->whereDate('target_month', $from)->value('target_amount');
        $ordersCount = DB::table('orders')->where('created_by', $user->id)->where('source_app', 'sales')->whereBetween('order_date', [$from, $to])->count();

        return ['summary' => ['monthly_sales' => $sales, 'target' => $target, 'achievement' => $target > 0 ? round($sales / $target * 100, 1) : null, 'orders_count' => $ordersCount, 'new_customers' => DB::table('customers')->where('route_id', $employee->assigned_route_id)->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(), 'assigned_route' => $employee->route_name, 'route_code' => $employee->route_code], 'trend' => $this->chartData(new Request(['date' => now()->toDateString()]), 'sales')['sales_trend']];
    }

    private function driverMobile(User $user): array
    {
        abort_unless($user->employee_id, 403);
        $date = now();
        $from = $date->copy()->startOfMonth()->toDateString();
        $to = $date->copy()->endOfMonth()->toDateString();
        $deliveries = DB::table('deliveries')->join('routes', 'deliveries.route_id', '=', 'routes.id')->where('deliveries.driver_id', $user->employee_id)->whereBetween('planned_date', [$from, $to])->get(['deliveries.*', 'routes.name as route_name']);
        $expenses = DB::table('expenses')->where('employee_id', $user->employee_id)->whereBetween('expense_date', [$from, $to])->count();
        $collections = DB::table('collections')->where('employee_id', $user->employee_id)->whereBetween('collection_date', [$from, $to])->count();

        return ['summary' => ['today_deliveries' => $deliveries->where('planned_date', $date->toDateString())->count(), 'completed_deliveries' => $deliveries->whereIn('status', ['delivered', 'partially_delivered'])->count(), 'delivered_quantity' => (float) $deliveries->sum('delivered_quantity'), 'assigned_route' => $deliveries->first()->route_name ?? DB::table('employees')->join('routes', 'employees.assigned_route_id', '=', 'routes.id')->where('employees.id', $user->employee_id)->value('routes.name'), 'submitted_expenses' => $expenses, 'submitted_collections' => $collections], 'deliveries' => $deliveries->sortByDesc('planned_date')->take(4)->values()->map(fn ($item) => ['id' => $item->id, 'code' => $item->code, 'date' => $item->planned_date, 'status' => $item->status, 'route_name' => $item->route_name, 'quantity' => (float) $item->delivered_quantity])];
    }

    private function invoiceRows(string $from, string $to)
    {
        return DB::table('invoices')->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')->leftJoin('orders', 'invoices.order_id', '=', 'orders.id')->leftJoin('routes', function ($join) {
            $join->on('routes.id', '=', DB::raw('COALESCE(invoices.route_id, orders.route_id, customers.route_id)'));
        })->leftJoin('areas', 'routes.area_id', '=', 'areas.id')->where('invoices.status', '!=', 'cancelled')->whereBetween('invoices.invoice_date', [$from, $to])->get(['invoices.id', 'invoices.order_id', 'invoices.customer_id', 'invoices.total', 'customers.code as customer_code', DB::raw('COALESCE(invoices.recipient_name, orders.recipient_name, customers.shop_name) as shop_name'), DB::raw('COALESCE(invoices.route_id, orders.route_id, customers.route_id) as route_id'), 'routes.name as route_name', 'areas.name as area_name'])->map(function ($item) {
            $item->total = (float) $item->total;

            return $item;
        });
    }

    private function salesBetween(string $from, string $to): float
    {
        return (float) DB::table('invoices')->where('status', '!=', 'cancelled')->whereBetween('invoice_date', [$from, $to])->sum('total');
    }

    private function outstanding(string $through): float
    {
        $creditSales = (float) DB::table('invoices')->join('orders', 'invoices.order_id', '=', 'orders.id')->where('orders.payment_type', 'credit')->where('invoices.status', '!=', 'cancelled')->whereDate('invoices.invoice_date', '<=', $through)->sum('invoices.total');
        $returnCredits = (float) DB::table('orders as returns')->join('orders as originals', 'returns.original_order_id', '=', 'originals.id')->where('returns.status', 'confirmed')->whereDate('returns.order_date', '<=', $through)->where(fn ($query) => $query->where('originals.payment_type', 'credit')->orWhere('returns.return_settlement_method', 'customer_credit'))->sum('returns.total');
        $returnRefunds = (float) DB::table('orders as returns')->join('orders as originals', 'returns.original_order_id', '=', 'originals.id')->where('returns.status', 'confirmed')->where('originals.payment_type', 'credit')->whereDate('returns.order_date', '<=', $through)->sum('returns.refund_amount');

        return max($creditSales + $returnRefunds - (float) DB::table('collections')->where('status', 'approved')->whereDate('collection_date', '<=', $through)->sum('amount') - $returnCredits, 0);
    }

    private function customerOutstanding(int $customerId): float
    {
        return $this->customerCredit->outstanding($customerId);
    }

    private function bookBalance(string $book, string $through): float
    {
        $query = DB::table('financial_transactions')->where('book_type', $book)->whereDate('transaction_date', '<=', $through);

        return (float) (clone $query)->where('direction', 'in')->sum('amount') - (float) (clone $query)->where('direction', 'out')->sum('amount');
    }

    private function cashFlow(Carbon $date)
    {
        return collect(range(6, 0))->map(function ($offset) use ($date) {
            $day = $date->copy()->subDays($offset)->toDateString();
            $rows = DB::table('financial_transactions')->whereDate('transaction_date', $day)->get();

            return ['label' => Carbon::parse($day)->format('D'), 'inflow' => (float) $rows->where('direction', 'in')->sum('amount'), 'outflow' => (float) $rows->where('direction', 'out')->sum('amount')];
        });
    }

    private function cashFlowRange(string $from, string $to)
    {
        return collect(Carbon::parse($from)->daysUntil(Carbon::parse($to)))->map(function ($date) {
            $day = $date->toDateString();
            $rows = DB::table('financial_transactions')->whereDate('transaction_date', $day)->get();

            return ['label' => $date->format('M j'), 'inflow' => (float) $rows->where('direction', 'in')->sum('amount'), 'outflow' => (float) $rows->where('direction', 'out')->sum('amount')];
        });
    }

    private function monthRange(Request $request): array
    {
        $month = Carbon::parse($request->query('month', now()->format('Y-m').'-01'))->startOfMonth();

        return [$month->toDateString(), $month->toDateString(), $month->copy()->endOfMonth()->toDateString()];
    }

    private function periodRange(Request $request): array
    {
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = Carbon::parse($request->query('date_from', $request->query('date_to')))->toDateString();
            $to = Carbon::parse($request->query('date_to', $request->query('date_from')))->toDateString();

            return [$from, $to];
        }

        [, $from, $to] = $this->monthRange($request);

        return [$from, $to];
    }

    private function validatePeriod(Request $request): void
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'date' => ['nullable', 'date'],
            'month' => ['nullable', 'date'],
        ]);
    }

    private function cached(Request $request, string $scope, callable $callback)
    {
        $key = 'valley:dashboard:'.$scope.':'.md5(json_encode($request->query()));

        return Cache::remember($key, now()->addSeconds(60), $callback);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
