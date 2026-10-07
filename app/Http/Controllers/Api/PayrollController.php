<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payroll;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use App\Support\SalaryDefaults;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission($request, 'office.payroll.view');

        $query = Payroll::query()->latest('id');

        if ($request->filled('month')) {
            $query->where('month', $request->query('month'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('employee_type')) {
            $query->where('employee_type', $request->query('employee_type'));
        }

        $paginator = $query->paginate(min(max((int) $request->query('per_page', 10), 1), 50));

        return ApiResponse::success('Payrolls loaded.', [
            'items' => collect($paginator->items())->map(fn (Payroll $payroll) => $this->payrollPayload($payroll)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, Payroll $payroll)
    {
        $this->authorizePermission($request, 'office.payroll.view');
        $payroll = $this->refreshDraft($payroll);

        return ApiResponse::success('Payroll loaded.', [
            'payroll' => $this->payrollPayload($payroll),
            'items' => $payroll->items()->orderBy('employee_name')->get()->map(fn ($item) => $this->itemPayload($item)),
        ]);
    }

    public function salaryHistory(Request $request)
    {
        $this->authorizePermission($request, 'office.payroll.view');

        $query = DB::table('payroll_items')
            ->join('payrolls', 'payroll_items.payroll_id', '=', 'payrolls.id')
            ->where('payrolls.status', 'paid');

        if ($request->filled('month')) {
            $query->where('payrolls.month', $request->query('month'));
        }

        if ($request->filled('employee_type')) {
            $query->where('payroll_items.employee_type', $request->query('employee_type'));
        }

        if ($request->filled('employee_id')) {
            $query->where('payroll_items.employee_id', $request->query('employee_id'));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('payroll_items.employee_name', 'like', "%{$search}%")
                    ->orWhere('payroll_items.employee_code', 'like', "%{$search}%")
                    ->orWhere('payrolls.code', 'like', "%{$search}%")
                    ->orWhere('payrolls.payment_reference', 'like', "%{$search}%");
            });
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as payments_count, COUNT(DISTINCT payroll_items.employee_id) as employees_count, COALESCE(SUM(payroll_items.gross_pay), 0) as total_gross, COALESCE(SUM(payroll_items.advance_deduction + payroll_items.other_deduction), 0) as total_deductions, COALESCE(SUM(payroll_items.net_pay), 0) as total_net, MAX(payrolls.paid_at) as latest_paid_at')
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
            ->orderBy('payroll_items.employee_name')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Salary history loaded.', [
            'items' => collect($paginator->items())->map(fn ($item) => $this->salaryHistoryPayload($item)),
            'summary' => [
                'payments_count' => (int) ($summary->payments_count ?? 0),
                'employees_count' => (int) ($summary->employees_count ?? 0),
                'total_gross' => (float) ($summary->total_gross ?? 0),
                'total_deductions' => (float) ($summary->total_deductions ?? 0),
                'total_net' => (float) ($summary->total_net ?? 0),
                'latest_paid_at' => $summary->latest_paid_at ?? null,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function salaryHistoryShow(Request $request, int $id)
    {
        $this->authorizePermission($request, 'office.payroll.view');
        $item = DB::table('payroll_items')
            ->join('payrolls', 'payroll_items.payroll_id', '=', 'payrolls.id')
            ->where('payroll_items.id', $id)
            ->where('payrolls.status', 'paid')
            ->select('payroll_items.*', 'payrolls.code as payroll_code', 'payrolls.month', 'payrolls.period_start', 'payrolls.period_end', 'payrolls.status', 'payrolls.paid_at', 'payrolls.payment_reference')
            ->first();
        abort_unless($item, 404);

        return ApiResponse::success('Salary history record loaded.', ['item' => $this->salaryHistoryPayload($item)]);
    }

    public function generate(Request $request)
    {
        $this->authorizePermission($request, ['office.payroll.drafts.prepare', 'office.payroll.manage']);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'employee_type' => ['nullable', Rule::in(['office', 'sales', 'sales_supervisor', 'driver', 'warehouse'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month']);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $employeeType = $validated['employee_type'] ?? null;

        $payroll = DB::transaction(function () use ($employeeType, $end, $request, $start, $validated) {
            $payroll = Payroll::query()
                ->where('month', $validated['month'])
                ->where(function ($query) use ($employeeType) {
                    $employeeType === null ? $query->whereNull('employee_type') : $query->where('employee_type', $employeeType);
                })
                ->lockForUpdate()
                ->first();

            if ($payroll && $payroll->status !== 'draft') {
                abort(409, 'Only draft payrolls can be regenerated.');
            }

            if (! $payroll) {
                $payroll = Payroll::create([
                    'code' => $this->nextCode($validated['month'], $employeeType),
                    'month' => $validated['month'],
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'employee_type' => $employeeType,
                    'status' => 'draft',
                    'generated_by' => $request->user()?->id,
                ]);
            }

            $payroll->fill([
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'notes' => $validated['notes'] ?? $payroll->notes,
                'generated_by' => $request->user()?->id,
            ])->save();

            $payroll->items()->delete();
            $items = $this->draftItems($payroll, $start, $end, $employeeType);

            foreach ($items as $item) {
                $payroll->items()->create($item);
            }

            $payroll->update([
                'total_gross' => collect($items)->sum('gross_pay'),
                'total_deductions' => collect($items)->sum(fn ($item) => $item['advance_deduction'] + $item['other_deduction']),
                'total_net' => collect($items)->sum('net_pay'),
            ]);

            return $payroll->fresh();
        });

        return ApiResponse::success('Payroll draft generated.', [
            'payroll' => $this->payrollPayload($payroll),
            'items' => $payroll->items()->orderBy('employee_name')->get()->map(fn ($item) => $this->itemPayload($item)),
        ], 201);
    }

    public function approve(Request $request, Payroll $payroll)
    {
        $this->authorizePermission($request, ['office.payroll.drafts.approve', 'office.payroll.manage']);

        abort_unless($payroll->status === 'draft', 409, 'Only draft payrolls can be approved.');
        abort_unless($payroll->items()->exists(), 409, 'Payroll must have at least one item before approval.');
        if ($request->user()?->role === 'Finance Manager') {
            abort_if($payroll->generated_by === $request->user()->id, 409, 'The payroll preparer cannot approve their own draft.');
        }

        DB::transaction(function () use ($payroll, $request) {
            $locked = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'draft', 409, 'Only draft payrolls can be approved.');
            $locked = $this->refreshDraft($locked);
            $locked->update([
                'status' => 'approved',
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);
        });

        return ApiResponse::success('Payroll approved.', [
            'payroll' => $this->payrollPayload($payroll->fresh()),
        ]);
    }

    public function markPaid(Request $request, Payroll $payroll)
    {
        $this->authorizePermission($request, ['office.payroll.drafts.pay', 'office.payroll.manage']);

        abort_unless($payroll->status === 'approved', 409, 'Only approved payrolls can be marked paid.');
        if ($request->user()?->role === 'Accountant') {
            abort_if($payroll->approved_by === $request->user()->id, 409, 'The payroll approver cannot mark the same payroll as paid.');
        }

        $validated = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:150'],
        ]);

        $payroll->update([
            'status' => 'paid',
            'paid_by' => $request->user()?->id,
            'paid_at' => now(),
            'payment_reference' => $validated['payment_reference'] ?? $payroll->payment_reference,
        ]);

        return ApiResponse::success('Payroll marked as paid.', [
            'payroll' => $this->payrollPayload($payroll->fresh()),
        ]);
    }

    public function destroy(Request $request, Payroll $payroll)
    {
        $this->authorizePermission($request, ['office.payroll.drafts.prepare', 'office.payroll.manage']);

        abort_unless($payroll->status === 'draft', 409, 'Only draft payrolls can be deleted.');

        $payroll->delete();

        return ApiResponse::success('Payroll draft deleted.');
    }

    private function refreshDraft(Payroll $payroll): Payroll
    {
        if ($payroll->status !== 'draft') return $payroll;

        return DB::transaction(function () use ($payroll) {
            $payroll = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);
            if ($payroll->status !== 'draft') return $payroll;
            $items = $this->draftItems($payroll, $payroll->period_start->copy()->startOfDay(), $payroll->period_end->copy()->endOfDay(), $payroll->employee_type);
            foreach ($items as $item) {
                $payroll->items()->updateOrCreate(['employee_id' => $item['employee_id']], $item);
            }
            $payroll->items()->whereNotIn('employee_id', array_column($items, 'employee_id'))->delete();
            $payroll->update([
                'total_gross' => collect($items)->sum('gross_pay'),
                'total_deductions' => collect($items)->sum(fn ($item) => $item['advance_deduction'] + $item['other_deduction']),
                'total_net' => collect($items)->sum('net_pay'),
            ]);

            return $payroll;
        });
    }

    private function draftItems(Payroll $payroll, Carbon $start, Carbon $end, ?string $employeeType): array
    {
        $query = DB::table('employees')
            ->leftJoin('attendance_records', function ($join) use ($start, $end) {
                $join->on('employees.id', '=', 'attendance_records.employee_id')
                    ->whereBetween('attendance_records.attendance_at', [$start, $end]);
            })
            ->where('employees.is_active', true)
            ->select(
                'employees.id',
                'employees.code',
                'employees.name',
                'employees.employee_type',
                'employees.base_salary',
                DB::raw("SUM(CASE WHEN attendance_records.status IN ('accepted', 'late') THEN 1 ELSE 0 END) as accepted_count"),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count"),
                DB::raw("SUM(CASE WHEN attendance_records.rejection_reason = 'gps_denied' THEN 1 ELSE 0 END) as gps_denied_count"),
                DB::raw("SUM(CASE WHEN attendance_records.rejection_reason = 'outside_allowed_radius' THEN 1 ELSE 0 END) as outside_radius_count"),
                DB::raw('COALESCE(SUM(attendance_records.late_fine), 0) as late_fine'),
                DB::raw('MIN(attendance_records.attendance_at) as first_attendance_at'),
                DB::raw('MAX(attendance_records.attendance_at) as last_attendance_at')
            )
            ->groupBy('employees.id', 'employees.code', 'employees.name', 'employees.employee_type', 'employees.base_salary')
            ->orderBy('employees.name');

        if ($employeeType) {
            $query->where('employees.employee_type', $employeeType);
        }

        $employees = $query->get();
        app(KpiReviewController::class)->refreshExistingMonthlyDrafts($payroll->month, $employees->pluck('id')->all());
        $unpostedBonuses = DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->where('kpi_periods.month', $payroll->month)
            ->whereIn('kpi_results.employee_id', $employees->pluck('id'))
            ->whereIn('kpi_results.status', ['draft', 'submitted', 'approved'])
            ->whereNull('kpi_results.payroll_adjustment_id')
            ->pluck('kpi_results.bonus_amount', 'kpi_results.employee_id');
        $adjustments = DB::table('payroll_adjustments')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('status', 'active')
            ->whereBetween('effective_date', [$start, $end])
            ->select('employee_id', 'adjustment_type', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('employee_id', 'adjustment_type')
            ->get()
            ->groupBy('employee_id');

        $salaryDefaults = Company::query()->oldest('id')->first()?->default_base_salaries ?? SalaryDefaults::AMOUNTS;

        return $employees->map(function ($employee) use ($adjustments, $payroll, $salaryDefaults, $unpostedBonuses) {
            $baseSalary = (float) ($employee->base_salary ?? SalaryDefaults::forEmployeeType($employee->employee_type, $salaryDefaults));
            $employeeAdjustments = $adjustments->get($employee->id, collect())->keyBy('adjustment_type');
            $allowance = (float) ($employeeAdjustments->get('allowance')->total_amount ?? 0);
            $incentive = (float) ($employeeAdjustments->get('incentive')->total_amount ?? 0) + (float) ($unpostedBonuses[$employee->id] ?? 0);
            $ot = (float) ($employeeAdjustments->get('ot')->total_amount ?? 0);
            $advance = (float) ($employeeAdjustments->get('advance')->total_amount ?? 0);
            $grossPay = $baseSalary + $allowance + $incentive + $ot;

            return [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'employee_code' => $employee->code,
                'employee_name' => $employee->name,
                'employee_type' => $employee->employee_type,
                'accepted_count' => (int) $employee->accepted_count,
                'rejected_count' => (int) $employee->rejected_count,
                'gps_denied_count' => (int) $employee->gps_denied_count,
                'outside_radius_count' => (int) $employee->outside_radius_count,
                'first_attendance_at' => $employee->first_attendance_at,
                'last_attendance_at' => $employee->last_attendance_at,
                'base_salary' => $baseSalary,
                'allowance_amount' => $allowance,
                'incentive_amount' => $incentive,
                'ot_amount' => $ot,
                'advance_deduction' => $advance,
                'other_deduction' => (float) $employee->late_fine,
                'gross_pay' => $grossPay,
                'net_pay' => $grossPay - $advance - (float) $employee->late_fine,
                'remarks' => $employee->late_fine > 0 ? 'Late attendance fine: '.number_format($employee->late_fine, 2, '.', '').' MMK' : null,
            ];
        })->all();
    }

    private function payrollPayload(Payroll $payroll): array
    {
        return [
            'id' => $payroll->id,
            'code' => $payroll->code,
            'month' => $payroll->month,
            'period_start' => optional($payroll->period_start)->toDateString(),
            'period_end' => optional($payroll->period_end)->toDateString(),
            'employee_type' => $payroll->employee_type,
            'status' => $payroll->status,
            'total_gross' => (float) $payroll->total_gross,
            'total_deductions' => (float) $payroll->total_deductions,
            'total_net' => (float) $payroll->total_net,
            'notes' => $payroll->notes,
            'items_count' => $payroll->items_count ?? $payroll->items()->count(),
            'approved_at' => optional($payroll->approved_at)->toDateTimeString(),
            'paid_at' => optional($payroll->paid_at)->toDateTimeString(),
            'payment_reference' => $payroll->payment_reference,
            'updated_at' => optional($payroll->updated_at)->toDateTimeString(),
        ];
    }

    private function itemPayload($item): array
    {
        return [
            'id' => $item->id,
            'employee_id' => $item->employee_id,
            'employee_code' => $item->employee_code,
            'employee_name' => $item->employee_name,
            'employee_type' => $item->employee_type,
            'accepted_count' => $item->accepted_count,
            'rejected_count' => $item->rejected_count,
            'gps_denied_count' => $item->gps_denied_count,
            'outside_radius_count' => $item->outside_radius_count,
            'first_attendance_at' => $this->dateTimeString($item->first_attendance_at),
            'last_attendance_at' => $this->dateTimeString($item->last_attendance_at),
            'base_salary' => (float) $item->base_salary,
            'allowance_amount' => (float) $item->allowance_amount,
            'incentive_amount' => (float) $item->incentive_amount,
            'ot_amount' => (float) $item->ot_amount,
            'advance_deduction' => (float) $item->advance_deduction,
            'other_deduction' => (float) $item->other_deduction,
            'gross_pay' => (float) $item->gross_pay,
            'net_pay' => (float) $item->net_pay,
            'remarks' => $item->remarks,
        ];
    }

    private function salaryHistoryPayload($item): array
    {
        return [
            ...$this->itemPayload($item),
            'payroll_id' => $item->payroll_id,
            'payroll_code' => $item->payroll_code,
            'month' => $item->month,
            'period_start' => Carbon::parse($item->period_start)->toDateString(),
            'period_end' => Carbon::parse($item->period_end)->toDateString(),
            'status' => $item->status,
            'paid_at' => $item->paid_at,
            'payment_reference' => $item->payment_reference,
        ];
    }

    private function dateTimeString($value): ?string
    {
        return $value ? Carbon::parse($value)->toDateTimeString() : null;
    }

    private function nextCode(string $month, ?string $employeeType): string
    {
        return 'PAY-'.str_replace('-', '', $month).'-'.Str::upper($employeeType ?: 'ALL');
    }

    private function authorizePermission(Request $request, string|array $permission): void
    {
        abort_unless(array_intersect((array) $permission, AppAccess::permissionsForRole($request->user()?->role)), 403);
    }
}
