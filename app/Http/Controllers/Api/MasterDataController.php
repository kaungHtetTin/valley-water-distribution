<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerCreditService;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MasterDataController extends Controller
{
    private const DEFAULT_KPI_TEMPLATE_BY_EMPLOYEE_TYPE = [
        'office' => 'OFFICE-STAFF-V1',
        'sales' => 'SALES-REP-V1',
        'sales_supervisor' => 'SALES-SUPERVISOR-V1',
        'driver' => 'DRIVER-V1',
        'warehouse' => 'STOREKEEPER-V1',
    ];

    public function __construct(private readonly CustomerCreditService $customerCredit) {}

    public function meta(Request $request)
    {
        $this->authorizeAnyMasterView($request);

        $resources = collect($this->resources())->filter(fn ($config, $key) => $this->canUseResource($request, $key, 'view'))->map(fn ($config, $key) => [
            'key' => $key,
            'label' => $config['label'],
            'singular' => $config['singular'],
            'description' => $config['description'],
            'fields' => $key === 'employees' && ! $this->canManageUserAccess($request)
                ? array_values(array_filter($config['fields'], fn ($field) => ! in_array($field['name'], ['access_role', 'password', 'password_confirmation'], true)))
                : $config['fields'],
            'list' => $config['list'],
        ])->values();

        return ApiResponse::success('Master data setup loaded.', [
            'resources' => $resources,
            'options' => $this->optionLists($request),
        ]);
    }

    public function index(Request $request, string $resource)
    {
        $config = $this->resource($resource);
        $this->authorizeMasterResource($request, $resource, 'view');
        $query = DB::table($config['table']);
        if (Schema::hasColumn($config['table'], 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($config, $search) {
                foreach ($config['search'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        foreach ($config['filters'] ?? [] as $filter) {
            if ($request->filled($filter)) {
                $value = $request->query($filter);
                $value === '__none__' ? $query->whereNull($filter) : $query->where($filter, $value);
            }
        }

        if ($resource === 'employees') {
            if ($request->filled('assigned_vehicle_id')) {
                $vehicleId = $request->query('assigned_vehicle_id');
                $relation = fn ($vehicles) => $vehicles
                    ->selectRaw('1')
                    ->from('vehicles')
                    ->whereColumn('vehicles.assigned_driver_id', 'employees.id');

                $vehicleId === '__none__'
                    ? $query->whereNotExists($relation)
                    : $query->whereExists(fn ($vehicles) => $relation($vehicles)->where('vehicles.id', $vehicleId));
            }

            if ($request->filled('hire_date_from')) {
                $query->whereDate('hire_date', '>=', $request->query('hire_date_from'));
            }
            if ($request->filled('hire_date_to')) {
                $query->whereDate('hire_date', '<=', $request->query('hire_date_to'));
            }
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $paginator = $query->orderBy($config['sort'][0], $config['sort'][1])->paginate($perPage);
        $items = collect($paginator->items())->map(fn ($item) => $this->decorate($resource, (array) $item));

        return ApiResponse::success("{$config['label']} loaded.", [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $resource, int $id)
    {
        $config = $this->resource($resource);
        $this->authorizeMasterResource($request, $resource, 'view');
        $query = DB::table($config['table'])->where('id', $id);
        if (Schema::hasColumn($config['table'], 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $item = $query->first();
        abort_unless($item, 404);

        return ApiResponse::success("{$config['singular']} loaded.", [
            'item' => $this->decorate($resource, (array) $item),
        ]);
    }

    public function customerDetail(Request $request, int $id)
    {
        $this->authorizeOffice($request, 'office.master-data.view');

        $customer = DB::table('customers')
            ->leftJoin('areas', 'customers.area_id', '=', 'areas.id')
            ->leftJoin('routes', 'customers.route_id', '=', 'routes.id')
            ->leftJoin('price_types', 'customers.price_type_id', '=', 'price_types.id')
            ->where('customers.id', $id)
            ->select(
                'customers.*',
                'areas.name as area',
                'routes.name as route',
                'price_types.name as price_type',
            )
            ->first();
        abort_unless($customer, 404);

        $itemCounts = DB::table('order_items')
            ->select('order_id')
            ->selectRaw('COUNT(*) as items_count')
            ->groupBy('order_id');

        $sales = DB::table('orders')
            ->leftJoinSub($itemCounts, 'order_item_counts', fn ($join) => $join->on('orders.id', '=', 'order_item_counts.order_id'))
            ->leftJoin('invoices', function ($join) {
                $join->on('orders.id', '=', 'invoices.order_id')
                    ->where('invoices.status', '!=', 'cancelled');
            })
            ->where('orders.customer_id', $id)
            ->whereNull('orders.original_order_id')
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.id')
            ->limit(50)
            ->get([
                'orders.id', 'orders.code', 'orders.order_date', 'orders.requested_delivery_date',
                'orders.payment_type', 'orders.status', 'orders.total',
                'invoices.code as invoice_code', 'invoices.due_date',
                DB::raw('COALESCE(order_item_counts.items_count, 0) as items_count'),
            ])
            ->map(function ($order) {
                $order->total = (float) $order->total;
                $order->items_count = (int) $order->items_count;

                return $order;
            });

        $validSales = $sales->where('status', '!=', 'cancelled');
        $credit = $this->customerCredit->summary($id);
        $pendingAmount = (float) DB::table('collections')
            ->where('customer_id', $id)
            ->where('status', 'submitted')
            ->sum('amount');

        $payments = DB::table('collections')
            ->leftJoin('invoices', 'collections.invoice_id', '=', 'invoices.id')
            ->leftJoin('employees', 'collections.employee_id', '=', 'employees.id')
            ->where('collections.customer_id', $id)
            ->orderByDesc('collections.collection_date')
            ->orderByDesc('collections.id')
            ->limit(50)
            ->get([
                'collections.id', 'collections.code', 'collections.collection_date', 'collections.amount',
                'collections.payment_method', 'collections.reference_no', 'collections.source_app',
                'collections.status', 'invoices.code as invoice_code', 'employees.name as employee_name',
            ])
            ->map(function ($payment) {
                $payment->amount = (float) $payment->amount;

                return $payment;
            });

        $customer->credit_limit = (float) $customer->credit_limit;

        return ApiResponse::success('Customer detail loaded.', [
            'customer' => $customer,
            'summary' => [
                'orders_count' => $validSales->count(),
                'total_sales' => (float) $validSales->sum('total'),
                'average_order' => $validSales->count() ? (float) $validSales->avg('total') : 0,
                'last_order_date' => $validSales->first()?->order_date,
                'credit_sales_amount' => $credit['credit_sales_amount'],
                'collected_amount' => $credit['payments_amount'],
                'pending_collection_amount' => $pendingAmount,
                'outstanding_amount' => $credit['outstanding_amount'],
                'customer_credit_amount' => $credit['customer_credit_amount'],
                'available_credit' => $customer->credit_limit > 0
                    ? max($customer->credit_limit - $credit['outstanding_amount'] + $credit['customer_credit_amount'], 0)
                    : null,
            ],
            'sales' => $sales->values(),
            'payments' => $payments,
        ]);
    }

    public function employeeDetail(Request $request, int $id)
    {
        $this->authorizeMasterResource($request, 'employees', 'view');

        $validated = $request->validate([
            'attendance_month' => ['nullable', 'date_format:Y-m'],
        ]);

        $employee = DB::table('employees')
            ->leftJoin('routes', 'employees.assigned_route_id', '=', 'routes.id')
            ->leftJoin('employees as supervisors', 'employees.supervisor_id', '=', 'supervisors.id')
            ->where('employees.id', $id)
            ->select('employees.*', 'routes.name as assigned_route', 'supervisors.name as supervisor_name', 'supervisors.code as supervisor_code')
            ->first();
        abort_unless($employee, 404);

        $vehicle = DB::table('vehicles')
            ->where('assigned_driver_id', $id)
            ->orderBy('id')
            ->first(['id', 'code', 'plate_no', 'vehicle_type', 'make', 'model', 'capacity', 'is_active']);
        $availableVehicles = $employee->employee_type === 'driver'
            ? DB::table('vehicles')
                ->where('is_active', true)
                ->where(function ($query) use ($id) {
                    $query->whereNull('assigned_driver_id')->orWhere('assigned_driver_id', $id);
                })
                ->orderBy('code')
                ->get(['id', 'code', 'plate_no', 'vehicle_type', 'make', 'model', 'capacity'])
            : collect();
        $teamMembers = $employee->employee_type === 'sales_supervisor'
            ? DB::table('employees')
                ->leftJoin('routes', 'employees.assigned_route_id', '=', 'routes.id')
                ->where('employees.supervisor_id', $id)
                ->where('employees.employee_type', 'sales')
                ->orderBy('employees.name')
                ->get(['employees.id', 'employees.code', 'employees.name', 'employees.phone', 'employees.email', 'employees.is_active', 'routes.name as assigned_route'])
            : collect();
        $availableSalesRepresentatives = $employee->employee_type === 'sales_supervisor'
            ? DB::table('employees')
                ->leftJoin('routes', 'employees.assigned_route_id', '=', 'routes.id')
                ->where('employees.employee_type', 'sales')
                ->where('employees.is_active', true)
                ->where(function ($query) use ($id) {
                    $query->whereNull('employees.supervisor_id')->orWhere('employees.supervisor_id', $id);
                })
                ->orderBy('employees.name')
                ->get(['employees.id', 'employees.code', 'employees.name', 'employees.phone', 'employees.email', 'routes.name as assigned_route'])
            : collect();
        $userId = DB::table('users')->where('employee_id', $id)->value('id');
        $monthStart = isset($validated['attendance_month'])
            ? Carbon::createFromFormat('Y-m', $validated['attendance_month'])->startOfMonth()
            : now()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $attendanceQuery = DB::table('attendance_records')->where('employee_id', $id);
        $attendanceSummary = [
            'accepted_this_month' => (clone $attendanceQuery)->where('status', 'accepted')->whereBetween('attendance_at', [$monthStart, $monthEnd])->count(),
            'rejected_this_month' => (clone $attendanceQuery)->where('status', '!=', 'accepted')->whereBetween('attendance_at', [$monthStart, $monthEnd])->count(),
            'last_attendance_at' => (clone $attendanceQuery)->max('attendance_at'),
        ];
        $attendance = (clone $attendanceQuery)
            ->leftJoin('attendance_locations', 'attendance_records.attendance_location_id', '=', 'attendance_locations.id')
            ->whereBetween('attendance_records.attendance_at', [$monthStart, $monthEnd])
            ->orderByDesc('attendance_records.attendance_at')
            ->get([
                'attendance_records.id', 'attendance_records.attendance_at', 'attendance_records.status',
                'attendance_records.distance_m', 'attendance_records.rejection_reason',
                'attendance_locations.name as location_name',
            ]);

        $payroll = DB::table('payroll_items')
            ->join('payrolls', 'payroll_items.payroll_id', '=', 'payrolls.id')
            ->where('payroll_items.employee_id', $id)
            ->orderByDesc('payrolls.month')
            ->limit(24)
            ->get([
                'payroll_items.id', 'payrolls.code', 'payrolls.month', 'payrolls.status',
                'payroll_items.base_salary', 'payroll_items.allowance_amount', 'payroll_items.incentive_amount',
                'payroll_items.ot_amount', 'payroll_items.gross_pay', 'payroll_items.advance_deduction',
                'payroll_items.other_deduction', 'payroll_items.net_pay',
            ])
            ->map(function ($item) {
                foreach (['base_salary', 'allowance_amount', 'incentive_amount', 'ot_amount', 'gross_pay', 'advance_deduction', 'other_deduction', 'net_pay'] as $field) {
                    $item->{$field} = (float) $item->{$field};
                }

                return $item;
            });

        [$roleMetrics, $activity] = match ($employee->employee_type) {
            'sales' => $this->salesEmployeeActivity($employee, $userId),
            'sales_supervisor' => $this->salesSupervisorActivity($employee),
            'driver' => $this->driverEmployeeActivity($employee),
            'warehouse' => $this->warehouseEmployeeActivity($userId),
            default => $this->officeEmployeeActivity($userId),
        };

        $employee->assigned_vehicle_id = $vehicle?->id;
        $employee->assigned_vehicle = $vehicle ? $vehicle->code.' · '.$vehicle->plate_no : null;

        return ApiResponse::success('Employee detail loaded.', [
            'employee' => $employee,
            'vehicle' => $vehicle,
            'available_vehicles' => $availableVehicles,
            'team_members' => $teamMembers,
            'available_sales_representatives' => $availableSalesRepresentatives,
            'attendance_summary' => $attendanceSummary,
            'attendance' => $attendance,
            'payroll' => $payroll,
            'role_metrics' => $roleMetrics,
            'activity' => $activity,
        ]);
    }

    public function assignEmployeeVehicle(Request $request, int $id)
    {
        $this->authorizeMasterResource($request, 'employees', 'manage');

        $employee = DB::table('employees')->where('id', $id)->first(['id', 'employee_type']);
        abort_unless($employee, 404);
        if ($employee->employee_type !== 'driver') {
            throw ValidationException::withMessages([
                'assigned_vehicle_id' => 'Vehicles can only be assigned to driver employees.',
            ]);
        }

        $validated = $request->validate([
            'assigned_vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('is_active', true)],
        ]);

        DB::transaction(function () use ($id, $validated) {
            $this->syncAssignedVehicle($id, $validated['assigned_vehicle_id'] ?? null);
        });

        $vehicle = DB::table('vehicles')
            ->where('assigned_driver_id', $id)
            ->orderBy('id')
            ->first(['id', 'code', 'plate_no', 'vehicle_type', 'make', 'model', 'capacity', 'is_active']);
        $availableVehicles = DB::table('vehicles')
            ->where('is_active', true)
            ->where(function ($query) use ($id) {
                $query->whereNull('assigned_driver_id')->orWhere('assigned_driver_id', $id);
            })
            ->orderBy('code')
            ->get(['id', 'code', 'plate_no', 'vehicle_type', 'make', 'model', 'capacity']);

        return ApiResponse::success('Vehicle assignment updated.', [
            'vehicle' => $vehicle,
            'assigned_vehicle_id' => $vehicle?->id,
            'assigned_vehicle' => $vehicle ? $vehicle->code.' · '.$vehicle->plate_no : null,
            'available_vehicles' => $availableVehicles,
        ]);
    }

    public function assignSupervisorTeam(Request $request, int $id)
    {
        $this->authorizeMasterResource($request, 'employees', 'manage');

        $supervisor = DB::table('employees')->where('id', $id)->first(['id', 'employee_type']);
        abort_unless($supervisor, 404);
        if ($supervisor->employee_type !== 'sales_supervisor') {
            throw ValidationException::withMessages([
                'sales_representative_ids' => 'Sales representatives can only be assigned to a sales supervisor.',
            ]);
        }

        $validated = $request->validate([
            'sales_representative_ids' => ['present', 'array'],
            'sales_representative_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
        ]);
        $representativeIds = array_values(array_unique($validated['sales_representative_ids']));

        DB::transaction(function () use ($id, $representativeIds) {
            $representatives = DB::table('employees')
                ->whereIn('id', $representativeIds)
                ->lockForUpdate()
                ->get(['id', 'employee_type', 'is_active', 'supervisor_id']);

            if ($representatives->count() !== count($representativeIds)
                || $representatives->contains(fn ($representative) => $representative->employee_type !== 'sales' || ! $representative->is_active)) {
                throw ValidationException::withMessages([
                    'sales_representative_ids' => 'Choose active sales representatives only.',
                ]);
            }
            if ($representatives->contains(fn ($representative) => $representative->supervisor_id && (int) $representative->supervisor_id !== $id)) {
                throw ValidationException::withMessages([
                    'sales_representative_ids' => 'One or more sales representatives already belong to another supervisor.',
                ]);
            }

            DB::table('employees')
                ->where('supervisor_id', $id)
                ->when($representativeIds, fn ($query) => $query->whereNotIn('id', $representativeIds))
                ->update(['supervisor_id' => null, 'updated_at' => now()]);

            if ($representativeIds) {
                DB::table('employees')
                    ->whereIn('id', $representativeIds)
                    ->update(['supervisor_id' => $id, 'updated_at' => now()]);
            }
        });

        $teamMembers = DB::table('employees')
            ->leftJoin('routes', 'employees.assigned_route_id', '=', 'routes.id')
            ->where('employees.supervisor_id', $id)
            ->where('employees.employee_type', 'sales')
            ->orderBy('employees.name')
            ->get(['employees.id', 'employees.code', 'employees.name', 'employees.phone', 'employees.email', 'employees.is_active', 'routes.name as assigned_route']);
        $availableSalesRepresentatives = DB::table('employees')
            ->leftJoin('routes', 'employees.assigned_route_id', '=', 'routes.id')
            ->where('employees.employee_type', 'sales')
            ->where('employees.is_active', true)
            ->where(function ($query) use ($id) {
                $query->whereNull('employees.supervisor_id')->orWhere('employees.supervisor_id', $id);
            })
            ->orderBy('employees.name')
            ->get(['employees.id', 'employees.code', 'employees.name', 'employees.phone', 'employees.email', 'routes.name as assigned_route']);

        return ApiResponse::success('Sales team assignment updated.', [
            'team_members' => $teamMembers,
            'available_sales_representatives' => $availableSalesRepresentatives,
        ]);
    }

    private function salesEmployeeActivity(object $employee, ?int $userId): array
    {
        $userId ??= -1;
        $orders = DB::table('orders')->where('created_by', $userId)->whereNull('original_order_id');
        $collections = DB::table('collections')->where('employee_id', $employee->id)->whereIn('status', ['submitted', 'approved']);
        $visits = DB::table('sales_route_visits')->where('employee_id', $employee->id);
        $metrics = [
            ['label' => 'Route customers', 'value' => $employee->assigned_route_id ? DB::table('customers')->where('route_id', $employee->assigned_route_id)->where('is_active', true)->count() : 0, 'format' => 'number'],
            ['label' => 'Orders created', 'value' => (clone $orders)->count(), 'format' => 'number'],
            ['label' => 'Sales value', 'value' => (float) (clone $orders)->where('status', '!=', 'cancelled')->sum('total'), 'format' => 'money'],
            ['label' => 'Collections', 'value' => (float) (clone $collections)->sum('amount'), 'format' => 'money'],
            ['label' => 'Customer visits', 'value' => (clone $visits)->count(), 'format' => 'number'],
        ];
        $activity = (clone $orders)->orderByDesc('order_date')->orderByDesc('id')->limit(30)
            ->get(['id', 'code as reference', 'order_date as date', 'status', 'total as amount'])
            ->map(fn ($item) => [...(array) $item, 'kind' => 'Order', 'amount' => (float) $item->amount]);

        return [$metrics, $activity];
    }

    private function driverEmployeeActivity(object $employee): array
    {
        $trips = DB::table('delivery_trips')->where('driver_id', $employee->id);
        $deliveries = DB::table('deliveries')->where('driver_id', $employee->id);
        $collections = DB::table('collections')->where('employee_id', $employee->id)->whereIn('status', ['submitted', 'approved']);
        $metrics = [
            ['label' => 'Delivery trips', 'value' => (clone $trips)->count(), 'format' => 'number'],
            ['label' => 'Completed trips', 'value' => (clone $trips)->whereIn('status', ['completed', 'delivered'])->count(), 'format' => 'number'],
            ['label' => 'Delivered units', 'value' => (float) (clone $deliveries)->sum('delivered_quantity'), 'format' => 'number'],
            ['label' => 'Cash collected', 'value' => (float) (clone $collections)->where('payment_method', 'cash')->sum('amount'), 'format' => 'money'],
        ];
        $activity = (clone $trips)->orderByDesc('planned_date')->orderByDesc('id')->limit(30)
            ->get(['id', 'code as reference', 'planned_date as date', 'status', 'total_quantity as amount'])
            ->map(fn ($item) => [...(array) $item, 'kind' => 'Trip', 'amount' => (float) $item->amount, 'unit' => 'units']);

        return [$metrics, $activity];
    }

    private function warehouseEmployeeActivity(?int $userId): array
    {
        $userId ??= -1;
        $movements = DB::table('stock_movements')->where('created_by', $userId);
        $metrics = [
            ['label' => 'Stock movements', 'value' => (clone $movements)->count(), 'format' => 'number'],
            ['label' => 'Units handled', 'value' => (float) (clone $movements)->sum(DB::raw('ABS(signed_quantity)')), 'format' => 'number'],
            ['label' => 'Receipts', 'value' => (clone $movements)->where('movement_type', 'receive')->count(), 'format' => 'number'],
            ['label' => 'Adjustments', 'value' => (clone $movements)->where('movement_type', 'adjustment')->count(), 'format' => 'number'],
        ];
        $activity = (clone $movements)->orderByDesc('movement_date')->orderByDesc('id')->limit(30)
            ->get(['id', 'code as reference', 'movement_date as date', 'movement_type as status', 'signed_quantity as amount'])
            ->map(fn ($item) => [...(array) $item, 'kind' => 'Stock movement', 'amount' => (float) $item->amount, 'unit' => 'units']);

        return [$metrics, $activity];
    }

    private function officeEmployeeActivity(?int $userId): array
    {
        $userId ??= -1;
        $orders = DB::table('orders')->where('created_by', $userId)->whereNull('original_order_id');
        $invoices = DB::table('invoices')->where('created_by', $userId);
        $metrics = [
            ['label' => 'Orders created', 'value' => (clone $orders)->count(), 'format' => 'number'],
            ['label' => 'Invoices issued', 'value' => (clone $invoices)->count(), 'format' => 'number'],
            ['label' => 'Collections reviewed', 'value' => DB::table('collections')->where('reviewed_by', $userId)->count(), 'format' => 'number'],
            ['label' => 'Expenses reviewed', 'value' => DB::table('expenses')->where('reviewed_by', $userId)->count(), 'format' => 'number'],
        ];
        $activity = (clone $orders)->orderByDesc('order_date')->orderByDesc('id')->limit(30)
            ->get(['id', 'code as reference', 'order_date as date', 'status', 'total as amount'])
            ->map(fn ($item) => [...(array) $item, 'kind' => 'Order', 'amount' => (float) $item->amount]);

        return [$metrics, $activity];
    }

    private function salesSupervisorActivity(object $employee): array
    {
        $salesUsers = DB::table('users')
            ->join('employees', 'users.employee_id', '=', 'employees.id')
            ->where('employees.employee_type', 'sales')
            ->where('employees.supervisor_id', $employee->id)
            ->where('employees.is_active', true)
            ->select('users.id');
        $orders = DB::table('orders')->whereIn('created_by', $salesUsers)->whereNull('original_order_id');
        $salesEmployeeIds = DB::table('employees')->where('employee_type', 'sales')->where('supervisor_id', $employee->id)->where('is_active', true)->select('id');
        $metrics = [
            ['label' => 'Sales representatives', 'value' => DB::table('employees')->where('employee_type', 'sales')->where('supervisor_id', $employee->id)->where('is_active', true)->count(), 'format' => 'number'],
            ['label' => 'Team sales orders', 'value' => (clone $orders)->count(), 'format' => 'number'],
            ['label' => 'Team order value', 'value' => (float) (clone $orders)->where('status', '!=', 'cancelled')->sum('total'), 'format' => 'money'],
            ['label' => 'Team collections', 'value' => (float) DB::table('collections')->whereIn('employee_id', $salesEmployeeIds)->where('status', 'approved')->sum('amount'), 'format' => 'money'],
        ];
        $activity = (clone $orders)->orderByDesc('order_date')->orderByDesc('id')->limit(30)
            ->get(['id', 'code as reference', 'order_date as date', 'status', 'total as amount'])
            ->map(fn ($item) => [...(array) $item, 'kind' => 'Sales order', 'amount' => (float) $item->amount]);

        return [$metrics, $activity];
    }

    public function productPriceMatrix(Request $request)
    {
        $this->authorizeOffice($request, 'office.master-data.view');

        $products = DB::table('products')
            ->whereNull('deleted_at')
            ->when(trim((string) $request->query('search')), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        $priceTypes = DB::table('price_types')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'currency']);

        $productIds = collect($products->items())->pluck('id');
        $prices = DB::table('product_prices')
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get(['id', 'product_id', 'price_type_id', 'amount', 'effective_from'])
            ->groupBy('product_id')
            ->map(fn ($items) => $items->unique('price_type_id')->values());

        $items = collect($products->items())->map(fn ($product) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'is_active' => (bool) $product->is_active,
            'prices' => $prices->get($product->id, collect()),
        ]);

        return ApiResponse::success('Product price matrix loaded.', [
            'items' => $items,
            'price_types' => $priceTypes,
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function updateProductPriceMatrix(Request $request)
    {
        $this->authorizeOffice($request, 'office.master-data.manage');
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'effective_from' => ['required', 'date'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.price_type_id' => ['required', 'integer', 'exists:price_types,id'],
            'prices.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $now = now();
        DB::transaction(function () use ($request, $validated, $now) {
            foreach ($validated['prices'] as $price) {
                $values = [
                    'amount' => $price['amount'],
                    'is_active' => true,
                    'updated_at' => $now,
                ];
                if (Schema::hasColumn('product_prices', 'updated_by')) {
                    $values['updated_by'] = $request->user()->id;
                }

                $existing = DB::table('product_prices')->where([
                    'product_id' => $validated['product_id'],
                    'price_type_id' => $price['price_type_id'],
                    'effective_from' => $validated['effective_from'],
                ])->exists();

                if (! $existing) {
                    $values['created_at'] = $now;
                    if (Schema::hasColumn('product_prices', 'created_by')) {
                        $values['created_by'] = $request->user()->id;
                    }
                }

                DB::table('product_prices')->updateOrInsert([
                    'product_id' => $validated['product_id'],
                    'price_type_id' => $price['price_type_id'],
                    'effective_from' => $validated['effective_from'],
                ], $values);
            }
        });

        return ApiResponse::success('Product prices updated.');
    }

    public function store(Request $request, string $resource)
    {
        $config = $this->resource($resource);
        $this->authorizeMasterResource($request, $resource, 'manage');
        if ($resource === 'customers' && ! $request->filled('credit_limit')) {
            $request->merge([
                'credit_limit' => (float) (DB::table('companies')->oldest('id')->value('default_customer_credit_limit') ?? 500000),
            ]);
        }
        $this->authorizeEmployeeAccountChange($request, $resource);
        $validated = $request->validate($this->rules($resource));
        $accessRole = $validated['access_role'] ?? null;
        $assignedVehicleId = $resource === 'employees' ? ($validated['assigned_vehicle_id'] ?? null) : null;
        $permissionIds = $validated['permission_ids'] ?? [];
        $password = $validated['password'] ?? null;
        unset($validated['permission_ids'], $validated['password'], $validated['password_confirmation'], $validated['assigned_vehicle_id'], $validated['access_role']);
        if ($resource === 'roles') {
            $validated['allowed_apps'] = json_encode($validated['allowed_apps'] ?? []);
        }
        $generateCode = $this->needsGeneratedCode($resource, $validated);
        if ($generateCode) {
            $validated['code'] = 'AUTO-'.Str::upper(Str::random(12));
        }
        $validated['is_active'] = $validated['is_active'] ?? true;
        if (Schema::hasColumn($config['table'], 'created_by')) {
            $validated['created_by'] = $request->user()->id;
            $validated['updated_by'] = $request->user()->id;
        }
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        $id = DB::transaction(function () use ($config, $generateCode, $password, $permissionIds, $resource, $validated, $assignedVehicleId, $accessRole) {
            $id = DB::table($config['table'])->insertGetId($validated);
            if ($generateCode) {
                $validated['code'] = $this->generatedCode($resource, $id);
                DB::table($config['table'])->where('id', $id)->update(['code' => $validated['code']]);
            }
            $this->syncRolePermissions($resource, $id, $permissionIds);
            $this->syncLoginAccount($resource, $id, $validated, $password, $accessRole);
            if ($resource === 'employees') {
                $this->syncAssignedVehicle($id, $validated['employee_type'] === 'driver' ? $assignedVehicleId : null);
                $this->syncDefaultKpiProfile($id, $validated['employee_type'], $validated['created_by'] ?? null);
            }

            return $id;
        });

        return ApiResponse::success("{$config['singular']} created.", [
            'item' => $this->decorate($resource, (array) DB::table($config['table'])->find($id)),
        ], 201);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $config = $this->resource($resource);
        $this->authorizeMasterResource($request, $resource, 'manage');
        abort_unless(DB::table($config['table'])->where('id', $id)->exists(), 404);
        $previousRoleName = $resource === 'roles' ? DB::table('roles')->where('id', $id)->value('name') : null;
        $previousEmployeeType = $resource === 'employees' ? DB::table('employees')->where('id', $id)->value('employee_type') : null;
        $this->authorizeEmployeeAccountChange($request, $resource, $id);
        $validated = $request->validate($this->rules($resource, $id));
        $accessRole = $validated['access_role'] ?? null;
        $hasVehicleAssignment = $resource === 'employees' && array_key_exists('assigned_vehicle_id', $validated);
        $assignedVehicleId = $hasVehicleAssignment ? $validated['assigned_vehicle_id'] : null;
        $permissionIds = $validated['permission_ids'] ?? null;
        $password = $validated['password'] ?? null;
        unset($validated['permission_ids'], $validated['password'], $validated['password_confirmation'], $validated['assigned_vehicle_id'], $validated['access_role']);
        if ($resource === 'roles') {
            $validated['allowed_apps'] = json_encode($validated['allowed_apps'] ?? []);
        }
        if ($this->needsGeneratedCode($resource, $validated)) {
            $validated['code'] = $this->generatedCode($resource, $id);
        }
        if (Schema::hasColumn($config['table'], 'updated_by')) {
            $validated['updated_by'] = $request->user()->id;
        }
        $validated['updated_at'] = now();

        DB::transaction(function () use ($id, $config, $password, $permissionIds, $previousRoleName, $previousEmployeeType, $resource, $validated, $hasVehicleAssignment, $assignedVehicleId, $accessRole) {
            DB::table($config['table'])->where('id', $id)->update($validated);
            if ($resource === 'employees' && $validated['employee_type'] !== 'sales') {
                DB::table('employees')->where('id', $id)->update(['supervisor_id' => null]);
            }
            if ($resource === 'employees' && $previousEmployeeType === 'sales_supervisor' && $validated['employee_type'] !== 'sales_supervisor') {
                DB::table('employees')->where('supervisor_id', $id)->update(['supervisor_id' => null, 'updated_at' => now()]);
            }
            if ($resource === 'roles' && $previousRoleName !== $validated['name']) {
                User::query()->where('role', $previousRoleName)->update(['role' => $validated['name']]);
            }
            if ($permissionIds !== null) {
                $this->syncRolePermissions($resource, $id, $permissionIds);
            }
            $record = (array) DB::table($config['table'])->find($id);
            $this->syncLoginAccount($resource, $id, $record, $password, $accessRole);
            if ($resource === 'employees' && ($hasVehicleAssignment || $validated['employee_type'] !== 'driver')) {
                $this->syncAssignedVehicle($id, $validated['employee_type'] === 'driver' ? $assignedVehicleId : null);
            }
            if ($resource === 'employees') {
                $this->syncDefaultKpiProfile(
                    $id,
                    $validated['employee_type'],
                    $validated['updated_by'] ?? null,
                    $previousEmployeeType !== $validated['employee_type'],
                );
            }
        });

        return ApiResponse::success("{$config['singular']} updated.", [
            'item' => $this->decorate($resource, (array) DB::table($config['table'])->find($id)),
        ]);
    }

    public function destroy(Request $request, string $resource, int $id)
    {
        $config = $this->resource($resource);
        $this->authorizeMasterResource($request, $resource, 'manage');
        if ($this->hasReferences($resource, $id)) {
            return ApiResponse::error(
                "{$config['singular']} is used by another record and cannot be deleted.",
                ['record' => ['Set the record to inactive instead.']],
                409
            );
        }

        try {
            if (Schema::hasColumn($config['table'], 'deleted_at')) {
                $deleted = DB::table($config['table'])->where('id', $id)->whereNull('deleted_at')->update([
                    'is_active' => false,
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                    'deleted_at' => now(),
                ]);
            } else {
                $deleted = DB::table($config['table'])->where('id', $id)->delete();
            }
        } catch (QueryException) {
            return ApiResponse::error(
                "{$config['singular']} is used by another record and cannot be deleted.",
                ['record' => ['Set the record to inactive instead.']],
                409
            );
        }

        abort_unless($deleted, 404);

        return ApiResponse::success("{$config['singular']} deleted.");
    }

    private function authorizeOffice(Request $request, string $permission): void
    {
        abort_unless($request->user() && in_array($permission, AppAccess::permissionsForRole($request->user()->role), true), 403);
    }

    private function canManageUserAccess(Request $request): bool
    {
        return in_array('office.access.users.manage', AppAccess::permissionsForRole($request->user()?->role), true);
    }

    private function canUseResource(Request $request, string $resource, string $action): bool
    {
        $permissions = AppAccess::permissionsForRole($request->user()?->role);
        if (in_array($resource, ['roles', 'permissions'], true)) {
            return in_array('office.access.roles.manage', $permissions, true);
        }

        if ($resource === 'customers') {
            return in_array("office.customers.{$action}", $permissions, true)
                || in_array("office.master-data.{$action}", $permissions, true);
        }

        $specific = match ($resource) {
            'employees' => "office.employees.{$action}",
            'suppliers' => "office.suppliers.{$action}",
            default => null,
        };

        if ($specific) {
            return in_array($specific, $permissions, true)
                || ($request->user()?->role === 'Owner' && in_array("office.master-data.{$action}", $permissions, true));
        }

        return in_array("office.master-data.{$action}", $permissions, true);
    }

    private function authorizeMasterResource(Request $request, string $resource, string $action): void
    {
        abort_unless($this->canUseResource($request, $resource, $action), 403);
    }

    private function authorizeAnyMasterView(Request $request): void
    {
        abort_unless(collect(array_keys($this->resources()))->contains(fn ($resource) => $this->canUseResource($request, $resource, 'view')), 403);
    }

    private function authorizeEmployeeAccountChange(Request $request, string $resource, ?int $employeeId = null): void
    {
        if ($resource !== 'employees') {
            return;
        }
        $linked = $employeeId ? User::query()->where('employee_id', $employeeId)->first() : null;
        $emailChanged = $linked && $request->exists('email') && $request->input('email') !== $linked->email;
        $typeChanged = $linked && $request->filled('employee_type')
            && $request->input('employee_type') !== DB::table('employees')->where('id', $employeeId)->value('employee_type');
        if ($request->filled('access_role') || $request->filled('password') || $emailChanged || $typeChanged) {
            abort_unless($this->canManageUserAccess($request), 403);
        }
        if ($request->filled('access_role') && ! $linked && ! $request->filled('password')) {
            throw ValidationException::withMessages(['password' => 'Enter a password to create the employee login account.']);
        }
    }

    private function resource(string $resource): array
    {
        $resources = $this->resources();
        abort_unless(isset($resources[$resource]), 404);

        return $resources[$resource];
    }

    private function rules(string $resource, ?int $id = null): array
    {
        $unique = fn (string $table, string $column) => Rule::unique($table, $column)->ignore($id);
        $active = ['sometimes', 'boolean'];
        $linkedUser = in_array($resource, ['customers', 'employees'], true) && $id
            ? User::query()->where($resource === 'customers' ? 'customer_id' : 'employee_id', $id)->first()
            : null;
        $accountEmail = Rule::unique('users', 'email')->ignore($linkedUser?->id);
        $emailPresence = $linkedUser ? 'required' : 'nullable';
        $password = ['nullable', 'string', 'min:8', 'max:72', 'confirmed'];

        return match ($resource) {
            'areas' => $this->codedRules('areas', $id, ['description' => ['nullable', 'string', 'max:500']]),
            'routes' => $this->codedRules('routes', $id, [
                'area_id' => ['required', 'integer', 'exists:areas,id'],
                'service_day' => ['nullable', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
                'description' => ['nullable', 'string', 'max:500'],
            ]),
            'warehouses' => $this->codedRules('warehouses', $id, [
                'area_id' => ['nullable', 'integer', 'exists:areas,id'],
                'phone' => ['nullable', 'string', 'max:40'],
                'address' => ['nullable', 'string', 'max:500'],
            ]),
            'brands' => $this->codedRules('brands', $id, ['description' => ['nullable', 'string', 'max:500']]),
            'products' => [
                'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
                'sku' => ['required', 'string', 'max:50', $unique('products', 'sku')],
                'name' => ['required', 'string', 'max:150'],
                'unit' => ['required', 'string', 'max:30'],
                'size' => ['nullable', 'string', 'max:60'],
                'description' => ['nullable', 'string', 'max:500'],
                'is_active' => $active,
            ],
            'price-types' => [
                'code' => ['nullable', 'string', 'max:30', $unique('price_types', 'code')],
                'name' => ['required', 'string', 'max:100'],
                'currency' => ['required', 'string', 'size:3'],
                'is_default' => ['sometimes', 'boolean'],
                'is_active' => $active,
            ],
            'product-prices' => [
                'product_id' => ['required', 'integer', 'exists:products,id'],
                'price_type_id' => ['required', 'integer', 'exists:price_types,id'],
                'amount' => ['required', 'numeric', 'min:0'],
                'effective_from' => ['nullable', 'date'],
                'is_active' => $active,
            ],
            'customers' => [
                'area_id' => ['nullable', 'integer', 'exists:areas,id'],
                'route_id' => ['nullable', 'integer', 'exists:routes,id'],
                'price_type_id' => ['nullable', 'integer', 'exists:price_types,id'],
                'code' => ['nullable', 'string', 'max:30', $unique('customers', 'code')],
                'shop_name' => ['required', 'string', 'max:150'],
                'contact_name' => ['required', 'string', 'max:150'],
                'phone' => ['required', 'string', 'max:40'],
                'email' => [$emailPresence, 'required_with:password', 'email', 'max:150', $accountEmail],
                'password' => $password,
                'password_confirmation' => ['nullable', 'string', 'max:72'],
                'address' => ['nullable', 'string', 'max:500'],
                'credit_limit' => ['required', 'numeric', 'min:0'],
                'is_active' => $active,
            ],
            'suppliers' => [
                'code' => ['nullable', 'string', 'max:30', $unique('suppliers', 'code')],
                'name' => ['required', 'string', 'max:150'],
                'contact_name' => ['nullable', 'string', 'max:150'],
                'phone' => ['nullable', 'string', 'max:40'],
                'email' => ['nullable', 'email', 'max:150', $unique('suppliers', 'email')],
                'address' => ['nullable', 'string', 'max:500'],
                'is_active' => $active,
            ],
            'employees' => [
                'assigned_route_id' => ['nullable', 'integer', 'exists:routes,id'],
                'code' => ['nullable', 'string', 'max:30', $unique('employees', 'code')],
                'name' => ['required', 'string', 'max:150'],
                'employee_type' => ['required', Rule::in(['office', 'sales', 'sales_supervisor', 'driver', 'warehouse'])],
                'access_role' => ['nullable', Rule::in(['Office Staff', 'HR', 'Accountant', 'Finance Manager'])],
                'phone' => ['nullable', 'string', 'max:40'],
                'email' => [$emailPresence, 'required_with:password', 'email', 'max:150', $unique('employees', 'email'), $accountEmail],
                'password' => $password,
                'password_confirmation' => ['nullable', 'string', 'max:72'],
                'hire_date' => ['nullable', 'date'],
                'address' => ['nullable', 'string', 'max:500'],
                'is_active' => $active,
            ],
            'vehicles' => [
                'code' => ['nullable', 'string', 'max:30', $unique('vehicles', 'code')],
                'plate_no' => ['required', 'string', 'max:40', $unique('vehicles', 'plate_no')],
                'vehicle_type' => ['required', Rule::in(['truck', 'van', 'motorbike', 'other'])],
                'make' => ['nullable', 'string', 'max:80'],
                'model' => ['nullable', 'string', 'max:80'],
                'capacity' => ['nullable', 'numeric', 'min:0'],
                'is_active' => $active,
            ],
            'roles' => [
                'name' => ['required', 'string', 'max:100', $unique('roles', 'name')],
                'description' => ['nullable', 'string', 'max:500'],
                'guard_name' => ['required', Rule::in(['web'])],
                'allowed_apps' => ['required', 'array', 'min:1'],
                'allowed_apps.*' => ['string', Rule::in(AppAccess::APPS)],
                'permission_ids' => ['sometimes', 'array'],
                'permission_ids.*' => ['integer', 'exists:permissions,id'],
                'is_active' => $active,
            ],
            'permissions' => [
                'name' => ['required', 'string', 'max:150', $unique('permissions', 'name')],
                'group' => ['required', 'string', 'max:100'],
                'guard_name' => ['required', Rule::in(['web'])],
                'is_active' => $active,
            ],
            default => abort(404),
        };
    }

    private function codedRules(string $table, ?int $id, array $extra): array
    {
        return array_merge([
            'code' => ['nullable', 'string', 'max:30', Rule::unique($table, 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ], $extra);
    }

    private function syncRolePermissions(string $resource, int $roleId, array $permissionIds): void
    {
        if ($resource !== 'roles') {
            return;
        }

        DB::table('permission_role')->where('role_id', $roleId)->delete();
        foreach (array_unique($permissionIds) as $permissionId) {
            DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    private function syncAssignedVehicle(int $employeeId, ?int $vehicleId): void
    {
        if ($vehicleId) {
            $vehicle = DB::table('vehicles')->where('id', $vehicleId)->lockForUpdate()->first();
            if (! $vehicle || ! $vehicle->is_active) {
                throw ValidationException::withMessages(['assigned_vehicle_id' => 'Choose an active vehicle.']);
            }
            if ($vehicle->assigned_driver_id && (int) $vehicle->assigned_driver_id !== $employeeId) {
                throw ValidationException::withMessages(['assigned_vehicle_id' => 'This vehicle is already assigned to another driver.']);
            }
        }

        $currentAssignments = DB::table('vehicles')->where('assigned_driver_id', $employeeId);
        if ($vehicleId) {
            $currentAssignments->where('id', '!=', $vehicleId);
        }
        $currentAssignments->update(['assigned_driver_id' => null, 'updated_at' => now()]);

        if ($vehicleId) {
            DB::table('vehicles')->where('id', $vehicleId)->update(['assigned_driver_id' => $employeeId, 'updated_at' => now()]);
        }
    }

    private function syncDefaultKpiProfile(int $employeeId, string $employeeType, ?int $actorId, bool $replaceExisting = false): void
    {
        $templateCode = self::DEFAULT_KPI_TEMPLATE_BY_EMPLOYEE_TYPE[$employeeType] ?? null;
        $template = $templateCode
            ? DB::table('kpi_templates')->where('code', $templateCode)->where('is_active', true)->first()
            : null;
        if (! $template) {
            return;
        }

        $now = now();
        $profile = DB::table('kpi_staff_profiles')->where('employee_id', $employeeId)->first();
        if ($profile && ! $replaceExisting) {
            return;
        }

        $values = [
            'kpi_template_id' => $template->id,
            'target_bonus' => $template->target_bonus,
            'updated_by' => $actorId,
            'updated_at' => $now,
        ];
        if ($profile) {
            DB::table('kpi_staff_profiles')->where('id', $profile->id)->update($values);
            $profileId = (int) $profile->id;
        } else {
            $profileId = (int) DB::table('kpi_staff_profiles')->insertGetId([
                'employee_id' => $employeeId,
                ...$values,
                'created_by' => $actorId,
                'created_at' => $now,
            ]);
        }

        $metrics = DB::table('kpi_template_metrics')->where('kpi_template_id', $template->id)->orderBy('sort_order')->get();
        DB::table('kpi_staff_target_items')->where('kpi_staff_profile_id', $profileId)->delete();
        foreach ($metrics as $metric) {
            if ($metric->calculation_type === 'manual' || $metric->default_target === null) {
                continue;
            }
            DB::table('kpi_staff_target_items')->insert([
                'kpi_staff_profile_id' => $profileId,
                'kpi_template_metric_id' => $metric->id,
                'target_value' => $metric->default_target,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! $replaceExisting) {
            return;
        }

        $drafts = DB::table('kpi_results')->where('employee_id', $employeeId)->where('status', 'draft')->get();
        foreach ($drafts as $draft) {
            DB::table('kpi_results')->where('id', $draft->id)->update([
                'kpi_template_id' => $template->id,
                'target_bonus' => $template->target_bonus,
                'overall_score' => 0,
                'bonus_amount' => 0,
                'updated_at' => $now,
            ]);
            DB::table('kpi_result_items')->where('kpi_result_id', $draft->id)->delete();
            foreach ($metrics as $metric) {
                DB::table('kpi_result_items')->insert([
                    'kpi_result_id' => $draft->id,
                    'kpi_template_metric_id' => $metric->id,
                    'target_value' => $metric->default_target,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function syncLoginAccount(string $resource, int $recordId, array $record, ?string $password, ?string $accessRole = null): void
    {
        if (! in_array($resource, ['customers', 'employees'], true)) {
            return;
        }

        $linkColumn = $resource === 'customers' ? 'customer_id' : 'employee_id';
        $user = User::query()->where($linkColumn, $recordId)->first();
        if (! $user && ! $password) {
            return;
        }

        $attributes = [
            'name' => $resource === 'customers' ? $record['contact_name'] : $record['name'],
            'email' => $record['email'],
            'phone' => $record['phone'] ?? null,
            'role' => $resource === 'customers' ? 'Customer' : (
                in_array($record['employee_type'], ['sales', 'sales_supervisor', 'driver'], true)
                    ? $this->employeeRole($record['employee_type'])
                    : ($accessRole ?: (in_array($user?->role, ['Sales Representative', 'Sales Supervisor', 'Driver'], true)
                        ? 'Office Staff'
                        : ($user?->role ?: 'Office Staff')))
            ),
            'customer_id' => $resource === 'customers' ? $recordId : null,
            'employee_id' => $resource === 'employees' ? $recordId : null,
        ];

        if ($password) {
            $attributes['password'] = Hash::make($password);
        }

        if ($user) {
            $user->update($attributes);
        } else {
            User::create($attributes + ['locale' => 'en']);
        }
    }

    private function employeeRole(string $employeeType): string
    {
        return match ($employeeType) {
            'sales' => 'Sales Representative',
            'sales_supervisor' => 'Sales Supervisor',
            'driver' => 'Driver',
            default => 'Office Staff',
        };
    }

    private function needsGeneratedCode(string $resource, array $values): bool
    {
        return array_key_exists($resource, $this->codePrefixes()) && blank($values['code'] ?? null);
    }

    private function generatedCode(string $resource, int $recordId): string
    {
        $prefix = $this->codePrefixes()[$resource];
        $table = $this->resource($resource)['table'];
        $sequence = $recordId;

        do {
            $code = $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (DB::table($table)->where('code', $code)->where('id', '!=', $recordId)->exists());

        return $code;
    }

    private function codePrefixes(): array
    {
        return [
            'areas' => 'AREA',
            'routes' => 'RTE',
            'warehouses' => 'WH',
            'brands' => 'BRD',
            'price-types' => 'PRT',
            'customers' => 'CUS',
            'suppliers' => 'SUP',
            'employees' => 'EMP',
            'vehicles' => 'VEH',
        ];
    }

    private function decorate(string $resource, array $item): array
    {
        $relations = $this->resource($resource)['relations'] ?? [];
        foreach ($relations as $field => [$table, $label, $alias]) {
            $item[$alias] = $item[$field] ? DB::table($table)->where('id', $item[$field])->value($label) : null;
        }

        if ($resource === 'roles') {
            $item['permission_ids'] = DB::table('permission_role')->where('role_id', $item['id'])->pluck('permission_id')->all();
            $item['allowed_apps'] = json_decode($item['allowed_apps'] ?? '[]', true) ?: [];
        }

        if ($resource === 'employees') {
            $vehicle = DB::table('vehicles')->where('assigned_driver_id', $item['id'])->orderBy('id')->first(['id', 'code', 'plate_no']);
            $item['assigned_vehicle_id'] = $vehicle?->id;
            $item['assigned_vehicle'] = $vehicle ? $vehicle->code.' · '.$vehicle->plate_no : null;
            $item['access_role'] = User::query()->where('employee_id', $item['id'])->value('role');
        }

        return $item;
    }

    private function optionLists(Request $request): array
    {
        $lists = [
            'areas' => ['areas', 'name'],
            'routes' => ['routes', 'name'],
            'brands' => ['brands', 'name'],
            'products' => ['products', 'name'],
            'price-types' => ['price_types', 'name'],
            'suppliers' => ['suppliers', 'name'],
            'employees' => ['employees', 'name'],
            'vehicles' => ['vehicles', 'code'],
            'permissions' => ['permissions', 'name'],
        ];

        $options = collect($lists)->map(function ($definition) {
            $query = DB::table($definition[0])->where('is_active', true);
            if (Schema::hasColumn($definition[0], 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            return $query->orderBy($definition[1])->get(['id', DB::raw("{$definition[1]} as label")]);
        })->all();

        $options['routes'] = DB::table('routes')->where('is_active', true)->orderBy('name')
            ->get(['id', 'name as label', 'area_id']);

        $options['vehicles'] = DB::table('vehicles')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'plate_no'])
            ->map(fn ($vehicle) => ['id' => $vehicle->id, 'label' => $vehicle->code.' · '.$vehicle->plate_no]);

        $options['apps'] = collect(AppAccess::APPS)->map(fn ($app) => [
            'id' => $app,
            'label' => ucfirst($app).' App',
        ]);

        if (! $this->canUseResource($request, 'roles', 'view')) {
            unset($options['permissions']);
        }

        $permissions = AppAccess::permissionsForRole($request->user()?->role);
        if (! in_array('office.master-data.view', $permissions, true)) {
            $allowed = in_array('office.employees.view', $permissions, true)
                ? ['routes', 'vehicles', 'employees']
                : ['suppliers'];
            $options = array_intersect_key($options, array_flip($allowed));
        }

        return $options;
    }

    private function hasReferences(string $resource, int $id): bool
    {
        $references = [
            'areas' => [['routes', 'area_id'], ['warehouses', 'area_id'], ['customers', 'area_id']],
            'routes' => [['customers', 'route_id'], ['employees', 'assigned_route_id'], ['orders', 'route_id'], ['deliveries', 'route_id']],
            'warehouses' => [['stock_balances', 'warehouse_id'], ['stock_movements', 'warehouse_id'], ['deliveries', 'warehouse_id']],
            'brands' => [['products', 'brand_id']],
            'products' => [['product_prices', 'product_id'], ['order_items', 'product_id'], ['invoice_items', 'product_id'], ['stock_balances', 'product_id']],
            'customers' => [['users', 'customer_id'], ['orders', 'customer_id'], ['invoices', 'customer_id'], ['collections', 'customer_id']],
            'employees' => [['users', 'employee_id'], ['attendance_records', 'employee_id'], ['deliveries', 'driver_id'], ['vehicle_costs', 'employee_id']],
            'vehicles' => [['deliveries', 'vehicle_id'], ['vehicle_costs', 'vehicle_id']],
            'suppliers' => [['supplier_ledger_entries', 'supplier_id'], ['supplier_invoices', 'supplier_id'], ['supplier_payments', 'supplier_id'], ['stock_movements', 'supplier_id']],
        ];

        return collect($references[$resource] ?? [])->contains(fn ($reference) => DB::table($reference[0])->where($reference[1], $id)->exists());
    }

    private function resources(): array
    {
        $active = ['name' => 'is_active', 'label' => 'Status', 'type' => 'boolean'];
        $code = ['name' => 'code', 'label' => 'Code', 'type' => 'text'];
        $name = ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true];

        return [
            'areas' => $this->config('areas', 'Areas', 'Area', 'Service territories used by routes and customers.',
                [$code, $name, ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], $active],
                ['code', 'name', 'description', 'is_active'], ['code', 'name', 'description']),
            'routes' => $this->config('routes', 'Routes', 'Route', 'Delivery and sales service routes.',
                [['name' => 'area_id', 'label' => 'Area', 'type' => 'select', 'source' => 'areas', 'required' => true], $code, $name, ['name' => 'service_day', 'label' => 'Service day', 'type' => 'select', 'options' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']], ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], $active],
                ['code', 'name', 'area', 'service_day', 'is_active'], ['code', 'name', 'service_day', 'description'], ['area_id'], ['area_id' => ['areas', 'name', 'area']]),
            'warehouses' => $this->config('warehouses', 'Warehouses', 'Warehouse', 'Stock holding locations.',
                [['name' => 'area_id', 'label' => 'Area', 'type' => 'select', 'source' => 'areas'], $code, $name, ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'], ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'], $active],
                ['code', 'name', 'area', 'phone', 'is_active'], ['code', 'name', 'phone', 'address'], ['area_id'], ['area_id' => ['areas', 'name', 'area']]),
            'brands' => $this->config('brands', 'Brands', 'Brand', 'Product brand definitions.',
                [$code, $name, ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], $active],
                ['code', 'name', 'description', 'is_active'], ['code', 'name', 'description']),
            'products' => $this->config('products', 'Products', 'Product', 'Sellable water and related items.',
                [['name' => 'brand_id', 'label' => 'Brand', 'type' => 'select', 'source' => 'brands'], ['name' => 'sku', 'label' => 'SKU', 'type' => 'text', 'required' => true], $name, ['name' => 'unit', 'label' => 'Unit', 'type' => 'text', 'required' => true], ['name' => 'size', 'label' => 'Size', 'type' => 'text'], ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], $active],
                ['sku', 'name', 'brand', 'unit', 'is_active'], ['sku', 'name', 'unit', 'size', 'description'], ['brand_id'], ['brand_id' => ['brands', 'name', 'brand']]),
            'price-types' => $this->config('price_types', 'Price Types', 'Price Type', 'Retail, wholesale, and special pricing tiers.',
                [$code, $name, ['name' => 'currency', 'label' => 'Currency', 'type' => 'text', 'required' => true], ['name' => 'is_default', 'label' => 'Default price', 'type' => 'boolean'], $active],
                ['code', 'name', 'currency', 'is_default', 'is_active'], ['code', 'name']),
            'product-prices' => $this->config('product_prices', 'Product Prices', 'Product Price', 'Prices by product and customer price type.',
                [['name' => 'product_id', 'label' => 'Product', 'type' => 'select', 'source' => 'products', 'required' => true], ['name' => 'price_type_id', 'label' => 'Price type', 'type' => 'select', 'source' => 'price-types', 'required' => true], ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true], ['name' => 'effective_from', 'label' => 'Effective from', 'type' => 'date'], $active],
                ['product', 'price_type', 'amount', 'effective_from', 'is_active'], ['effective_from'], ['product_id', 'price_type_id'], ['product_id' => ['products', 'name', 'product'], 'price_type_id' => ['price_types', 'name', 'price_type']]),
            'customers' => $this->config('customers', 'Customers', 'Customer', 'Reseller shops, contacts, route, and credit settings.',
                [['name' => 'area_id', 'label' => 'Area', 'type' => 'select', 'source' => 'areas'], ['name' => 'route_id', 'label' => 'Route', 'type' => 'select', 'source' => 'routes'], ['name' => 'price_type_id', 'label' => 'Price type', 'type' => 'select', 'source' => 'price-types'], $code, ['name' => 'shop_name', 'label' => 'Shop name', 'type' => 'text', 'required' => true], ['name' => 'contact_name', 'label' => 'Contact name', 'type' => 'text', 'required' => true], ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => true], ['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password'], ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'autocomplete' => 'new-password'], ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'], ['name' => 'credit_limit', 'label' => 'Credit limit', 'type' => 'number', 'required' => true, 'default' => (float) (DB::table('companies')->oldest('id')->value('default_customer_credit_limit') ?? 500000)], $active],
                ['code', 'shop_name', 'contact_name', 'route', 'phone', 'is_active'], ['code', 'shop_name', 'contact_name', 'phone', 'email', 'address'], ['area_id', 'route_id', 'price_type_id'], ['area_id' => ['areas', 'name', 'area'], 'route_id' => ['routes', 'name', 'route'], 'price_type_id' => ['price_types', 'name', 'price_type']]),
            'suppliers' => $this->config('suppliers', 'Suppliers', 'Supplier', 'Supplier companies, contacts, and account status used by purchasing and supplier ledgers.',
                [$code, $name, ['name' => 'contact_name', 'label' => 'Contact name', 'type' => 'text'], ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'], ['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'], $active],
                ['code', 'name', 'contact_name', 'phone', 'email', 'is_active'], ['code', 'name', 'contact_name', 'phone', 'email', 'address']),
            'employees' => $this->config('employees', 'Employees', 'Employee', 'Office, warehouse, sales, sales supervisor, and driver records.',
                [['name' => 'assigned_route_id', 'label' => 'Assigned route', 'type' => 'select', 'source' => 'routes'], $code, $name, ['name' => 'employee_type', 'label' => 'Employee type', 'type' => 'select', 'required' => true, 'options' => ['office', 'sales', 'sales_supervisor', 'driver', 'warehouse']], ['name' => 'access_role', 'label' => 'Office access role', 'type' => 'select', 'options' => ['Office Staff', 'HR', 'Accountant', 'Finance Manager'], 'depends_on' => 'employee_type', 'show_when' => 'office'], ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'], ['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password'], ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'autocomplete' => 'new-password'], ['name' => 'hire_date', 'label' => 'Hire date', 'type' => 'date'], ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'], $active],
                ['code', 'name', 'employee_type', 'assigned_route', 'assigned_vehicle', 'phone', 'is_active'], ['code', 'name', 'employee_type', 'phone', 'email'], ['assigned_route_id', 'employee_type'], ['assigned_route_id' => ['routes', 'name', 'assigned_route']]),
            'vehicles' => $this->config('vehicles', 'Vehicles', 'Vehicle', 'Delivery vehicles and assigned drivers.',
                [$code, ['name' => 'plate_no', 'label' => 'Plate no.', 'type' => 'text', 'required' => true], ['name' => 'vehicle_type', 'label' => 'Vehicle type', 'type' => 'select', 'required' => true, 'options' => ['truck', 'van', 'motorbike', 'other']], ['name' => 'make', 'label' => 'Make', 'type' => 'text'], ['name' => 'model', 'label' => 'Model', 'type' => 'text'], ['name' => 'capacity', 'label' => 'Capacity', 'type' => 'number'], $active],
                ['code', 'plate_no', 'vehicle_type', 'assigned_driver', 'capacity', 'is_active'], ['code', 'plate_no', 'make', 'model'], ['assigned_driver_id', 'vehicle_type'], ['assigned_driver_id' => ['employees', 'name', 'assigned_driver']]),
            'roles' => $this->config('roles', 'Roles & Permissions', 'Role', 'App roles and permission assignments.',
                [$name, ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'], ['name' => 'guard_name', 'label' => 'Guard', 'type' => 'hidden', 'default' => 'web'], ['name' => 'allowed_apps', 'label' => 'Allowed apps', 'type' => 'multiselect', 'source' => 'apps'], ['name' => 'permission_ids', 'label' => 'Permissions', 'type' => 'multiselect', 'source' => 'permissions'], $active],
                ['name', 'allowed_apps', 'description', 'is_active'], ['name', 'description']),
            'permissions' => $this->config('permissions', 'Permissions', 'Permission', 'Permission catalog used by roles.',
                [['name' => 'name', 'label' => 'Permission', 'type' => 'text', 'required' => true], ['name' => 'group', 'label' => 'Group', 'type' => 'text', 'required' => true], ['name' => 'guard_name', 'label' => 'Guard', 'type' => 'hidden', 'default' => 'web'], $active],
                ['name', 'group', 'is_active'], ['name', 'group']),
        ];
    }

    private function config(string $table, string $label, string $singular, string $description, array $fields, array $list, array $search, array $filters = [], array $relations = []): array
    {
        return compact('table', 'label', 'singular', 'description', 'fields', 'list', 'search', 'filters', 'relations') + ['sort' => ['id', 'desc']];
    }
}
