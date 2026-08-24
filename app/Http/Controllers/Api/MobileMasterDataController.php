<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return ApiResponse::success('Customer loaded.', ['customer' => $customer]);
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
