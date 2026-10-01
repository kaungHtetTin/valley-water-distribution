<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupervisorMobileController extends Controller
{
    public function overview(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->role === 'Sales Supervisor', 403, 'This workspace is only available to sales supervisors.');
        abort_unless(in_array('supervisor.home.view', AppAccess::permissionsForRole($user->role), true), 403);
        abort_unless($user->employee_id, 404, 'Employee profile is not linked to this account.');

        $supervisor = DB::table('employees')
            ->where('id', $user->employee_id)
            ->where('employee_type', 'sales_supervisor')
            ->first(['id', 'code', 'name', 'phone', 'email']);
        abort_unless($supervisor, 404, 'Sales supervisor profile was not found.');

        $members = $this->teamMembers((int) $supervisor->id);
        $memberUserIds = DB::table('users')
            ->whereIn('employee_id', $members->pluck('id'))
            ->pluck('id');
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();
        $orders = DB::table('orders')
            ->whereIn('created_by', $memberUserIds)
            ->whereBetween('order_date', [$monthStart, $monthEnd]);

        return ApiResponse::success('Supervisor workspace loaded.', [
            'supervisor' => $supervisor,
            'team' => $members,
            'period' => Carbon::now()->format('Y-m'),
            'summary' => [
                'team_members' => $members->count(),
                'active_members' => $members->where('is_active', true)->count(),
                'orders' => (clone $orders)->count(),
                'order_value' => (float) (clone $orders)->sum('total'),
                'customers_reached' => (clone $orders)->whereNotNull('customer_id')->distinct()->count('customer_id'),
            ],
        ]);
    }

    public function team(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->role === 'Sales Supervisor', 403, 'This workspace is only available to sales supervisors.');
        abort_unless(in_array('supervisor.team.view', AppAccess::permissionsForRole($user->role), true), 403);
        $supervisorId = $this->supervisorEmployeeId($user->employee_id);

        return ApiResponse::success('Supervisor team loaded.', [
            'team' => $this->teamMembers($supervisorId),
        ]);
    }

    public function representative(Request $request, int $employee)
    {
        $user = $request->user();
        abort_unless($user?->role === 'Sales Supervisor', 403, 'This workspace is only available to sales supervisors.');
        abort_unless(in_array('supervisor.team.view', AppAccess::permissionsForRole($user->role), true), 403);
        abort_unless(in_array('supervisor.kpi.view', AppAccess::permissionsForRole($user->role), true), 403);
        $supervisorId = $this->supervisorEmployeeId($user->employee_id);

        $representative = DB::table('employees')
            ->leftJoin('routes', 'employees.assigned_route_id', '=', 'routes.id')
            ->where('employees.id', $employee)
            ->where('employees.employee_type', 'sales')
            ->where('employees.supervisor_id', $supervisorId)
            ->first([
                'employees.id', 'employees.code', 'employees.name', 'employees.phone', 'employees.email',
                'employees.address', 'employees.hire_date', 'employees.is_active',
                'routes.code as route_code', 'routes.name as route_name',
            ]);
        abort_unless($representative, 404, 'Sales representative was not found in your team.');

        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['month', 'year'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ]);
        $availableMonths = DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->where('kpi_results.employee_id', $employee)
            ->orderByDesc('kpi_periods.month')
            ->pluck('kpi_periods.month')
            ->unique()
            ->values();
        $latestMonth = $availableMonths->first() ?? now()->format('Y-m');
        $period = $validated['period'] ?? 'month';
        $month = $validated['month'] ?? $latestMonth;
        $year = (int) ($validated['year'] ?? substr($month, 0, 4));

        $baseQuery = DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->where('kpi_results.employee_id', $employee)
            ->select(
                'kpi_results.*', 'kpi_periods.month', 'kpi_templates.name as template_name',
                'kpi_templates.code as template_code'
            );
        $periodQuery = $period === 'month'
            ? (clone $baseQuery)->where('kpi_periods.month', $month)
            : (clone $baseQuery)->where('kpi_periods.month', 'like', "{$year}-%");
        $reviews = $periodQuery->orderByDesc('kpi_periods.month')->get();
        $resultIds = $reviews->pluck('id');

        $metrics = $resultIds->isEmpty() ? collect() : DB::table('kpi_result_items')
            ->join('kpi_template_metrics', 'kpi_result_items.kpi_template_metric_id', '=', 'kpi_template_metrics.id')
            ->whereIn('kpi_result_items.kpi_result_id', $resultIds)
            ->groupBy(
                'kpi_template_metrics.id', 'kpi_template_metrics.code', 'kpi_template_metrics.name',
                'kpi_template_metrics.calculation_type', 'kpi_template_metrics.unit',
                'kpi_template_metrics.weight', 'kpi_template_metrics.sort_order'
            )
            ->orderBy('kpi_template_metrics.sort_order')
            ->selectRaw('kpi_template_metrics.id, kpi_template_metrics.code, kpi_template_metrics.name, kpi_template_metrics.calculation_type, kpi_template_metrics.unit, kpi_template_metrics.weight, AVG(kpi_result_items.target_value) as target_value, AVG(CASE WHEN kpi_template_metrics.calculation_type = ? THEN kpi_result_items.manual_score ELSE kpi_result_items.actual_value END) as actual_value, AVG(kpi_result_items.achievement_percent) as achievement_percent, AVG(kpi_result_items.weighted_score) as weighted_score', ['manual'])
            ->get()
            ->map(fn ($metric) => [
                'id' => (int) $metric->id,
                'code' => $metric->code,
                'name' => $metric->name,
                'calculation_type' => $metric->calculation_type,
                'unit' => $metric->unit,
                'weight' => (float) $metric->weight,
                'target_value' => $metric->target_value === null ? null : round((float) $metric->target_value, 2),
                'actual_value' => $metric->actual_value === null ? null : round((float) $metric->actual_value, 2),
                'achievement_percent' => $metric->achievement_percent === null ? null : round((float) $metric->achievement_percent, 2),
                'weighted_score' => $metric->weighted_score === null ? null : round((float) $metric->weighted_score, 2),
            ]);

        return ApiResponse::success('Sales representative KPI loaded.', [
            'representative' => $representative,
            'filters' => ['period' => $period, 'month' => $month, 'year' => $year],
            'available_months' => $availableMonths,
            'summary' => [
                'reviews' => $reviews->count(),
                'average_score' => round((float) $reviews->avg('overall_score'), 2),
                'bonus_total' => (float) $reviews->sum('bonus_amount'),
                'approved' => $reviews->where('status', 'approved')->count(),
            ],
            'reviews' => $reviews->map(fn ($review) => [
                'id' => (int) $review->id,
                'month' => $review->month,
                'template_name' => $review->template_name,
                'status' => $review->status,
                'overall_score' => (float) $review->overall_score,
                'bonus_amount' => (float) $review->bonus_amount,
                'approved_at' => $review->approved_at,
            ])->values(),
            'metrics' => $metrics->values(),
        ]);
    }

    private function teamMembers(int $supervisorId)
    {
        return DB::table('employees')
            ->leftJoin('routes', 'employees.assigned_route_id', '=', 'routes.id')
            ->where('employees.supervisor_id', $supervisorId)
            ->where('employees.employee_type', 'sales')
            ->orderByDesc('employees.is_active')
            ->orderBy('employees.name')
            ->get([
                'employees.id',
                'employees.code',
                'employees.name',
                'employees.phone',
                'employees.email',
                'employees.is_active',
                'routes.code as route_code',
                'routes.name as route_name',
            ]);
    }

    private function supervisorEmployeeId(?int $employeeId): int
    {
        abort_unless($employeeId, 404, 'Employee profile is not linked to this account.');
        abort_unless(
            DB::table('employees')->where('id', $employeeId)->where('employee_type', 'sales_supervisor')->exists(),
            404,
            'Sales supervisor profile was not found.'
        );

        return $employeeId;
    }
}
