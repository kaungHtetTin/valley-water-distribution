<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KpiReviewController extends Controller
{
    public function meta(Request $request)
    {
        $this->authorizePermission($request, 'office.kpi.view');

        $templates = DB::table('kpi_templates')
            ->where('is_active', true)
            ->orderBy('employee_type')
            ->get()
            ->map(function ($template) {
                $template->target_bonus = (float) $template->target_bonus;
                $template->metrics = DB::table('kpi_template_metrics')
                    ->where('kpi_template_id', $template->id)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn ($metric) => $this->metricPayload($metric));
                $template->bonus_rules = DB::table('kpi_bonus_rules')
                    ->where('kpi_template_id', $template->id)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn ($rule) => [
                        'minimum_score' => (float) $rule->minimum_score,
                        'maximum_score' => $rule->maximum_score === null ? null : (float) $rule->maximum_score,
                        'payout_percent' => (float) $rule->payout_percent,
                    ]);

                return $template;
            });

        return ApiResponse::success('KPI setup loaded.', ['templates' => $templates]);
    }

    public function targets(Request $request)
    {
        $this->authorizePermission($request, 'office.kpi.view');
        $validated = $request->validate([
            'employee_type' => ['nullable', Rule::in(['office', 'sales', 'sales_supervisor', 'driver', 'warehouse'])],
            'template_id' => ['nullable', 'integer', 'exists:kpi_templates,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $templates = DB::table('kpi_templates')
            ->where('is_active', true)
            ->orderByRaw("FIELD(code, 'OFFICE-STAFF-V1', 'SALES-REP-V1', 'SALES-SUPERVISOR-V1', 'DRIVER-V1', 'HELPER-V1', 'STOREKEEPER-V1')")
            ->orderBy('name')
            ->get()
            ->map(function ($template) {
                $template->target_bonus = (float) $template->target_bonus;
                $template->metrics = DB::table('kpi_template_metrics')
                    ->where('kpi_template_id', $template->id)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn ($metric) => $this->metricPayload($metric));

                return $template;
            });

        $roleItems = $templates
            ->filter(function ($template) use ($validated) {
                if (! empty($validated['employee_type']) && $template->employee_type !== $validated['employee_type']) {
                    return false;
                }
                if (! empty($validated['template_id']) && (int) $template->id !== (int) $validated['template_id']) {
                    return false;
                }
                $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));
                if ($search && ! str_contains(mb_strtolower("{$template->name} {$template->code}"), $search)) {
                    return false;
                }

                return true;
            })
            ->map(function ($template) {
                $staff = DB::table('kpi_staff_profiles')
                    ->join('employees', 'kpi_staff_profiles.employee_id', '=', 'employees.id')
                    ->where('kpi_staff_profiles.kpi_template_id', $template->id)
                    ->where('employees.is_active', true)
                    ->orderBy('employees.name')
                    ->get(['employees.id', 'employees.code', 'employees.name']);
                $targetMetrics = collect($template->metrics)->where('calculation_type', '!=', 'manual');

                return [
                    'id' => (int) $template->id,
                    'code' => $template->code,
                    'name' => $template->name,
                    'employee_type' => $template->employee_type,
                    'target_bonus' => (float) $template->target_bonus,
                    'configured_targets' => $targetMetrics->whereNotNull('default_target')->count(),
                    'total_targets' => $targetMetrics->count(),
                    'metrics' => $template->metrics,
                    'staff_count' => $staff->count(),
                    'staff' => $staff->map(fn ($employee) => [
                        'id' => (int) $employee->id,
                        'code' => $employee->code,
                        'name' => $employee->name,
                    ])->values(),
                ];
            })->values();

        $summary = DB::table('employees')
            ->leftJoin('kpi_staff_profiles', 'employees.id', '=', 'kpi_staff_profiles.employee_id')
            ->where('employees.is_active', true)
            ->selectRaw('COUNT(employees.id) as staff, COUNT(kpi_staff_profiles.id) as configured')
            ->first();

        return ApiResponse::success('Staff KPI targets loaded.', [
            'items' => $roleItems,
            'templates' => $templates,
            'summary' => [
                'staff' => (int) ($summary->staff ?? 0),
                'configured' => (int) ($summary->configured ?? 0),
                'unassigned' => (int) (($summary->staff ?? 0) - ($summary->configured ?? 0)),
                'roles' => $templates->count(),
            ],
        ]);
    }

    public function employeeTarget(Request $request, int $employeeId)
    {
        $this->authorizePermission($request, 'office.kpi.view');
        $employee = DB::table('employees')
            ->leftJoin('kpi_staff_profiles', 'employees.id', '=', 'kpi_staff_profiles.employee_id')
            ->leftJoin('kpi_templates', 'kpi_staff_profiles.kpi_template_id', '=', 'kpi_templates.id')
            ->where('employees.id', $employeeId)
            ->select(
                'employees.id',
                'employees.code',
                'employees.name',
                'employees.employee_type',
                'kpi_staff_profiles.id as profile_id',
                'kpi_staff_profiles.kpi_template_id as template_id',
                'kpi_staff_profiles.target_bonus',
                'kpi_templates.code as template_code',
                'kpi_templates.name as template_name'
            )
            ->first();
        abort_unless($employee, 404, 'Employee not found.');

        $templates = DB::table('kpi_templates')
            ->where('is_active', true)
            ->where('employee_type', $employee->employee_type)
            ->orderBy('employee_type')
            ->orderBy('name')
            ->get()
            ->map(function ($template) {
                $template->target_bonus = (float) $template->target_bonus;
                $template->metrics = DB::table('kpi_template_metrics')
                    ->where('kpi_template_id', $template->id)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn ($metric) => $this->metricPayload($metric));

                return $template;
            });

        return ApiResponse::success('Employee KPI target loaded.', [
            'employee' => $this->staffTargetPayload($employee),
            'templates' => $templates,
        ]);
    }

    public function saveRoleTarget(Request $request, int $templateId)
    {
        $this->authorizePermission($request, 'office.kpi.manage');
        $template = DB::table('kpi_templates')->where('id', $templateId)->where('is_active', true)->first();
        abort_unless($template, 404, 'Active KPI role not found.');

        $validated = $request->validate([
            'target_bonus' => ['required', 'numeric', 'min:0'],
            'targets' => ['nullable', 'array'],
            'targets.*.metric_id' => ['required', 'integer', 'exists:kpi_template_metrics,id'],
            'targets.*.target_value' => ['nullable', 'numeric', 'min:0'],
            'apply_to_staff' => ['nullable', 'boolean'],
        ]);
        $metrics = DB::table('kpi_template_metrics')->where('kpi_template_id', $templateId)->orderBy('sort_order')->get();
        $metricIds = $metrics->pluck('id')->map(fn ($id) => (int) $id)->all();
        $targets = collect($validated['targets'] ?? [])->keyBy(fn ($target) => (int) $target['metric_id']);
        abort_if($targets->keys()->contains(fn ($id) => ! in_array((int) $id, $metricIds, true)), 422, 'A target does not belong to this KPI role.');

        $result = DB::transaction(function () use ($request, $templateId, $metrics, $targets, $validated) {
            $now = now();
            DB::table('kpi_templates')->where('id', $templateId)->update([
                'target_bonus' => $validated['target_bonus'],
                'updated_at' => $now,
            ]);
            foreach ($metrics as $metric) {
                $target = $targets->get((int) $metric->id);
                if ($metric->calculation_type !== 'manual') {
                    DB::table('kpi_template_metrics')->where('id', $metric->id)->update([
                        'default_target' => $target['target_value'] ?? null,
                        'updated_at' => $now,
                    ]);
                }
            }

            $staffCount = 0;
            $draftCount = 0;
            if ($validated['apply_to_staff'] ?? true) {
                $profiles = DB::table('kpi_staff_profiles')->where('kpi_template_id', $templateId)->get();
                foreach ($profiles as $profile) {
                    DB::table('kpi_staff_profiles')->where('id', $profile->id)->update([
                        'target_bonus' => $validated['target_bonus'],
                        'updated_by' => $request->user()?->id,
                        'updated_at' => $now,
                    ]);
                    DB::table('kpi_staff_target_items')->where('kpi_staff_profile_id', $profile->id)->delete();
                    foreach ($metrics as $metric) {
                        $target = $targets->get((int) $metric->id);
                        $targetValue = $target ? ($target['target_value'] ?? null) : $metric->default_target;
                        if ($metric->calculation_type === 'manual' || $targetValue === null || $targetValue === '') {
                            continue;
                        }
                        DB::table('kpi_staff_target_items')->insert([
                            'kpi_staff_profile_id' => $profile->id,
                            'kpi_template_metric_id' => $metric->id,
                            'target_value' => $targetValue,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $drafts = DB::table('kpi_results')->where('employee_id', $profile->employee_id)->where('status', 'draft')->get();
                    foreach ($drafts as $draft) {
                        DB::table('kpi_results')->where('id', $draft->id)->update(['target_bonus' => $validated['target_bonus'], 'updated_at' => $now]);
                        foreach ($metrics as $metric) {
                            $target = $targets->get((int) $metric->id);
                            $targetValue = $metric->calculation_type === 'manual'
                                ? $metric->default_target
                                : ($target ? ($target['target_value'] ?? null) : $metric->default_target);
                            DB::table('kpi_result_items')->updateOrInsert(
                                ['kpi_result_id' => $draft->id, 'kpi_template_metric_id' => $metric->id],
                                ['target_value' => $targetValue, 'created_at' => $now, 'updated_at' => $now]
                            );
                        }
                        $this->recalculate((int) $draft->id);
                        $draftCount++;
                    }
                    $staffCount++;
                }
            }

            return ['staff_count' => $staffCount, 'updated_drafts' => $draftCount];
        });

        return ApiResponse::success('Role targets saved and applied to assigned staff.', $result);
    }

    public function saveTarget(Request $request, int $employeeId)
    {
        $this->authorizePermission($request, 'office.kpi.manage');
        $employee = DB::table('employees')->where('id', $employeeId)->where('is_active', true)->first();
        abort_unless($employee, 404, 'Active employee not found.');

        $validated = $request->validate([
            'template_id' => ['nullable', 'integer', Rule::exists('kpi_templates', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'target_bonus' => ['nullable', 'numeric', 'min:0'],
            'targets' => ['nullable', 'array'],
            'targets.*.metric_id' => ['required', 'integer', 'exists:kpi_template_metrics,id'],
            'targets.*.target_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (empty($validated['template_id'])) {
            DB::table('kpi_staff_profiles')->where('employee_id', $employeeId)->delete();

            return ApiResponse::success('KPI role removed from staff member.', ['employee_id' => $employeeId, 'updated_drafts' => 0]);
        }

        $template = DB::table('kpi_templates')->where('id', $validated['template_id'])->where('is_active', true)->first();
        abort_unless($template, 422, 'Choose an active KPI role.');
        abort_unless($template->employee_type === $employee->employee_type, 422, 'Choose a KPI role for this employee category.');
        $metrics = DB::table('kpi_template_metrics')->where('kpi_template_id', $template->id)->orderBy('sort_order')->get();
        $metricIds = $metrics->pluck('id')->map(fn ($id) => (int) $id)->all();
        $targets = collect($validated['targets'] ?? [])->keyBy(fn ($target) => (int) $target['metric_id']);
        abort_if($targets->keys()->contains(fn ($id) => ! in_array((int) $id, $metricIds, true)), 422, 'A target does not belong to the selected KPI role.');

        $updatedDrafts = DB::transaction(function () use ($request, $employeeId, $template, $metrics, $targets, $validated) {
            $now = now();
            $profile = DB::table('kpi_staff_profiles')->where('employee_id', $employeeId)->first();
            $profileValues = [
                'kpi_template_id' => $template->id,
                'target_bonus' => $validated['target_bonus'] ?? $template->target_bonus,
                'updated_by' => $request->user()?->id,
                'updated_at' => $now,
            ];
            if ($profile) {
                DB::table('kpi_staff_profiles')->where('id', $profile->id)->update($profileValues);
                $profileId = $profile->id;
            } else {
                $profileId = DB::table('kpi_staff_profiles')->insertGetId([
                    'employee_id' => $employeeId,
                    ...$profileValues,
                    'created_by' => $request->user()?->id,
                    'created_at' => $now,
                ]);
            }

            DB::table('kpi_staff_target_items')->where('kpi_staff_profile_id', $profileId)->delete();
            foreach ($metrics as $metric) {
                $target = $targets->get((int) $metric->id);
                $targetValue = $target['target_value'] ?? $metric->default_target;
                if ($metric->calculation_type === 'manual' || $targetValue === null || $targetValue === '') {
                    continue;
                }
                DB::table('kpi_staff_target_items')->insert([
                    'kpi_staff_profile_id' => $profileId,
                    'kpi_template_metric_id' => $metric->id,
                    'target_value' => $targetValue,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $drafts = DB::table('kpi_results')->where('employee_id', $employeeId)->where('status', 'draft')->get();
            foreach ($drafts as $draft) {
                $templateChanged = (int) $draft->kpi_template_id !== (int) $template->id;
                DB::table('kpi_results')->where('id', $draft->id)->update([
                    'kpi_template_id' => $template->id,
                    'target_bonus' => $validated['target_bonus'] ?? $template->target_bonus,
                    'overall_score' => $templateChanged ? 0 : $draft->overall_score,
                    'bonus_amount' => $templateChanged ? 0 : $draft->bonus_amount,
                    'updated_at' => $now,
                ]);
                if ($templateChanged) {
                    DB::table('kpi_result_items')->where('kpi_result_id', $draft->id)->delete();
                }
                foreach ($metrics as $metric) {
                    $savedTarget = DB::table('kpi_staff_target_items')
                        ->where('kpi_staff_profile_id', $profileId)
                        ->where('kpi_template_metric_id', $metric->id)
                        ->value('target_value');
                    DB::table('kpi_result_items')->updateOrInsert(
                        ['kpi_result_id' => $draft->id, 'kpi_template_metric_id' => $metric->id],
                        [
                            'target_value' => $metric->calculation_type === 'manual' ? $metric->default_target : ($savedTarget ?? $metric->default_target),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
                $this->recalculate((int) $draft->id);
            }

            return $drafts->count();
        });

        return ApiResponse::success('Staff KPI role and targets saved.', [
            'employee_id' => $employeeId,
            'updated_drafts' => $updatedDrafts,
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'office.kpi.view');
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'employee_type' => ['nullable', Rule::in(['office', 'sales', 'sales_supervisor', 'driver', 'warehouse'])],
            'template_id' => ['nullable', 'integer', 'exists:kpi_templates,id'],
            'status' => ['nullable', Rule::in(['draft', 'submitted', 'approved'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $selectedMonth = $validated['month'] ?? now()->format('Y-m');
        $this->ensureMonthlyReviews($selectedMonth, createdBy: $request->user()?->id, refreshExisting: true);

        $query = DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->join('employees', 'kpi_results.employee_id', '=', 'employees.id');

        $query->where('kpi_periods.month', $selectedMonth);
        if (! empty($validated['employee_type'])) {
            $query->where('kpi_templates.employee_type', $validated['employee_type']);
        }
        if (! empty($validated['template_id'])) {
            $query->where('kpi_results.kpi_template_id', $validated['template_id']);
        }
        if (! empty($validated['status'])) {
            $query->where('kpi_results.status', $validated['status']);
        }
        if ($search = trim((string) ($validated['search'] ?? ''))) {
            $query->where(function ($query) use ($search) {
                $query->where('employees.name', 'like', "%{$search}%")
                    ->orWhere('employees.code', 'like', "%{$search}%");
            });
        }

        $summaryRows = (clone $query)
            ->select('kpi_results.status', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(kpi_results.bonus_amount), 0) as bonus_total'))
            ->groupBy('kpi_results.status')
            ->get();
        $summary = ['total' => 0, 'draft' => 0, 'submitted' => 0, 'approved' => 0, 'posted' => 0, 'bonus_total' => 0.0];
        foreach ($summaryRows as $row) {
            $summary['total'] += (int) $row->total;
            $summary[$row->status] = (int) $row->total;
            $summary['bonus_total'] += (float) $row->bonus_total;
        }
        $summary['posted'] = (clone $query)
            ->where('kpi_results.status', 'approved')
            ->whereNotNull('kpi_results.payroll_adjustment_id')
            ->count('kpi_results.id');

        $paginator = $query
            ->select(
                'kpi_results.*',
                'kpi_periods.month',
                'kpi_templates.id as template_id',
                'kpi_templates.code as template_code',
                'kpi_templates.name as template_name',
                'kpi_templates.employee_type',
                'employees.code as employee_code',
                'employees.name as employee_name'
            )
            ->orderBy('employees.name')
            ->paginate((int) ($validated['per_page'] ?? 30));

        return ApiResponse::success('KPI reviews loaded.', [
            'items' => collect($paginator->items())->map(fn ($result) => $this->resultPayload($result)),
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function mobile(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user?->role, ['Sales Representative', 'Driver'], true), 403);
        abort_unless($user?->employee_id, 404, 'Employee profile is not linked to this account.');

        $this->authorizePermission(
            $request,
            $user->role === 'Driver' ? 'driver.payroll.view' : 'sales.payroll.view'
        );

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $selectedMonth = $validated['month'] ?? now()->format('Y-m');
        $this->ensureMonthlyReviews($selectedMonth, createdBy: $user->id, refreshExisting: true);

        $baseQuery = DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->join('employees', 'kpi_results.employee_id', '=', 'employees.id')
            ->where('kpi_results.employee_id', $user->employee_id)
            ->select(
                'kpi_results.*',
                'kpi_periods.month',
                'kpi_templates.id as template_id',
                'kpi_templates.code as template_code',
                'kpi_templates.name as template_name',
                'kpi_templates.employee_type',
                'employees.code as employee_code',
                'employees.name as employee_name'
            );

        $history = (clone $baseQuery)
            ->orderByDesc('kpi_periods.month')
            ->limit(12)
            ->get()
            ->map(fn ($result) => $this->resultPayload($result));

        $result = (clone $baseQuery)->where('kpi_periods.month', $selectedMonth)->first();
        if (! $result && ! array_key_exists('month', $validated)) {
            $result = (clone $baseQuery)->orderByDesc('kpi_periods.month')->first();
            $selectedMonth = $result?->month ?? $selectedMonth;
        }

        $previous = (clone $baseQuery)
            ->where('kpi_periods.month', '<', $selectedMonth)
            ->orderByDesc('kpi_periods.month')
            ->first();

        $detail = $result ? $this->detailPayload((int) $result->id) : ['result' => null, 'items' => []];

        return ApiResponse::success('Personal KPI loaded.', [
            ...$detail,
            'selected_month' => $selectedMonth,
            'previous' => $previous ? $this->resultPayload($previous) : null,
            'history' => $history,
        ]);
    }

    public function report(Request $request)
    {
        $this->authorizePermission($request, 'office.kpi.view');
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['month', 'year'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
            'employee_type' => ['nullable', Rule::in(['office', 'sales', 'sales_supervisor', 'driver', 'warehouse'])],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $period = $validated['period'] ?? 'month';
        $month = $validated['month'] ?? now()->format('Y-m');
        $year = (int) ($validated['year'] ?? substr($month, 0, 4));
        $employeeType = $validated['employee_type'] ?? null;
        $employeeId = $validated['employee_id'] ?? null;

        if ($period === 'month') {
            $this->ensureMonthlyReviews($month, createdBy: $request->user()?->id, refreshExisting: true);
        }

        $query = fn () => DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->join('employees', 'kpi_results.employee_id', '=', 'employees.id');
        $applyPeople = function ($builder) use ($employeeId, $employeeType) {
            if ($employeeType) {
                $builder->where('kpi_templates.employee_type', $employeeType);
            }
            if ($employeeId) {
                $builder->where('kpi_results.employee_id', $employeeId);
            }

            return $builder;
        };
        $applyPeriod = function ($builder) use ($month, $period, $year) {
            return $period === 'month'
                ? $builder->where('kpi_periods.month', $month)
                : $builder->where('kpi_periods.month', 'like', "{$year}-%");
        };

        $summary = $applyPeriod($applyPeople($query()))
            ->selectRaw("COUNT(*) as reviews, COALESCE(AVG(kpi_results.overall_score), 0) as average_score, COALESCE(SUM(kpi_results.target_bonus), 0) as target_bonus_total, COALESCE(SUM(kpi_results.bonus_amount), 0) as bonus_total, SUM(CASE WHEN kpi_results.status = 'approved' THEN 1 ELSE 0 END) as approved, SUM(CASE WHEN kpi_results.payroll_adjustment_id IS NOT NULL THEN 1 ELSE 0 END) as posted")
            ->first();

        $roleSummary = $applyPeriod($applyPeople($query()))
            ->selectRaw("kpi_templates.id as template_id, kpi_templates.code as template_code, kpi_templates.name as template_name, kpi_templates.employee_type, COUNT(*) as reviews, COALESCE(AVG(kpi_results.overall_score), 0) as average_score, COALESCE(SUM(kpi_results.bonus_amount), 0) as bonus_total, SUM(CASE WHEN kpi_results.status = 'approved' THEN 1 ELSE 0 END) as approved, SUM(CASE WHEN kpi_results.payroll_adjustment_id IS NOT NULL THEN 1 ELSE 0 END) as posted")
            ->groupBy('kpi_templates.id', 'kpi_templates.code', 'kpi_templates.name', 'kpi_templates.employee_type')
            ->orderBy('kpi_templates.name')
            ->get()
            ->map(fn ($row) => [
                'template_id' => (int) $row->template_id,
                'template_code' => $row->template_code,
                'template_name' => $row->template_name,
                'employee_type' => $row->employee_type,
                'reviews' => (int) $row->reviews,
                'average_score' => round((float) $row->average_score, 2),
                'bonus_total' => (float) $row->bonus_total,
                'approved' => (int) $row->approved,
                'posted' => (int) $row->posted,
            ]);

        $statusSummary = $applyPeriod($applyPeople($query()))
            ->selectRaw('kpi_results.status, COUNT(*) as reviews, COALESCE(SUM(kpi_results.bonus_amount), 0) as bonus_total')
            ->groupBy('kpi_results.status')
            ->orderBy('kpi_results.status')
            ->get()
            ->map(fn ($row) => ['status' => $row->status, 'reviews' => (int) $row->reviews, 'bonus_total' => (float) $row->bonus_total]);

        $monthlyTrend = $applyPeople($query())
            ->where('kpi_periods.month', 'like', "{$year}-%")
            ->selectRaw('kpi_periods.month as label, COUNT(*) as reviews, COALESCE(AVG(kpi_results.overall_score), 0) as average_score, COALESCE(SUM(kpi_results.bonus_amount), 0) as bonus_total')
            ->groupBy('kpi_periods.month')
            ->orderBy('kpi_periods.month')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'reviews' => (int) $row->reviews, 'average_score' => round((float) $row->average_score, 2), 'bonus_total' => (float) $row->bonus_total]);

        $yearlyTrend = $applyPeople($query())
            ->whereBetween('kpi_periods.month', [($year - 4).'-01', $year.'-12'])
            ->selectRaw("SUBSTR(kpi_periods.month, 1, 4) as label, COUNT(*) as reviews, COALESCE(AVG(kpi_results.overall_score), 0) as average_score, COALESCE(SUM(kpi_results.bonus_amount), 0) as bonus_total")
            ->groupByRaw("SUBSTR(kpi_periods.month, 1, 4)")
            ->orderByRaw("SUBSTR(kpi_periods.month, 1, 4)")
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'reviews' => (int) $row->reviews, 'average_score' => round((float) $row->average_score, 2), 'bonus_total' => (float) $row->bonus_total]);

        $metricQuery = DB::table('kpi_result_items')
            ->join('kpi_results', 'kpi_result_items.kpi_result_id', '=', 'kpi_results.id')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->join('employees', 'kpi_results.employee_id', '=', 'employees.id')
            ->join('kpi_template_metrics', 'kpi_result_items.kpi_template_metric_id', '=', 'kpi_template_metrics.id');
        $metricBreakdown = $applyPeriod($applyPeople($metricQuery))
            ->selectRaw("kpi_templates.code as template_code, kpi_templates.name as template_name, kpi_templates.employee_type, kpi_template_metrics.code, kpi_template_metrics.name, kpi_template_metrics.unit, kpi_template_metrics.calculation_type, kpi_template_metrics.weight, COUNT(*) as reviews, AVG(COALESCE(kpi_result_items.target_value, kpi_template_metrics.default_target)) as target_average, AVG(CASE WHEN kpi_template_metrics.calculation_type = 'manual' THEN kpi_result_items.manual_score ELSE kpi_result_items.actual_value END) as actual_average, AVG(kpi_result_items.achievement_percent) as achievement_average, AVG(kpi_result_items.weighted_score) as points_average")
            ->groupBy('kpi_templates.id', 'kpi_templates.code', 'kpi_templates.name', 'kpi_templates.employee_type', 'kpi_template_metrics.id', 'kpi_template_metrics.code', 'kpi_template_metrics.name', 'kpi_template_metrics.unit', 'kpi_template_metrics.calculation_type', 'kpi_template_metrics.weight', 'kpi_template_metrics.sort_order')
            ->orderBy('kpi_templates.name')
            ->orderBy('kpi_template_metrics.sort_order')
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'template_code' => $row->template_code,
                'template_name' => $row->template_name,
                'employee_type' => $row->employee_type,
                'name' => $row->name,
                'unit' => $row->unit,
                'calculation_type' => $row->calculation_type,
                'weight' => (float) $row->weight,
                'reviews' => (int) $row->reviews,
                'target_average' => $row->target_average === null ? null : round((float) $row->target_average, 2),
                'actual_average' => $row->actual_average === null ? null : round((float) $row->actual_average, 2),
                'achievement_average' => $row->achievement_average === null ? null : round((float) $row->achievement_average, 2),
                'points_average' => $row->points_average === null ? null : round((float) $row->points_average, 2),
            ]);

        $reviews = $applyPeriod($applyPeople($query()))
            ->select('kpi_results.*', 'kpi_periods.month', 'kpi_templates.id as template_id', 'kpi_templates.code as template_code', 'kpi_templates.name as template_name', 'kpi_templates.employee_type', 'employees.code as employee_code', 'employees.name as employee_name')
            ->orderByDesc('kpi_periods.month')
            ->orderBy('employees.name')
            ->limit(250)
            ->get()
            ->map(fn ($result) => $this->resultPayload($result));

        $employees = DB::table('employees')
            ->join('kpi_staff_profiles', 'employees.id', '=', 'kpi_staff_profiles.employee_id')
            ->where('employees.is_active', true)
            ->orderBy('name')
            ->get(['employees.id', 'employees.code', 'employees.name', 'employees.employee_type'])
            ->map(fn ($employee) => ['id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name, 'employee_type' => $employee->employee_type]);

        return ApiResponse::success('KPI report loaded.', [
            'period' => ['type' => $period, 'month' => $month, 'year' => $year],
            'summary' => [
                'reviews' => (int) ($summary->reviews ?? 0),
                'average_score' => round((float) ($summary->average_score ?? 0), 2),
                'target_bonus_total' => (float) ($summary->target_bonus_total ?? 0),
                'bonus_total' => (float) ($summary->bonus_total ?? 0),
                'approved' => (int) ($summary->approved ?? 0),
                'posted' => (int) ($summary->posted ?? 0),
            ],
            'role_summary' => $roleSummary,
            'status_summary' => $statusSummary,
            'monthly_trend' => $monthlyTrend,
            'yearly_trend' => $yearlyTrend,
            'metric_breakdown' => $metricBreakdown,
            'reviews' => $reviews,
            'employees' => $employees,
        ]);
    }

    public function generate(Request $request)
    {
        $this->authorizePermission($request, 'office.kpi.manage');
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'template_id' => ['nullable', 'integer', 'exists:kpi_templates,id', 'required_without:employee_type'],
            'employee_type' => ['nullable', Rule::in(['office', 'sales', 'sales_supervisor', 'driver', 'warehouse']), 'required_without:template_id'],
        ]);

        $generation = $this->ensureMonthlyReviews(
            $validated['month'],
            $validated['template_id'] ?? null,
            $validated['employee_type'] ?? null,
            $request->user()?->id,
            true,
        );
        abort_if($generation['eligible'] === 0, 422, 'No active staff are assigned to this KPI role. Set staff targets first.');

        $message = $generation['created']
            ? "{$generation['created']} KPI review(s) created and current figures loaded."
            : 'Current figures refreshed for existing draft reviews.';

        return ApiResponse::success($message, $generation);
    }

    public function ensureMonthlyReviews(
        string $monthValue,
        ?int $templateId = null,
        ?string $employeeType = null,
        ?int $createdBy = null,
        bool $refreshExisting = false,
    ): array {
        $month = Carbon::createFromFormat('Y-m', $monthValue)->startOfMonth();

        return DB::transaction(function () use ($monthValue, $month, $templateId, $employeeType, $createdBy, $refreshExisting) {
            $now = now();
            DB::table('kpi_periods')->upsert([[
                'month' => $monthValue,
                'period_start' => $month->toDateString(),
                'period_end' => $month->copy()->endOfMonth()->toDateString(),
                'status' => 'open',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['month'], ['period_start', 'period_end', 'updated_at']);
            $periodId = (int) DB::table('kpi_periods')->where('month', $monthValue)->value('id');

            $profiles = DB::table('kpi_staff_profiles')
                ->join('employees', 'kpi_staff_profiles.employee_id', '=', 'employees.id')
                ->join('kpi_templates', 'kpi_staff_profiles.kpi_template_id', '=', 'kpi_templates.id')
                ->where('employees.is_active', true)
                ->where('kpi_templates.is_active', true)
                ->when($templateId, fn ($query) => $query->where('kpi_templates.id', $templateId))
                ->when($employeeType, fn ($query) => $query->where('kpi_templates.employee_type', $employeeType))
                ->orderBy('employees.name')
                ->get([
                    'employees.id as employee_id',
                    'kpi_staff_profiles.id as profile_id',
                    'kpi_staff_profiles.target_bonus',
                    'kpi_templates.id as template_id',
                    'kpi_templates.code as template_code',
                ]);

            $metricsByTemplate = [];
            $created = 0;
            $refreshed = 0;
            foreach ($profiles as $profile) {
                $metrics = $metricsByTemplate[$profile->template_id] ??= DB::table('kpi_template_metrics')
                    ->where('kpi_template_id', $profile->template_id)
                    ->orderBy('sort_order')
                    ->get();
                $staffTargets = DB::table('kpi_staff_target_items')
                    ->where('kpi_staff_profile_id', $profile->profile_id)
                    ->pluck('target_value', 'kpi_template_metric_id');
                $inserted = DB::table('kpi_results')->insertOrIgnore([
                    'kpi_period_id' => $periodId,
                    'kpi_template_id' => $profile->template_id,
                    'employee_id' => $profile->employee_id,
                    'status' => 'draft',
                    'target_bonus' => $profile->target_bonus,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $result = DB::table('kpi_results')
                    ->where('kpi_period_id', $periodId)
                    ->where('employee_id', $profile->employee_id)
                    ->first();
                if (! $result) {
                    continue;
                }

                foreach ($metrics as $metric) {
                    DB::table('kpi_result_items')->insertOrIgnore([
                        'kpi_result_id' => $result->id,
                        'kpi_template_metric_id' => $metric->id,
                        'target_value' => $staffTargets->get($metric->id) ?? $metric->default_target,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($inserted) {
                    $created++;
                    $this->recalculate((int) $result->id);
                }
                if (($inserted || $refreshExisting) && $result->status === 'draft' && in_array($profile->template_code, ['SALES-REP-V1', 'DRIVER-V1', 'OFFICE-STAFF-V1'], true)) {
                    $this->syncAutomaticActuals((int) $result->id, $profile->template_code);
                    $refreshed++;
                }
            }

            return ['eligible' => $profiles->count(), 'created' => $created, 'refreshed' => $refreshed];
        });
    }

    public function show(Request $request, int $id)
    {
        $this->authorizePermission($request, 'office.kpi.view');
        $result = DB::table('kpi_results')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->where('kpi_results.id', $id)
            ->select('kpi_results.status', 'kpi_templates.code as template_code')
            ->first();
        abort_unless($result, 404);
        if ($result->status === 'draft') {
            if (in_array($result->template_code, ['SALES-REP-V1', 'DRIVER-V1', 'OFFICE-STAFF-V1'], true)) {
                $this->syncAutomaticActuals($id, $result->template_code);
            }
        }

        return ApiResponse::success('KPI review loaded.', $this->detailPayload($id));
    }

    public function update(Request $request, int $id)
    {
        $this->authorizePermission($request, 'office.kpi.manage');
        $result = DB::table('kpi_results')->where('id', $id)->first();
        abort_unless($result, 404);
        abort_unless($result->status === 'draft', 409, 'Only draft KPI reviews can be edited.');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:kpi_result_items,id'],
            'items.*.target_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.actual_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.manual_score' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($id, $validated) {
            DB::table('kpi_results')->where('id', $id)->update([
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);
            foreach ($validated['items'] as $item) {
                $storedItem = DB::table('kpi_result_items')
                    ->join('kpi_template_metrics', 'kpi_result_items.kpi_template_metric_id', '=', 'kpi_template_metrics.id')
                    ->where('kpi_result_items.id', $item['id'])
                    ->where('kpi_result_items.kpi_result_id', $id)
                    ->select('kpi_result_items.id', 'kpi_template_metrics.code', 'kpi_template_metrics.calculation_type')
                    ->first();
                abort_unless($storedItem, 422, 'A KPI item does not belong to this review.');
                $automaticMetric = $this->isAutomaticMetric($storedItem->code, $storedItem->calculation_type);
                DB::table('kpi_result_items')->where('id', $storedItem->id)->update([
                    'target_value' => $item['target_value'] ?? null,
                    'actual_value' => $automaticMetric ? DB::raw('actual_value') : ($item['actual_value'] ?? null),
                    'manual_score' => $item['manual_score'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'updated_at' => now(),
                ]);
            }
            $this->recalculate($id);
        });

        return ApiResponse::success('KPI draft saved.', $this->detailPayload($id));
    }

    public function refresh(Request $request, int $id)
    {
        $this->authorizePermission($request, 'office.kpi.manage');
        $result = DB::table('kpi_results')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->where('kpi_results.id', $id)
            ->select('kpi_results.id', 'kpi_results.status', 'kpi_templates.employee_type', 'kpi_templates.code as template_code')
            ->first();
        abort_unless($result, 404);
        abort_unless($result->status === 'draft', 409, 'Only draft KPI reviews can refresh source figures.');
        abort_unless(in_array($result->template_code, ['SALES-REP-V1', 'DRIVER-V1', 'OFFICE-STAFF-V1'], true), 422, 'Automatic refresh is not available for this KPI role.');
        $this->syncAutomaticActuals($id, $result->template_code);

        return ApiResponse::success(ucfirst($result->employee_type).' figures refreshed.', $this->detailPayload($id));
    }

    public function submit(Request $request, int $id)
    {
        $this->authorizePermission($request, 'office.kpi.manage');
        $result = DB::table('kpi_results')->where('id', $id)->first();
        abort_unless($result, 404);
        abort_unless($result->status === 'draft', 409, 'Only draft KPI reviews can be submitted.');

        $this->recalculate($id);
        $missing = DB::table('kpi_result_items')->where('kpi_result_id', $id)->whereNull('achievement_percent')->count();
        abort_if($missing > 0, 422, 'Complete every target, actual, and manager score before submitting.');

        DB::table('kpi_results')->where('id', $id)->update([
            'status' => 'submitted',
            'submitted_by' => $request->user()?->id,
            'submitted_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('KPI review submitted.', $this->detailPayload($id));
    }

    public function approve(Request $request, int $id)
    {
        $this->authorizePermission($request, 'office.kpi.approve');
        $result = DB::table('kpi_results')->where('id', $id)->first();
        abort_unless($result, 404);
        abort_unless($result->status === 'submitted', 409, 'Only submitted KPI reviews can be approved.');
        if ($request->user()?->role === 'Finance Manager') {
            abort_if($result->submitted_by === $request->user()->id, 409, 'The KPI review submitter cannot approve their own review.');
        }

        DB::table('kpi_results')->where('id', $id)->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('KPI review approved.', $this->detailPayload($id));
    }

    public function postBonus(Request $request, int $id)
    {
        $this->authorizePermission($request, 'office.kpi.approve');

        DB::transaction(function () use ($id, $request) {
            $result = DB::table('kpi_results')->where('id', $id)->lockForUpdate()->first();
            abort_unless($result, 404);
            abort_unless($result->status === 'approved', 409, 'Approve the KPI review before posting its bonus.');
            abort_if($result->payroll_adjustment_id, 409, 'This KPI bonus has already been posted to payroll.');

            $period = DB::table('kpi_periods')->where('id', $result->kpi_period_id)->first();
            abort_unless($period, 422, 'The KPI period is unavailable.');

            $reference = $this->resultReference($id, $period->month);
            $adjustmentId = DB::table('payroll_adjustments')->insertGetId([
                'employee_id' => $result->employee_id,
                'adjustment_type' => 'incentive',
                'title' => "KPI bonus · {$period->month}",
                'amount' => $result->bonus_amount,
                'effective_date' => $period->period_end,
                'status' => 'active',
                'created_by' => $request->user()?->id,
                'notes' => "{$reference} · Score ".rtrim(rtrim(number_format((float) $result->overall_score, 2, '.', ''), '0'), '.').'% · Approved monthly KPI bonus',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('kpi_results')->where('id', $id)->update([
                'payroll_adjustment_id' => $adjustmentId,
                'bonus_posted_by' => $request->user()?->id,
                'bonus_posted_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return ApiResponse::success('KPI bonus posted to payroll.', $this->detailPayload($id));
    }

    private function recalculate(int $resultId): void
    {
        $items = DB::table('kpi_result_items')
            ->join('kpi_template_metrics', 'kpi_result_items.kpi_template_metric_id', '=', 'kpi_template_metrics.id')
            ->where('kpi_result_items.kpi_result_id', $resultId)
            ->select('kpi_result_items.*', 'kpi_template_metrics.calculation_type', 'kpi_template_metrics.weight')
            ->get();
        $overall = 0.0;
        foreach ($items as $item) {
            $achievement = null;
            if ($item->calculation_type === 'manual') {
                $achievement = $item->manual_score === null ? null : (float) $item->manual_score;
            } elseif ($item->target_value !== null && (float) $item->target_value > 0 && $item->actual_value !== null) {
                $target = (float) $item->target_value;
                $actual = (float) $item->actual_value;
                $achievement = $item->calculation_type === 'lower'
                    ? ($actual <= $target ? 100 : min(($target / max($actual, 0.0001)) * 100, 100))
                    : min(($actual / $target) * 100, 100);
            }
            $weighted = $achievement === null ? null : $achievement * (float) $item->weight / 100;
            $overall += $weighted ?? 0;
            DB::table('kpi_result_items')->where('id', $item->id)->update([
                'achievement_percent' => $achievement === null ? null : round($achievement, 2),
                'weighted_score' => $weighted === null ? null : round($weighted, 2),
                'updated_at' => now(),
            ]);
        }

        $result = DB::table('kpi_results')->where('id', $resultId)->first();
        $payout = DB::table('kpi_bonus_rules')
            ->where('kpi_template_id', $result->kpi_template_id)
            ->where('minimum_score', '<=', $overall)
            ->where(function ($query) use ($overall) {
                $query->whereNull('maximum_score')->orWhere('maximum_score', '>', $overall);
            })
            ->orderByDesc('minimum_score')
            ->value('payout_percent') ?? 0;
        DB::table('kpi_results')->where('id', $resultId)->update([
            'overall_score' => round($overall, 2),
            'bonus_amount' => round((float) $result->target_bonus * (float) $payout / 100, 2),
            'updated_at' => now(),
        ]);
    }

    private function syncSalesActuals(int $resultId): void
    {
        $result = DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->where('kpi_results.id', $resultId)
            ->where('kpi_templates.code', 'SALES-REP-V1')
            ->select('kpi_results.employee_id', 'kpi_periods.period_start', 'kpi_periods.period_end')
            ->first();
        abort_unless($result, 422, 'Sales KPI review not found.');

        $employeeId = (int) $result->employee_id;
        $start = Carbon::parse($result->period_start)->startOfDay();
        $end = Carbon::parse($result->period_end)->endOfDay();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $invoiceQuery = DB::table('invoices')
            ->join('orders', 'invoices.order_id', '=', 'orders.id')
            ->join('users', 'orders.created_by', '=', 'users.id')
            ->where('users.employee_id', $employeeId)
            ->whereIn('invoices.status', ['issued', 'delivered', 'partially_delivered'])
            ->whereBetween('invoices.invoice_date', [$startDate, $endDate]);
        $invoiceCount = (clone $invoiceQuery)->count('invoices.id');
        $grossSales = (float) (clone $invoiceQuery)->sum('invoices.total');

        $returnQuery = DB::table('orders as returns')
            ->join('orders as original_orders', 'returns.original_order_id', '=', 'original_orders.id')
            ->join('users', 'original_orders.created_by', '=', 'users.id')
            ->where('users.employee_id', $employeeId)
            ->where('returns.status', 'confirmed')
            ->whereBetween('returns.order_date', [$startDate, $endDate]);
        $returnCount = (clone $returnQuery)->count('returns.id');
        $returnValue = (float) (clone $returnQuery)->sum('returns.total');
        $salesTarget = DB::table('sales_targets')
            ->where('employee_id', $employeeId)
            ->whereDate('target_month', $startDate)
            ->value('target_amount');
        $this->updateSourceMetric($resultId, 'SAL-NET-SALES', max($grossSales - $returnValue, 0), $invoiceCount + $returnCount, "{$invoiceCount} eligible invoice(s), {$returnCount} confirmed return(s)", $salesTarget);

        $newCustomerCount = DB::table('customers')
            ->join('users', 'customers.created_by', '=', 'users.id')
            ->where('users.employee_id', $employeeId)
            ->where('customers.is_active', true)
            ->whereNull('customers.deleted_at')
            ->whereBetween('customers.created_at', [$start, $end])
            ->count('customers.id');
        $this->updateSourceMetric($resultId, 'SAL-NEW-CUSTOMER', $newCustomerCount, $newCustomerCount, "{$newCustomerCount} active customer registration(s)");

        $visitCount = DB::table('sales_route_visits')
            ->where('employee_id', $employeeId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereBetween('visit_date', [$startDate, $endDate])
            ->count('id');
        $this->updateSourceMetric($resultId, 'SAL-VISIT', $visitCount, $visitCount, "{$visitCount} completed customer visit(s)");

        $collectionQuery = DB::table('collections')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereBetween('collection_date', [$startDate, $endDate]);
        $collectionCount = (clone $collectionQuery)->count('id');
        $collectionAmount = (float) (clone $collectionQuery)->sum('amount');
        $this->updateSourceMetric($resultId, 'SAL-COLLECTION', $collectionAmount, $collectionCount, "{$collectionCount} approved collection(s)");

        $attendanceDays = DB::table('attendance_records')
            ->where('employee_id', $employeeId)
            ->where('status', 'accepted')
            ->whereBetween('attendance_at', [$start, $end])
            ->distinct()
            ->count(DB::raw('DATE(attendance_at)'));
        $this->updateSourceMetric($resultId, 'SAL-ATTENDANCE', $attendanceDays, $attendanceDays, "{$attendanceDays} accepted attendance day(s)");

        $this->recalculate($resultId);
    }

    private function syncDriverActuals(int $resultId): void
    {
        $result = DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->where('kpi_results.id', $resultId)
            ->where('kpi_templates.code', 'DRIVER-V1')
            ->select('kpi_results.employee_id', 'kpi_periods.period_start', 'kpi_periods.period_end')
            ->first();
        abort_unless($result, 422, 'Driver KPI review not found.');

        $employeeId = (int) $result->employee_id;
        $start = Carbon::parse($result->period_start)->startOfDay();
        $end = Carbon::parse($result->period_end)->endOfDay();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $attendanceDays = DB::table('attendance_records')
            ->where('employee_id', $employeeId)
            ->where('status', 'accepted')
            ->whereBetween('attendance_at', [$start, $end])
            ->distinct()
            ->count(DB::raw('DATE(attendance_at)'));
        $this->updateSourceMetric($resultId, 'DRV-ATTENDANCE', $attendanceDays, $attendanceDays, "{$attendanceDays} accepted attendance day(s)");

        $assignedQuery = DB::table('deliveries')
            ->where('driver_id', $employeeId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('planned_date', [$startDate, $endDate]);
        $assignedStops = (clone $assignedQuery)->count('id');
        $completedQuery = (clone $assignedQuery)->whereIn('status', ['delivered', 'partially_delivered']);
        $completedStops = (clone $completedQuery)->count('id');
        $deliveredQuantity = (float) (clone $completedQuery)->sum('delivered_quantity');
        $quantityLabel = rtrim(rtrim(number_format($deliveredQuantity, 2, '.', ''), '0'), '.');
        $this->updateSourceMetric(
            $resultId,
            'DRV-COMPLETION',
            $completedStops,
            $assignedStops,
            "{$completedStops} of {$assignedStops} assigned stop(s) completed · {$quantityLabel} unit(s) delivered",
            $assignedStops
        );

        $this->recalculate($resultId);
    }

    private function syncOfficeActuals(int $resultId): void
    {
        $result = DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->where('kpi_results.id', $resultId)
            ->where('kpi_templates.code', 'OFFICE-STAFF-V1')
            ->select('kpi_results.employee_id', 'kpi_periods.period_start', 'kpi_periods.period_end')
            ->first();
        abort_unless($result, 422, 'Office KPI review not found.');

        $start = Carbon::parse($result->period_start)->startOfDay();
        $end = Carbon::parse($result->period_end)->endOfDay();
        $attendanceDays = DB::table('attendance_records')
            ->where('employee_id', $result->employee_id)
            ->where('status', 'accepted')
            ->whereBetween('attendance_at', [$start, $end])
            ->distinct()
            ->count(DB::raw('DATE(attendance_at)'));
        $this->updateSourceMetric($resultId, 'OFF-ATTENDANCE', $attendanceDays, $attendanceDays, "{$attendanceDays} accepted attendance day(s)");
        $this->recalculate($resultId);
    }

    private function syncAutomaticActuals(int $resultId, string $templateCode): void
    {
        match ($templateCode) {
            'SALES-REP-V1' => $this->syncSalesActuals($resultId),
            'DRIVER-V1' => $this->syncDriverActuals($resultId),
            'OFFICE-STAFF-V1' => $this->syncOfficeActuals($resultId),
            default => null,
        };
    }

    private function updateSourceMetric(int $resultId, string $metricCode, float|int $actual, int $sourceCount, string $sourceNote, mixed $target = null): void
    {
        $metricId = DB::table('kpi_template_metrics')->where('code', $metricCode)->value('id');
        if (! $metricId) {
            return;
        }

        $values = [
            'actual_value' => $actual,
            'source_count' => $sourceCount,
            'source_note' => $sourceNote,
            'synced_at' => now(),
            'updated_at' => now(),
        ];
        if ($target !== null) {
            $values['target_value'] = $target;
        }
        DB::table('kpi_result_items')
            ->where('kpi_result_id', $resultId)
            ->where('kpi_template_metric_id', $metricId)
            ->update($values);
    }

    private function detailPayload(int $id): array
    {
        $result = DB::table('kpi_results')
            ->join('kpi_periods', 'kpi_results.kpi_period_id', '=', 'kpi_periods.id')
            ->join('kpi_templates', 'kpi_results.kpi_template_id', '=', 'kpi_templates.id')
            ->join('employees', 'kpi_results.employee_id', '=', 'employees.id')
            ->where('kpi_results.id', $id)
            ->select('kpi_results.*', 'kpi_periods.month', 'kpi_templates.id as template_id', 'kpi_templates.code as template_code', 'kpi_templates.name as template_name', 'kpi_templates.employee_type', 'employees.code as employee_code', 'employees.name as employee_name')
            ->first();
        abort_unless($result, 404);

        $items = DB::table('kpi_result_items')
            ->join('kpi_template_metrics', 'kpi_result_items.kpi_template_metric_id', '=', 'kpi_template_metrics.id')
            ->where('kpi_result_items.kpi_result_id', $id)
            ->select('kpi_result_items.*', 'kpi_template_metrics.code', 'kpi_template_metrics.name', 'kpi_template_metrics.calculation_type', 'kpi_template_metrics.unit', 'kpi_template_metrics.weight')
            ->orderBy('kpi_template_metrics.sort_order')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'calculation_type' => $item->calculation_type,
                'unit' => $item->unit,
                'weight' => (float) $item->weight,
                'target_value' => $item->target_value === null ? null : (float) $item->target_value,
                'actual_value' => $item->actual_value === null ? null : (float) $item->actual_value,
                'manual_score' => $item->manual_score === null ? null : (float) $item->manual_score,
                'achievement_percent' => $item->achievement_percent === null ? null : (float) $item->achievement_percent,
                'weighted_score' => $item->weighted_score === null ? null : (float) $item->weighted_score,
                'is_automatic' => $this->isAutomaticMetric($item->code, $item->calculation_type),
                'source_count' => (int) $item->source_count,
                'source_note' => $item->source_note,
                'synced_at' => $item->synced_at,
                'notes' => $item->notes,
            ]);

        return ['result' => $this->resultPayload($result), 'items' => $items];
    }

    private function resultPayload($result): array
    {
        return [
            'id' => $result->id,
            'reference' => $this->resultReference((int) $result->id, $result->month),
            'month' => $result->month,
            'employee_id' => $result->employee_id,
            'employee_code' => $result->employee_code,
            'employee_name' => $result->employee_name,
            'employee_type' => $result->employee_type,
            'template_id' => (int) $result->template_id,
            'template_code' => $result->template_code,
            'template_name' => $result->template_name,
            'status' => $result->status,
            'overall_score' => (float) $result->overall_score,
            'target_bonus' => (float) $result->target_bonus,
            'bonus_amount' => (float) $result->bonus_amount,
            'payroll_adjustment_id' => $result->payroll_adjustment_id ? (int) $result->payroll_adjustment_id : null,
            'payroll_adjustment_reference' => $result->payroll_adjustment_id ? 'ADJ-'.str_pad((string) $result->payroll_adjustment_id, 6, '0', STR_PAD_LEFT) : null,
            'bonus_posted_at' => $result->bonus_posted_at,
            'notes' => $result->notes,
            'submitted_at' => $result->submitted_at,
            'approved_at' => $result->approved_at,
            'updated_at' => $result->updated_at,
        ];
    }

    private function resultReference(int $id, string $month): string
    {
        return 'KPI-'.str_replace('-', '', $month).'-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    private function metricPayload($metric): array
    {
        return [
            'id' => $metric->id,
            'code' => $metric->code,
            'name' => $metric->name,
            'calculation_type' => $metric->calculation_type,
            'unit' => $metric->unit,
            'weight' => (float) $metric->weight,
            'default_target' => $metric->default_target === null ? null : (float) $metric->default_target,
        ];
    }

    private function staffTargetPayload($employee): array
    {
        $metrics = collect();
        if ($employee->profile_id) {
            $savedTargets = DB::table('kpi_staff_target_items')
                ->where('kpi_staff_profile_id', $employee->profile_id)
                ->pluck('target_value', 'kpi_template_metric_id');
            $metrics = DB::table('kpi_template_metrics')
                ->where('kpi_template_id', $employee->template_id)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($metric) use ($savedTargets) {
                    $payload = $this->metricPayload($metric);
                    $saved = $savedTargets->get($metric->id);
                    $payload['target_value'] = $saved === null
                        ? $payload['default_target']
                        : (float) $saved;

                    return $payload;
                });
        }

        $targetMetrics = $metrics->where('calculation_type', '!=', 'manual');

        return [
            'id' => (int) $employee->id,
            'code' => $employee->code,
            'name' => $employee->name,
            'employee_type' => $employee->employee_type,
            'profile_id' => $employee->profile_id ? (int) $employee->profile_id : null,
            'template_id' => $employee->template_id ? (int) $employee->template_id : null,
            'template_code' => $employee->template_code,
            'template_name' => $employee->template_name,
            'target_bonus' => $employee->target_bonus === null ? null : (float) $employee->target_bonus,
            'configured_targets' => $targetMetrics->whereNotNull('target_value')->count(),
            'total_targets' => $targetMetrics->count(),
            'targets' => $metrics->values(),
        ];
    }

    private function isAutomaticMetric(string $code, string $calculationType): bool
    {
        if ($calculationType === 'manual') {
            return false;
        }

        return str_starts_with($code, 'SAL-')
            || in_array($code, ['DRV-ATTENDANCE', 'DRV-COMPLETION', 'OFF-ATTENDANCE'], true);
    }

    private function authorizePermission(Request $request, string|array $permission): void
    {
        abort_unless(array_intersect((array) $permission, AppAccess::permissionsForRole($request->user()?->role)), 403);
    }
}
