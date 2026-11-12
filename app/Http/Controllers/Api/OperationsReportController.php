<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission($request);
        $filters = $this->filters($request);

        return ApiResponse::success('Operations report loaded.', $this->buildReport($filters));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request);
        $filters = $this->filters($request);
        $section = $request->validate([
            'section' => ['nullable', 'in:route_sales,customer_sales,product_sales,expenses,payroll,drivers,stock'],
        ])['section'] ?? 'route_sales';
        if ($section === 'customer_sales') {
            return $this->exportCustomerSalesItems($filters);
        }
        $report = $this->buildReport($filters);
        $rows = collect($report['breakdowns'][$section] ?? []);
        $headers = $rows->isEmpty() ? ['message'] : array_keys((array) $rows->first());
        $filename = "valley-{$section}-{$filters['date_from']}-{$filters['date_to']}.csv";

        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            if ($rows->isEmpty()) {
                fputcsv($output, ['No records for the selected period.']);
            } else {
                foreach ($rows as $row) {
                    fputcsv($output, array_map(fn ($header) => data_get($row, $header), $headers));
                }
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function salesInvoiceQuery(array $filters): Builder
    {
        return DB::table('invoices')
            ->leftJoin('orders', 'invoices.order_id', '=', 'orders.id')
            ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->leftJoin('routes', function ($join) {
                $join->on('routes.id', '=', DB::raw('COALESCE(invoices.route_id, orders.route_id, customers.route_id)'));
            })
            ->leftJoin('users', 'orders.created_by', '=', 'users.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->whereBetween('invoices.invoice_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['route_id'], fn ($query, $id) => $query->whereRaw('COALESCE(invoices.route_id, orders.route_id, customers.route_id) = ?', [$id]))
            ->when($filters['customer_id'], fn ($query, $id) => $query->where('invoices.customer_id', $id))
            ->when($filters['employee_id'], fn ($query, $id) => $query->where('users.employee_id', $id))
            ->when($filters['warehouse_id'] || $filters['driver_id'] || $filters['vehicle_id'], function ($query) use ($filters) {
                $query->whereExists(function ($deliveries) use ($filters) {
                    $deliveries->selectRaw('1')->from('deliveries')
                        ->whereColumn('deliveries.invoice_id', 'invoices.id')
                        ->where('deliveries.status', '!=', 'cancelled')
                        ->when($filters['warehouse_id'], fn ($query, $id) => $query->where('deliveries.warehouse_id', $id))
                        ->when($filters['driver_id'], fn ($query, $id) => $query->where('deliveries.driver_id', $id))
                        ->when($filters['vehicle_id'], fn ($query, $id) => $query->where('deliveries.vehicle_id', $id));
                });
            });
    }

    private function exportCustomerSalesItems(array $filters): StreamedResponse
    {
        $columns = [
            'customer_code' => 'Customer code', 'shop_name' => 'Shop name',
            'order_code' => 'Order code', 'order_date' => 'Order date', 'order_source' => 'Order source',
            'created_by' => 'Order created by', 'created_by_user_id' => 'Creator user ID',
            'created_by_role' => 'Creator role', 'created_by_employee_code' => 'Creator employee code',
            'invoice_code' => 'Invoice code', 'invoice_date' => 'Invoice date', 'invoice_status' => 'Invoice status',
            'route_code' => 'Route code', 'route_name' => 'Route name',
            'product_sku' => 'Product SKU', 'product_name' => 'Product name',
            'item_type' => 'Item type', 'unit' => 'Unit', 'quantity' => 'Quantity',
            'unit_price' => 'Unit price (MMK)', 'discount_amount' => 'Line discount (MMK)', 'line_total' => 'Line total (MMK)',
        ];
        $items = $this->salesInvoiceQuery($filters)
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('employees as creators', 'users.employee_id', '=', 'creators.id')
            ->select([
                'customers.code as customer_code', DB::raw('COALESCE(invoices.recipient_name, customers.shop_name, orders.recipient_name) as shop_name'),
                'orders.code as order_code', 'orders.order_date', 'orders.source_app as order_source',
                'users.name as created_by', 'orders.created_by as created_by_user_id', 'users.role as created_by_role', 'creators.code as created_by_employee_code',
                'invoices.code as invoice_code', 'invoices.invoice_date', 'invoices.status as invoice_status',
                'routes.code as route_code', 'routes.name as route_name',
                'invoice_items.product_sku', 'invoice_items.product_name', 'invoice_items.item_type', 'invoice_items.unit',
                'invoice_items.quantity', 'invoice_items.unit_price', 'invoice_items.discount_amount', 'invoice_items.line_total',
            ])->orderBy('invoices.invoice_date')->orderBy('invoices.id')->orderBy('invoice_items.id');
        $filename = "valley-customer_sales-items-{$filters['date_from']}-{$filters['date_to']}.csv";

        return response()->streamDownload(function () use ($columns, $items) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values($columns), ',', '"', '');
            foreach ($items->cursor() as $item) {
                $row = array_map(function ($key) use ($item) {
                    $value = $item->{$key};
                    // Keep customer and product text as text when opened in Excel.
                    return is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
                }, array_keys($columns));
                fputcsv($output, $row, ',', '"', '');
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function buildReport(array $filters): array
    {
        $from = $filters['date_from'];
        $to = $filters['date_to'];

        $invoiceQuery = $this->salesInvoiceQuery($filters);

        $invoiceRows = (clone $invoiceQuery)->get([
            'invoices.id', 'invoices.invoice_date', 'invoices.total', 'invoices.customer_id',
            DB::raw('COALESCE(invoices.route_id, orders.route_id, customers.route_id) as route_id'), 'customers.code as customer_code',
            DB::raw('COALESCE(invoices.recipient_name, customers.shop_name) as customer_name'),
            'routes.code as route_code', 'routes.name as route_name',
        ]);

        $orderCount = DB::table('orders')
            ->leftJoin('users', 'orders.created_by', '=', 'users.id')
            ->whereBetween('orders.order_date', [$from, $to])
            ->whereNotIn('orders.status', ['cancelled'])
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->whereIn('order_items.item_type', ['sale', 'foc']);
            })
            ->when($filters['route_id'], fn ($query, $id) => $query->where('orders.route_id', $id))
            ->when($filters['customer_id'], fn ($query, $id) => $query->where('orders.customer_id', $id))
            ->when($filters['employee_id'], fn ($query, $id) => $query->where('users.employee_id', $id))
            ->count('orders.id');

        $stockQuery = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->whereBetween('stock_movements.movement_date', [$from, $to])
            ->when($filters['warehouse_id'], fn ($query, $id) => $query->where('stock_movements.warehouse_id', $id));
        $stockRows = (clone $stockQuery)->get([
            'stock_movements.movement_date', 'stock_movements.signed_quantity', 'stock_movements.total_cost',
            'products.sku', 'products.name as product_name', 'warehouses.name as warehouse_name',
        ]);
        $stockValue = (float) DB::table('stock_balances')
            ->when($filters['warehouse_id'], fn ($query, $id) => $query->where('warehouse_id', $id))
            ->sum('stock_value');

        $transactions = DB::table('financial_transactions')
            ->whereBetween('transaction_date', [$from, $to])
            ->get(['transaction_date', 'book_type', 'direction', 'category', 'amount']);
        $expenseRows = DB::table('expenses')
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$from, $to])
            ->when($filters['employee_id'], fn ($query, $id) => $query->where('employee_id', $id))
            ->get(['expense_date', 'expense_type', 'category', 'amount']);
        $vehicleCosts = DB::table('vehicle_costs')
            ->where('status', 'approved')
            ->whereBetween('cost_date', [$from, $to])
            ->when($filters['vehicle_id'], fn ($query, $id) => $query->where('vehicle_id', $id))
            ->sum('amount');

        $payrollRows = DB::table('payrolls')
            ->whereIn('status', ['approved', 'paid'])
            ->whereDate('period_end', '>=', $from)
            ->whereDate('period_start', '<=', $to)
            ->get(['month', 'employee_type', 'total_gross', 'total_deductions', 'total_net']);

        $deliveryQuery = DB::table('deliveries')
            ->join('employees', 'deliveries.driver_id', '=', 'employees.id')
            ->leftJoin('vehicles', 'deliveries.vehicle_id', '=', 'vehicles.id')
            ->whereBetween('deliveries.planned_date', [$from, $to])
            ->when($filters['route_id'], fn ($query, $id) => $query->where('deliveries.route_id', $id))
            ->when($filters['warehouse_id'], fn ($query, $id) => $query->where('deliveries.warehouse_id', $id))
            ->when($filters['driver_id'], fn ($query, $id) => $query->where('deliveries.driver_id', $id))
            ->when($filters['vehicle_id'], fn ($query, $id) => $query->where('deliveries.vehicle_id', $id));
        $deliveryRows = (clone $deliveryQuery)->get([
            'deliveries.planned_date', 'deliveries.status', 'deliveries.delivered_quantity',
            'deliveries.returned_quantity', 'deliveries.damaged_quantity', 'deliveries.driver_id',
            'employees.code as driver_code', 'employees.name as driver_name',
            'vehicles.code as vehicle_code',
        ]);

        $newCustomers = DB::table('customers')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->where('is_active', true)
            ->when($filters['route_id'], fn ($query, $id) => $query->where('route_id', $id))
            ->count();

        $productSales = (clone $invoiceQuery)
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->groupBy('invoice_items.product_id', 'invoice_items.product_sku', 'invoice_items.product_name')
            ->orderByDesc(DB::raw('SUM(invoice_items.line_total)'))
            ->get([
                'invoice_items.product_sku as code', 'invoice_items.product_name as name',
                DB::raw('SUM(invoice_items.quantity) as quantity'),
                DB::raw('SUM(invoice_items.line_total) as amount'),
            ])->map(fn ($row) => $this->numericRow($row, ['quantity', 'amount']));

        $cashIn = (float) $transactions->where('direction', 'in')->sum('amount');
        $cashOut = (float) $transactions->where('direction', 'out')->sum('amount');
        $expenseTotal = (float) $expenseRows->sum('amount') + (float) $vehicleCosts;
        $payrollTotal = (float) $payrollRows->sum('total_net');
        $salesTotal = (float) $invoiceRows->sum('total');
        $completed = $deliveryRows->whereIn('status', ['delivered', 'partially_delivered'])->count();

        return [
            'period' => ['date_from' => $from, 'date_to' => $to, 'grouping' => $this->grouping($from, $to)],
            'filters' => $filters,
            'summary' => [
                'sales' => $salesTotal,
                'orders' => $orderCount,
                'average_order_value' => $orderCount ? round($salesTotal / $orderCount, 2) : 0,
                'stock_in' => (float) $stockRows->where('signed_quantity', '>', 0)->sum('signed_quantity'),
                'stock_out' => abs((float) $stockRows->where('signed_quantity', '<', 0)->sum('signed_quantity')),
                'stock_value' => $stockValue,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'cash_net' => $cashIn - $cashOut,
                'expenses' => $expenseTotal,
                'payroll' => $payrollTotal,
                'net_result' => $salesTotal - $expenseTotal - $payrollTotal,
                'deliveries' => $deliveryRows->count(),
                'delivery_completion' => $deliveryRows->count() ? round($completed / $deliveryRows->count() * 100, 1) : 0,
                'new_customers' => $newCustomers,
            ],
            'trend' => $this->trend($from, $to, $invoiceRows, $transactions, $expenseRows),
            'breakdowns' => [
                'route_sales' => $this->salesBreakdown($invoiceRows, 'route_id', 'route_code', 'route_name'),
                'customer_sales' => $this->salesBreakdown($invoiceRows, 'customer_id', 'customer_code', 'customer_name'),
                'product_sales' => $productSales,
                'expenses' => $expenseRows->groupBy('category')->map(fn ($rows, $category) => [
                    'code' => $category, 'name' => str($category)->replace('_', ' ')->title()->toString(),
                    'records' => $rows->count(), 'amount' => (float) $rows->sum('amount'),
                ])->sortByDesc('amount')->values(),
                'payroll' => $payrollRows->groupBy(fn ($row) => $row->employee_type ?: 'all')->map(fn ($rows, $type) => [
                    'code' => $type, 'name' => str($type)->title()->toString(),
                    'gross' => (float) $rows->sum('total_gross'),
                    'deductions' => (float) $rows->sum('total_deductions'),
                    'amount' => (float) $rows->sum('total_net'),
                ])->sortByDesc('amount')->values(),
                'drivers' => $deliveryRows->groupBy('driver_id')->map(function ($rows) {
                    $first = $rows->first();
                    $completed = $rows->whereIn('status', ['delivered', 'partially_delivered'])->count();

                    return [
                        'code' => $first->driver_code, 'name' => $first->driver_name,
                        'deliveries' => $rows->count(), 'completed' => $completed,
                        'completion' => $rows->count() ? round($completed / $rows->count() * 100, 1) : 0,
                        'quantity' => (float) $rows->sum('delivered_quantity'),
                        'returned' => (float) $rows->sum('returned_quantity'),
                        'damaged' => (float) $rows->sum('damaged_quantity'),
                    ];
                })->sortByDesc('quantity')->values(),
                'stock' => $stockRows->groupBy(fn ($row) => $row->sku.'|'.$row->warehouse_name)->map(function ($rows) {
                    $first = $rows->first();

                    return [
                        'code' => $first->sku, 'name' => $first->product_name,
                        'warehouse' => $first->warehouse_name,
                        'in' => (float) $rows->where('signed_quantity', '>', 0)->sum('signed_quantity'),
                        'out' => abs((float) $rows->where('signed_quantity', '<', 0)->sum('signed_quantity')),
                        'net' => (float) $rows->sum('signed_quantity'),
                    ];
                })->values(),
            ],
            'meta' => $this->meta(),
        ];
    }

    private function trend(string $from, string $to, Collection $invoices, Collection $transactions, Collection $expenses): Collection
    {
        $grouping = $this->grouping($from, $to);
        $key = fn ($date) => $grouping === 'month' ? Carbon::parse($date)->format('Y-m') : Carbon::parse($date)->toDateString();
        $labels = $grouping === 'month'
            ? collect(Carbon::parse($from)->startOfMonth()->monthsUntil(Carbon::parse($to)->startOfMonth()))->map(fn ($date) => $date->format('Y-m'))
            : collect(Carbon::parse($from)->daysUntil(Carbon::parse($to)))->map(fn ($date) => $date->toDateString());

        return $labels->map(fn ($label) => [
            'label' => $label,
            'sales' => (float) $invoices->filter(fn ($row) => $key($row->invoice_date) === $label)->sum('total'),
            'cash_in' => (float) $transactions->filter(fn ($row) => $key($row->transaction_date) === $label && $row->direction === 'in')->sum('amount'),
            'cash_out' => (float) $transactions->filter(fn ($row) => $key($row->transaction_date) === $label && $row->direction === 'out')->sum('amount'),
            'expenses' => (float) $expenses->filter(fn ($row) => $key($row->expense_date) === $label)->sum('amount'),
        ])->values();
    }

    private function salesBreakdown(Collection $rows, string $groupKey, string $codeKey, string $nameKey): Collection
    {
        return $rows->groupBy(fn ($row) => $row->{$groupKey} ?: 'unassigned')->map(function ($items) use ($codeKey, $nameKey) {
            $first = $items->first();

            return [
                'code' => $first->{$codeKey} ?: '—',
                'name' => $first->{$nameKey} ?: 'Unassigned',
                'invoices' => $items->count(),
                'amount' => (float) $items->sum('total'),
            ];
        })->sortByDesc('amount')->values();
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'route_id' => ['nullable', 'integer', 'exists:routes,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'driver_id' => ['nullable', 'integer', 'exists:employees,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
        ]);
        $to = Carbon::parse($validated['date_to'] ?? now())->toDateString();
        $from = Carbon::parse($validated['date_from'] ?? Carbon::parse($to)->startOfMonth())->toDateString();

        return ['date_from' => $from, 'date_to' => $to] + collect(['route_id', 'customer_id', 'warehouse_id', 'employee_id', 'driver_id', 'vehicle_id'])
            ->mapWithKeys(fn ($key) => [$key => isset($validated[$key]) ? (int) $validated[$key] : null])->all();
    }

    private function meta(): array
    {
        $map = fn ($rows) => $rows->map(fn ($row) => ['id' => (int) $row->id, 'label' => trim(($row->code ? $row->code.' · ' : '').$row->name)]);

        return [
            'routes' => $map(DB::table('routes')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])),
            'customers' => DB::table('customers')->where('is_active', true)->orderBy('shop_name')->get(['id', 'code', 'shop_name as name'])->map(fn ($row) => ['id' => (int) $row->id, 'label' => "{$row->code} · {$row->name}"]),
            'warehouses' => $map(DB::table('warehouses')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])),
            'employees' => $map(DB::table('employees')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])),
            'drivers' => $map(DB::table('employees')->where('employee_type', 'driver')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])),
            'vehicles' => DB::table('vehicles')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'plate_no'])->map(fn ($row) => ['id' => (int) $row->id, 'label' => "{$row->code} · {$row->plate_no}"]),
        ];
    }

    private function grouping(string $from, string $to): string
    {
        return Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 62 ? 'month' : 'day';
    }

    private function numericRow(object $row, array $fields): array
    {
        $payload = (array) $row;
        foreach ($fields as $field) {
            $payload[$field] = (float) $payload[$field];
        }

        return $payload;
    }

    private function authorizePermission(Request $request): void
    {
        abort_unless(in_array('office.reports.operations.view', AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
