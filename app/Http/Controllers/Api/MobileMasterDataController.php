<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Services\CustomerCreditService;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileMasterDataController extends Controller
{
    public function __construct(private readonly CustomerCreditService $customerCredit)
    {
    }

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

    public function salesVisits(Request $request)
    {
        $this->authorizePermission($request, 'sales.customers.view');
        abort_unless($request->user()->role === 'Sales Representative', 403);

        $employee = DB::table('employees')->find($request->user()->employee_id);
        abort_unless($employee, 404, 'Sales employee record not found.');

        $date = now()->toDateString();
        $route = $employee->assigned_route_id
            ? DB::table('routes')->where('is_active', true)->find($employee->assigned_route_id)
            : null;
        $visits = DB::table('sales_route_visits')
            ->where('employee_id', $employee->id)
            ->whereDate('visit_date', $date)
            ->get()
            ->keyBy('customer_id');

        $customers = $route
            ? DB::table('customers')
                ->leftJoin('areas', 'customers.area_id', '=', 'areas.id')
                ->where('customers.route_id', $route->id)
                ->where('customers.is_active', true)
                ->whereNull('customers.deleted_at')
                ->orderBy('customers.shop_name')
                ->get([
                    'customers.id',
                    'customers.code',
                    'customers.shop_name',
                    'customers.contact_name',
                    'customers.phone',
                    'customers.address',
                    'customers.latitude',
                    'customers.longitude',
                    'customers.gps_accuracy_m',
                    'customers.gps_captured_at',
                    'areas.name as area',
                ])
                ->map(function ($customer) use ($visits) {
                    $visit = $visits->get($customer->id);
                    $customer->visit_status = $visit?->status ?? 'planned';
                    $customer->started_at = $visit?->started_at;
                    $customer->completed_at = $visit?->completed_at;
                    $customer->visit_latitude = $visit?->completed_latitude ?? $visit?->started_latitude;
                    $customer->visit_longitude = $visit?->completed_longitude ?? $visit?->started_longitude;
                    $customer->visit_accuracy_m = $visit?->completed_accuracy_m ?? $visit?->started_accuracy_m;

                    return $customer;
                })
            : collect();

        return ApiResponse::success('Customer visits loaded.', [
            'date' => $date,
            'route' => $route,
            'customers' => $customers,
            'summary' => [
                'total' => $customers->count(),
                'planned' => $customers->where('visit_status', 'planned')->count(),
                'in_progress' => $customers->where('visit_status', 'in_progress')->count(),
                'completed' => $customers->where('visit_status', 'completed')->count(),
                'skipped' => $customers->where('visit_status', 'skipped')->count(),
            ],
        ]);
    }

    public function updateSalesVisit(Request $request, int $customerId)
    {
        $this->authorizePermission($request, 'sales.customers.view');
        abort_unless($request->user()->role === 'Sales Representative', 403);

        $validated = $request->validate([
            'status' => ['required', 'in:in_progress,completed,skipped'],
            'latitude' => ['required_unless:status,skipped', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_unless:status,skipped', 'nullable', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = DB::table('employees')->find($request->user()->employee_id);
        abort_unless($employee?->assigned_route_id, 422, 'An assigned sales route is required.');
        $customer = DB::table('customers')
            ->where('id', $customerId)
            ->where('route_id', $employee->assigned_route_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();
        abort_unless($customer, 404, 'Customer is not assigned to this sales route.');

        $date = now()->toDateString();
        $existing = DB::table('sales_route_visits')
            ->where('employee_id', $employee->id)
            ->where('customer_id', $customer->id)
            ->whereDate('visit_date', $date)
            ->first();
        abort_if($existing?->status === 'completed', 422, 'This visit is already completed.');
        abort_if($validated['status'] === 'completed' && $existing?->status !== 'in_progress', 422, 'Start the visit before completing it.');

        $visitId = DB::transaction(function () use ($customer, $date, $employee, $existing, $validated) {
            $now = now();
            $values = [
                'route_id' => $employee->assigned_route_id,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? $existing?->notes,
                'updated_at' => $now,
            ];

            if ($validated['status'] === 'in_progress') {
                $values += [
                    'started_at' => $now,
                    'started_latitude' => $validated['latitude'],
                    'started_longitude' => $validated['longitude'],
                    'started_accuracy_m' => $validated['accuracy_m'] ?? null,
                ];
            } elseif ($validated['status'] === 'completed') {
                $values += [
                    'completed_at' => $now,
                    'completed_latitude' => $validated['latitude'],
                    'completed_longitude' => $validated['longitude'],
                    'completed_accuracy_m' => $validated['accuracy_m'] ?? null,
                ];
                DB::table('customers')->where('id', $customer->id)->update([
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'gps_accuracy_m' => $validated['accuracy_m'] ?? null,
                    'gps_captured_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($existing) {
                DB::table('sales_route_visits')->where('id', $existing->id)->update($values);
                return (int) $existing->id;
            }

            return (int) DB::table('sales_route_visits')->insertGetId($values + [
                'employee_id' => $employee->id,
                'customer_id' => $customer->id,
                'visit_date' => $date,
                'created_at' => $now,
            ]);
        });

        return ApiResponse::success('Customer visit updated.', [
            'visit' => DB::table('sales_route_visits')->find($visitId),
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

        $outstanding = $this->customerCredit->outstanding((int) $customer->id);
        $salesQuery = DB::table('invoices')->where('customer_id', $customer->id)->where('status', '!=', 'cancelled');
        $monthSales = (float) (clone $salesQuery)->whereDate('invoice_date', '>=', now()->startOfMonth()->toDateString())->sum('total');
        $yearSales = (float) (clone $salesQuery)->whereDate('invoice_date', '>=', now()->startOfYear()->toDateString())->sum('total');
        $topProducts = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.customer_id', $customer->id)
            ->where('invoices.status', '!=', 'cancelled')
            ->groupBy('invoice_items.product_id', 'invoice_items.product_sku', 'invoice_items.product_name')
            ->orderByDesc(DB::raw('SUM(invoice_items.quantity)'))
            ->limit(5)
            ->get(['invoice_items.product_id', 'invoice_items.product_sku', 'invoice_items.product_name', DB::raw('SUM(invoice_items.quantity) as quantity'), DB::raw('SUM(invoice_items.line_total) as sales')])
            ->map(fn ($item) => ['product_id' => $item->product_id, 'sku' => $item->product_sku, 'name' => $item->product_name, 'quantity' => (float) $item->quantity, 'sales' => (float) $item->sales]);

        return ApiResponse::success('Customer loaded.', [
            'customer' => $customer,
            'orders' => $orders,
            'order_summary' => [
                'orders_count' => (int) ($orderSummary->orders_count ?? 0),
                'pending_count' => (int) ($orderSummary->pending_count ?? 0),
                'total_amount' => (float) ($orderSummary->total_amount ?? 0),
                'average_order_value' => (float) (($orderSummary->orders_count ?? 0) ? $orderSummary->total_amount / $orderSummary->orders_count : 0),
                'last_order_date' => $orders->first()?->order_date,
                'outstanding_balance' => $outstanding,
                'available_credit' => (float) $customer->credit_limit > 0 ? (float) $customer->credit_limit - $outstanding : null,
                'monthly_sales' => $monthSales,
                'yearly_sales' => $yearSales,
            ],
            'top_products' => $topProducts,
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

        abort_if(DB::table('customers')->where('route_id', $employee->assigned_route_id)->where('phone', $validated['phone'])->exists(), 422, 'A customer with this phone number already exists on your route.');

        $route = DB::table('routes')->find($employee->assigned_route_id);
        $nextId = ((int) DB::table('customers')->max('id')) + 1;
        $validated += [
            'code' => 'CUS-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT),
            'area_id' => $route->area_id,
            'route_id' => $route->id,
            'price_type_id' => DB::table('price_types')->where('is_default', true)->value('id'),
            'credit_limit' => (float) (DB::table('companies')->oldest('id')->value('default_customer_credit_limit') ?? 500000),
            'is_active' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('customers')->insertGetId($validated);

        return ApiResponse::success('Customer registration submitted.', [
            'customer' => DB::table('customers')->find($id),
        ], 201);
    }

    public function updateCustomer(Request $request, int $id)
    {
        $this->authorizePermission($request, 'sales.customers.create');
        $routeId = DB::table('employees')->where('id', $request->user()->employee_id)->value('assigned_route_id');
        $customer = DB::table('customers')->where('id', $id)->where('route_id', $routeId)->first();
        abort_unless($customer, 404);
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:150'],
            'contact_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'gps_accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
        abort_if(DB::table('customers')->where('route_id', $routeId)->where('phone', $validated['phone'])->where('id', '!=', $id)->exists(), 422, 'A customer with this phone number already exists on your route.');

        if (array_key_exists('latitude', $validated) || array_key_exists('longitude', $validated)) {
            $validated['gps_captured_at'] = isset($validated['latitude'], $validated['longitude']) ? now() : null;
            if ($validated['gps_captured_at'] === null) {
                $validated['gps_accuracy_m'] = null;
            }
        }

        DB::table('customers')->where('id', $id)->update($validated + ['updated_at' => now()]);

        return ApiResponse::success('Customer updated.', ['customer' => DB::table('customers')->find($id)]);
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

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:accepted,rejected'],
            'date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = DB::table('attendance_records')
            ->leftJoin('attendance_locations', 'attendance_records.attendance_location_id', '=', 'attendance_locations.id')
            ->leftJoin('warehouses', 'attendance_locations.warehouse_id', '=', 'warehouses.id')
            ->where('attendance_records.employee_id', $user->employee_id)
            ->select('attendance_records.*', 'attendance_locations.name as location_name', 'warehouses.name as warehouse_name');

        if (! empty($validated['month'])) {
            $monthStart = \Carbon\Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
            $query->whereBetween('attendance_records.attendance_at', [$monthStart, $monthStart->copy()->endOfMonth()]);
        }

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
            ->paginate(min(max((int) $request->query('per_page', 31), 1), 100));

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

    public function attendanceLocations(Request $request)
    {
        $this->authorizeMobileAttendance($request);
        abort_unless($request->user()->employee_id, 404, 'Employee profile is not linked to this account.');

        $locations = DB::table('attendance_locations')
            ->join('warehouses', 'attendance_locations.warehouse_id', '=', 'warehouses.id')
            ->where('attendance_locations.is_active', true)
            ->where('warehouses.is_active', true)
            ->orderBy('warehouses.name')
            ->orderBy('attendance_locations.name')
            ->get([
                'attendance_locations.id',
                'attendance_locations.name as location_name',
                'attendance_locations.address as location_address',
                'attendance_locations.allowed_radius_m',
                'warehouses.id as warehouse_id',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
                'warehouses.address as warehouse_address',
            ]);

        $todayRecord = DB::table('attendance_records')
            ->leftJoin('attendance_locations', 'attendance_records.attendance_location_id', '=', 'attendance_locations.id')
            ->leftJoin('warehouses', 'attendance_locations.warehouse_id', '=', 'warehouses.id')
            ->where('attendance_records.employee_id', $request->user()->employee_id)
            ->where('attendance_records.status', 'accepted')
            ->whereDate('attendance_records.attendance_at', today())
            ->orderByDesc('attendance_records.attendance_at')
            ->first([
                'attendance_records.id',
                'attendance_records.attendance_at',
                'attendance_records.distance_m',
                'attendance_locations.name as location_name',
                'warehouses.name as warehouse_name',
            ]);

        return ApiResponse::success('Attendance warehouses loaded.', [
            'locations' => $locations,
            'today_record' => $todayRecord,
        ]);
    }

    public function recordAttendance(Request $request)
    {
        $this->authorizeMobileAttendance($request);
        $user = $request->user();
        abort_unless($user->employee_id, 404, 'Employee profile is not linked to this account.');

        $validated = $request->validate([
            'attendance_location_id' => ['required', 'integer', 'exists:attendance_locations,id'],
            'gps_denied' => ['sometimes', 'boolean'],
            'latitude' => ['required_unless:gps_denied,true', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_unless:gps_denied,true', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        $employee = DB::table('employees')->where('id', $user->employee_id)->where('is_active', true)->first();
        abort_unless($employee, 404, 'Employee profile is inactive or unavailable.');

        $location = DB::table('attendance_locations')
            ->join('warehouses', 'attendance_locations.warehouse_id', '=', 'warehouses.id')
            ->where('attendance_locations.id', $validated['attendance_location_id'])
            ->where('attendance_locations.is_active', true)
            ->where('warehouses.is_active', true)
            ->first([
                'attendance_locations.*',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
            ]);
        abort_unless($location, 422, 'The selected warehouse attendance point is unavailable.');

        $existing = DB::table('attendance_records')
            ->leftJoin('attendance_locations', 'attendance_records.attendance_location_id', '=', 'attendance_locations.id')
            ->leftJoin('warehouses', 'attendance_locations.warehouse_id', '=', 'warehouses.id')
            ->where('attendance_records.employee_id', $employee->id)
            ->where('attendance_records.status', 'accepted')
            ->whereDate('attendance_records.attendance_at', today())
            ->orderByDesc('attendance_records.attendance_at')
            ->first([
                'attendance_records.*',
                'attendance_locations.name as recorded_location_name',
                'attendance_locations.allowed_radius_m as recorded_allowed_radius_m',
                'warehouses.id as recorded_warehouse_id',
                'warehouses.name as recorded_warehouse_name',
            ]);

        if ($existing) {
            $recordedLocation = (object) [
                'name' => $existing->recorded_location_name,
                'allowed_radius_m' => $existing->recorded_allowed_radius_m,
                'warehouse_id' => $existing->recorded_warehouse_id,
                'warehouse_name' => $existing->recorded_warehouse_name,
            ];

            return ApiResponse::success('Attendance was already recorded today.', [
                'result' => $this->mobileAttendanceResult($existing, $recordedLocation, $employee, true),
            ]);
        }

        $reason = null;
        $distance = null;

        if ($request->boolean('gps_denied')) {
            $reason = 'gps_denied';
        } else {
            $distance = $this->distanceInMeters(
                (float) $location->latitude,
                (float) $location->longitude,
                (float) $validated['latitude'],
                (float) $validated['longitude']
            );

            if ($distance > $location->allowed_radius_m) {
                $reason = 'outside_allowed_radius';
            }
        }

        $record = AttendanceRecord::create([
            'attendance_location_id' => $location->id,
            'employee_id' => $employee->id,
            'entered_employee_code' => $employee->code,
            'submitted_token' => null,
            'attendance_at' => now(),
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'distance_m' => $distance === null ? null : round($distance, 2),
            'status' => $reason ? 'rejected' : 'accepted',
            'rejection_reason' => $reason,
        ]);

        return ApiResponse::success($reason ? 'Attendance was rejected.' : 'Attendance recorded successfully.', [
            'result' => $this->mobileAttendanceResult($record, $location, $employee),
        ], 201);
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

    private function authorizeMobileAttendance(Request $request): void
    {
        $role = $request->user()->role;
        abort_unless(in_array($role, ['Driver', 'Sales Representative'], true), 403);
        $this->authorizePermission($request, $role === 'Driver' ? 'driver.attendance.view' : 'sales.attendance.view');
    }

    private function mobileAttendanceResult(object $record, object $location, object $employee, bool $alreadyRecorded = false): array
    {
        return [
            'record_id' => $record->id,
            'status' => $record->status,
            'rejection_reason' => $record->rejection_reason,
            'distance_m' => $record->distance_m,
            'allowed_radius_m' => $location->allowed_radius_m,
            'employee_name' => $employee->name,
            'location_name' => $location->name,
            'warehouse_id' => $location->warehouse_id,
            'warehouse_name' => $location->warehouse_name,
            'attendance_at' => $record->attendance_at,
            'already_recorded' => $alreadyRecorded,
        ];
    }

    private function distanceInMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
