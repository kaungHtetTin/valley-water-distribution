<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MobileMasterDataController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'Customer') {
            $this->authorizePermission($request, 'client.profile.view');
            $profile = DB::table('customers')
                ->leftJoin('areas', 'customers.area_id', '=', 'areas.id')
                ->leftJoin('routes', 'customers.route_id', '=', 'routes.id')
                ->leftJoin('price_types', 'customers.price_type_id', '=', 'price_types.id')
                ->where('customers.id', $user->customer_id)
                ->select('customers.*', 'areas.name as area', 'routes.name as route', 'routes.service_day', 'price_types.name as price_type')
                ->first();
        } else {
            $permission = $user->role === 'Driver' ? 'driver.profile.view' : 'sales.profile.view';
            $this->authorizePermission($request, $permission);
            $profile = DB::table('employees')
                ->leftJoin('routes', 'employees.assigned_route_id', '=', 'routes.id')
                ->where('employees.id', $user->employee_id)
                ->select('employees.*', 'routes.name as assigned_route', 'routes.service_day')
                ->first();
        }

        abort_unless($profile, 404);

        return ApiResponse::success('Profile loaded.', ['profile' => $profile]);
    }

    public function assignedCustomers(Request $request)
    {
        $this->authorizePermission($request, 'sales.customers.view');
        $employee = DB::table('employees')->find($request->user()->employee_id);
        abort_unless($employee, 404);

        $query = DB::table('customers')
            ->leftJoin('routes', 'customers.route_id', '=', 'routes.id')
            ->leftJoin('areas', 'customers.area_id', '=', 'areas.id')
            ->where('customers.route_id', $employee->assigned_route_id)
            ->select('customers.*', 'routes.name as route', 'areas.name as area');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('customers.shop_name', 'like', "%{$search}%")
                    ->orWhere('customers.contact_name', 'like', "%{$search}%")
                    ->orWhere('customers.code', 'like', "%{$search}%")
                    ->orWhere('customers.phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('customers.is_active', $request->boolean('is_active'));
        }

        $paginator = $query->orderBy('customers.shop_name')->paginate(min(max((int) $request->query('per_page', 10), 1), 50));

        return ApiResponse::success('Assigned customers loaded.', [
            'items' => $paginator->items(),
            'route' => $employee->assigned_route_id ? DB::table('routes')->find($employee->assigned_route_id) : null,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function customer(Request $request, int $id)
    {
        $this->authorizePermission($request, 'sales.customers.view');
        $routeId = DB::table('employees')->where('id', $request->user()->employee_id)->value('assigned_route_id');
        $customer = DB::table('customers')
            ->leftJoin('routes', 'customers.route_id', '=', 'routes.id')
            ->leftJoin('areas', 'customers.area_id', '=', 'areas.id')
            ->leftJoin('price_types', 'customers.price_type_id', '=', 'price_types.id')
            ->where('customers.id', $id)
            ->where('customers.route_id', $routeId)
            ->select('customers.*', 'routes.name as route', 'areas.name as area', 'price_types.name as price_type')
            ->first();
        abort_unless($customer, 404);

        $ordersQuery = DB::table('orders')
            ->leftJoin('invoices', function ($join) {
                $join->on('orders.id', '=', 'invoices.order_id')
                    ->where('invoices.status', '!=', 'cancelled');
            })
            ->where('orders.customer_id', $customer->id)
            ->where('orders.route_id', $routeId);

        $orderSummary = (clone $ordersQuery)
            ->selectRaw("COUNT(*) as orders_count, COALESCE(SUM(orders.total), 0) as total_amount, SUM(CASE WHEN orders.status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->first();

        $orders = $ordersQuery
            ->select('orders.id', 'orders.code', 'orders.order_date', 'orders.requested_delivery_date', 'orders.payment_type', 'orders.status', 'orders.total', 'invoices.code as invoice_code')
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.id')
            ->limit(25)
            ->get()
            ->map(function ($order) {
                $order->total = (float) $order->total;
                return $order;
            });

        return ApiResponse::success('Customer loaded.', [
            'customer' => $customer,
            'orders' => $orders,
            'order_summary' => [
                'orders_count' => (int) ($orderSummary->orders_count ?? 0),
                'pending_count' => (int) ($orderSummary->pending_count ?? 0),
                'total_amount' => (float) ($orderSummary->total_amount ?? 0),
            ],
        ]);
    }

    public function salesRoute(Request $request)
    {
        $this->authorizePermission($request, 'sales.route.view');
        $employee = DB::table('employees')->find($request->user()->employee_id);
        abort_unless($employee && $employee->employee_type === 'sales' && $employee->assigned_route_id, 422, 'A sales territory must be assigned before opening customer visits.');

        $date = now()->toDateString();
        $route = DB::table('routes')->leftJoin('areas', 'routes.area_id', '=', 'areas.id')
            ->where('routes.id', $employee->assigned_route_id)
            ->first(['routes.*', 'areas.name as area_name']);

        $orders = DB::table('orders')->whereDate('order_date', $date)
            ->where('source_app', 'sales')
            ->where('created_by', $request->user()->id)
            ->selectRaw('customer_id, COUNT(*) as orders_count, COALESCE(SUM(total), 0) as order_amount')
            ->groupBy('customer_id');
        $customers = DB::table('customers')
            ->leftJoin('areas', 'customers.area_id', '=', 'areas.id')
            ->leftJoin('sales_route_visits', function ($join) use ($employee, $date) {
                $join->on('customers.id', '=', 'sales_route_visits.customer_id')
                    ->where('sales_route_visits.employee_id', '=', $employee->id)
                    ->where('sales_route_visits.visit_date', '=', $date);
            })
            ->leftJoinSub($orders, 'today_orders', 'customers.id', '=', 'today_orders.customer_id')
            ->where('customers.route_id', $employee->assigned_route_id)
            ->where('customers.is_active', true)
            ->orderBy('customers.shop_name')
            ->get([
                'customers.id', 'customers.code', 'customers.shop_name', 'customers.contact_name', 'customers.phone', 'customers.address',
                'areas.name as area', 'sales_route_visits.status as visit_status', 'sales_route_visits.started_at', 'sales_route_visits.completed_at',
                DB::raw('COALESCE(today_orders.orders_count, 0) as orders_count'), DB::raw('COALESCE(today_orders.order_amount, 0) as order_amount'),
            ])->map(function ($customer) {
                $customer->visit_status = $customer->visit_status ?: 'planned';
                $customer->orders_count = (int) $customer->orders_count;
                $customer->order_amount = (float) $customer->order_amount;
                return $customer;
            });

        return ApiResponse::success('Sales customer visits loaded.', [
            'route' => $route,
            'date' => $date,
            'customers' => $customers,
            'summary' => [
                'total' => $customers->count(),
                'completed' => $customers->where('visit_status', 'completed')->count(),
                'in_progress' => $customers->where('visit_status', 'in_progress')->count(),
                'skipped' => $customers->where('visit_status', 'skipped')->count(),
                'orders_count' => $customers->sum('orders_count'),
                'order_amount' => (float) $customers->sum('order_amount'),
            ],
        ]);
    }

    public function updateSalesRouteVisit(Request $request, int $customerId)
    {
        $this->authorizePermission($request, 'sales.route.view');
        $employee = DB::table('employees')->find($request->user()->employee_id);
        abort_unless($employee && $employee->employee_type === 'sales' && $employee->assigned_route_id, 422, 'A sales territory must be assigned before recording customer visits.');
        abort_unless(DB::table('customers')->where('id', $customerId)->where('route_id', $employee->assigned_route_id)->where('is_active', true)->exists(), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'completed', 'skipped'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $date = now()->toDateString();
        $existing = DB::table('sales_route_visits')->where('employee_id', $employee->id)->where('customer_id', $customerId)->where('visit_date', $date)->first();
        $now = now();

        DB::table('sales_route_visits')->updateOrInsert(
            ['employee_id' => $employee->id, 'customer_id' => $customerId, 'visit_date' => $date],
            [
                'route_id' => $employee->assigned_route_id,
                'status' => $validated['status'],
                'started_at' => $existing?->started_at ?: $now,
                'completed_at' => in_array($validated['status'], ['completed', 'skipped'], true) ? $now : null,
                'notes' => $validated['notes'] ?? $existing?->notes,
                'created_at' => $existing?->created_at ?: $now,
                'updated_at' => $now,
            ]
        );

        return ApiResponse::success('Customer visit updated.', [
            'visit' => DB::table('sales_route_visits')->where('employee_id', $employee->id)->where('customer_id', $customerId)->where('visit_date', $date)->first(),
        ]);
    }

    public function storeCustomer(Request $request)
    {
        $this->authorizePermission($request, 'sales.customers.create');
        $employee = DB::table('employees')->find($request->user()->employee_id);
        abort_unless($employee && $employee->assigned_route_id, 422, 'A route must be assigned before registering customers.');

        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:150'],
            'contact_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $route = DB::table('routes')->find($employee->assigned_route_id);
        $nextId = ((int) DB::table('customers')->max('id')) + 1;
        $validated += [
            'code' => 'CUS-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT),
            'area_id' => $route->area_id,
            'route_id' => $route->id,
            'price_type_id' => DB::table('price_types')->where('is_default', true)->value('id'),
            'credit_limit' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('customers')->insertGetId($validated);

        return ApiResponse::success('Customer registration submitted.', [
            'customer' => DB::table('customers')->find($id),
        ], 201);
    }

    public function assignedVehicle(Request $request)
    {
        $this->authorizePermission($request, 'driver.vehicle.view');
        $vehicle = DB::table('vehicles')
            ->where('assigned_driver_id', $request->user()->employee_id)
            ->where('is_active', true)
            ->first();

        return ApiResponse::success('Assigned vehicle loaded.', ['vehicle' => $vehicle]);
    }

    public function attendanceRecords(Request $request)
    {
        $user = $request->user();
        $permission = $user->role === 'Driver' ? 'driver.attendance.view' : 'sales.attendance.view';
        $this->authorizePermission($request, $permission);

        abort_unless($user->employee_id, 404);

        $query = DB::table('attendance_records')
            ->leftJoin('attendance_locations', 'attendance_records.attendance_location_id', '=', 'attendance_locations.id')
            ->where('attendance_records.employee_id', $user->employee_id)
            ->select('attendance_records.*', 'attendance_locations.name as location_name');

        if ($request->filled('status')) {
            $query->where('attendance_records.status', $request->query('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('attendance_records.attendance_at', $request->query('date'));
        }

        $summary = (clone $query)
            ->select('attendance_records.status', DB::raw('count(*) as total'))
            ->groupBy('attendance_records.status')
            ->pluck('total', 'attendance_records.status');

        $paginator = $query->orderByDesc('attendance_records.attendance_at')
            ->paginate(min(max((int) $request->query('per_page', 12), 1), 50));

        return ApiResponse::success('Attendance history loaded.', [
            'items' => $paginator->items(),
            'summary' => [
                'accepted' => (int) ($summary['accepted'] ?? 0),
                'rejected' => (int) ($summary['rejected'] ?? 0),
                'total' => (int) $summary->sum(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function payrollHistory(Request $request)
    {
        $user = $request->user();
        $permission = $user->role === 'Driver' ? 'driver.payroll.view' : 'sales.payroll.view';
        $this->authorizePermission($request, $permission);

        abort_unless($user->employee_id, 404);

        $query = DB::table('payroll_items')
            ->join('payrolls', 'payroll_items.payroll_id', '=', 'payrolls.id')
            ->where('payroll_items.employee_id', $user->employee_id)
            ->where('payrolls.status', 'paid');

        if ($request->filled('month')) {
            $query->where('payrolls.month', $request->query('month'));
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as payments_count, COALESCE(SUM(payroll_items.net_pay), 0) as total_net, MAX(payrolls.paid_at) as latest_paid_at')
            ->first();

        $paginator = $query->select(
            'payroll_items.*',
            'payrolls.code as payroll_code',
            'payrolls.month',
            'payrolls.period_start',
            'payrolls.period_end',
            'payrolls.status',
            'payrolls.paid_at',
            'payrolls.payment_reference'
        )
            ->orderByDesc('payrolls.month')
            ->paginate(min(max((int) $request->query('per_page', 12), 1), 50));

        return ApiResponse::success('Salary history loaded.', [
            'items' => collect($paginator->items())->map(fn ($item) => [
                'id' => $item->id,
                'payroll_id' => $item->payroll_id,
                'payroll_code' => $item->payroll_code,
                'month' => $item->month,
                'period_start' => $item->period_start,
                'period_end' => $item->period_end,
                'status' => $item->status,
                'paid_at' => $item->paid_at,
                'payment_reference' => $item->payment_reference,
                'employee_code' => $item->employee_code,
                'employee_name' => $item->employee_name,
                'accepted_count' => (int) $item->accepted_count,
                'rejected_count' => (int) $item->rejected_count,
                'base_salary' => (float) $item->base_salary,
                'allowance_amount' => (float) $item->allowance_amount,
                'incentive_amount' => (float) $item->incentive_amount,
                'ot_amount' => (float) $item->ot_amount,
                'advance_deduction' => (float) $item->advance_deduction,
                'other_deduction' => (float) $item->other_deduction,
                'gross_pay' => (float) $item->gross_pay,
                'net_pay' => (float) $item->net_pay,
                'remarks' => $item->remarks,
            ]),
            'summary' => [
                'payments_count' => (int) ($summary->payments_count ?? 0),
                'total_net' => (float) ($summary->total_net ?? 0),
                'latest_paid_at' => $summary->latest_paid_at ?? null,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()->role), true), 403);
    }
}
