<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollAdjustment;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission($request, 'office.payroll.view');

        $query = PayrollAdjustment::query()
            ->leftJoin('employees', 'payroll_adjustments.employee_id', '=', 'employees.id')
            ->leftJoin('kpi_results', 'payroll_adjustments.id', '=', 'kpi_results.payroll_adjustment_id')
            ->leftJoin('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->select(
                'payroll_adjustments.*',
                'employees.code as employee_code',
                'employees.name as employee_name',
                'employees.employee_type',
                'kpi_results.id as kpi_result_id',
                'kpi_results.overall_score as kpi_score',
                'kpi_periods.month as kpi_month'
            )
            ->latest('payroll_adjustments.effective_date')
            ->latest('payroll_adjustments.id');

        if ($request->filled('adjustment_type')) {
            $query->where('payroll_adjustments.adjustment_type', $request->query('adjustment_type'));
        }

        if ($request->filled('employee_id')) {
            $query->where('payroll_adjustments.employee_id', $request->query('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('payroll_adjustments.status', $request->query('status'));
        }

        if ($request->filled('month')) {
            $month = Carbon::createFromFormat('Y-m', $request->query('month'));
            $query->whereBetween('payroll_adjustments.effective_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('payroll_adjustments.title', 'like', "%{$search}%")
                    ->orWhere('employees.name', 'like', "%{$search}%")
                    ->orWhere('employees.code', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Payroll adjustments loaded.', [
            'items' => collect($paginator->items())->map(fn ($adjustment) => $this->payload($adjustment)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission($request, ['office.payroll.adjustments.manage', 'office.payroll.manage']);
        $validated = $request->validate($this->rules());
        $validated['created_by'] = $request->user()?->id;
        $adjustment = PayrollAdjustment::create($validated);

        return ApiResponse::success('Payroll adjustment created.', [
            'adjustment' => $this->payload($this->withEmployee($adjustment->id)),
        ], 201);
    }

    public function update(Request $request, PayrollAdjustment $payrollAdjustment)
    {
        $this->authorizePermission($request, ['office.payroll.adjustments.manage', 'office.payroll.manage']);
        abort_if($this->isKpiBonus($payrollAdjustment->id), 409, 'KPI bonus adjustments are managed from KPI Reviews.');
        $payrollAdjustment->update($request->validate($this->rules()));

        return ApiResponse::success('Payroll adjustment updated.', [
            'adjustment' => $this->payload($this->withEmployee($payrollAdjustment->id)),
        ]);
    }

    public function destroy(Request $request, PayrollAdjustment $payrollAdjustment)
    {
        $this->authorizePermission($request, ['office.payroll.adjustments.manage', 'office.payroll.manage']);
        abort_if($this->isKpiBonus($payrollAdjustment->id), 409, 'Posted KPI bonuses cannot be deleted.');
        $payrollAdjustment->delete();

        return ApiResponse::success('Payroll adjustment deleted.');
    }

    private function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'adjustment_type' => ['required', Rule::in(['advance', 'allowance', 'incentive', 'ot'])],
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['active', 'void'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function withEmployee(int $id)
    {
        return PayrollAdjustment::query()
            ->leftJoin('employees', 'payroll_adjustments.employee_id', '=', 'employees.id')
            ->leftJoin('kpi_results', 'payroll_adjustments.id', '=', 'kpi_results.payroll_adjustment_id')
            ->leftJoin('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->where('payroll_adjustments.id', $id)
            ->select(
                'payroll_adjustments.*',
                'employees.code as employee_code',
                'employees.name as employee_name',
                'employees.employee_type',
                'kpi_results.id as kpi_result_id',
                'kpi_results.overall_score as kpi_score',
                'kpi_periods.month as kpi_month'
            )
            ->firstOrFail();
    }

    private function payload($adjustment): array
    {
        return [
            'id' => $adjustment->id,
            'employee_id' => $adjustment->employee_id,
            'employee_code' => $adjustment->employee_code,
            'employee_name' => $adjustment->employee_name,
            'employee_type' => $adjustment->employee_type,
            'adjustment_type' => $adjustment->adjustment_type,
            'title' => $adjustment->title,
            'amount' => (float) $adjustment->amount,
            'effective_date' => $adjustment->effective_date ? Carbon::parse($adjustment->effective_date)->toDateString() : null,
            'status' => $adjustment->status,
            'notes' => $adjustment->notes,
            'source' => $adjustment->kpi_result_id ? [
                'type' => 'kpi',
                'result_id' => (int) $adjustment->kpi_result_id,
                'reference' => 'KPI-'.str_replace('-', '', $adjustment->kpi_month).'-'.str_pad((string) $adjustment->kpi_result_id, 4, '0', STR_PAD_LEFT),
                'month' => $adjustment->kpi_month,
                'score' => (float) $adjustment->kpi_score,
            ] : null,
        ];
    }

    private function isKpiBonus(int $adjustmentId): bool
    {
        return DB::table('kpi_results')->where('payroll_adjustment_id', $adjustmentId)->exists();
    }

    private function authorizePermission(Request $request, string|array $permission): void
    {
        abort_unless(array_intersect((array) $permission, AppAccess::permissionsForRole($request->user()?->role)), 403);
    }
}
