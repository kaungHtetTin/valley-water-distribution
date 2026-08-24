<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UatController extends Controller
{
    private const MODULES = ['foundation', 'master_data', 'attendance', 'payroll', 'orders', 'stock', 'delivery', 'finance', 'vehicle', 'reports', 'dashboards'];

    public function overview(Request $request)
    {
        $this->authorizePermission($request, 'office.uat.view');
        $issueCounts = DB::table('uat_issues')->selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status');
        $openByModule = DB::table('uat_issues')->whereNotIn('status', ['resolved', 'closed'])->selectRaw('module, COUNT(*) total')->groupBy('module')->pluck('total', 'module');

        return ApiResponse::success('UAT readiness loaded.', [
            'summary' => [
                'modules' => count(self::MODULES),
                'open' => (int) (($issueCounts['open'] ?? 0) + ($issueCounts['in_progress'] ?? 0) + ($issueCounts['retest'] ?? 0)),
                'resolved' => (int) ($issueCounts['resolved'] ?? 0),
                'closed' => (int) ($issueCounts['closed'] ?? 0),
                'audit_events' => DB::table('audit_logs')->count(),
            ],
            'modules' => collect(self::MODULES)->map(fn ($module, $index) => [
                'phase' => $index === 0 ? 0 : $index,
                'module' => $module,
                'label' => str($module)->replace('_', ' ')->title()->toString(),
                'open_issues' => (int) ($openByModule[$module] ?? 0),
                'status' => ($openByModule[$module] ?? 0) > 0 ? 'attention' : 'ready',
            ]),
            'recent_audit' => $this->auditQuery()->limit(8)->get(),
            'test_accounts' => [
                ['app' => 'Office', 'email' => 'owner@valley.test', 'role' => 'Owner'],
                ['app' => 'Client', 'email' => 'client@valley.test', 'role' => 'Customer'],
                ['app' => 'Sales', 'email' => 'sales@valley.test', 'role' => 'Sales Representative'],
                ['app' => 'Driver', 'email' => 'driver@valley.test', 'role' => 'Driver'],
            ],
        ]);
    }

    public function issues(Request $request)
    {
        $this->authorizePermission($request, 'office.uat.view');
        $query = DB::table('uat_issues')->leftJoin('users as assignee', 'uat_issues.assigned_to', '=', 'assignee.id')->leftJoin('users as reporter', 'uat_issues.created_by', '=', 'reporter.id')->select(['uat_issues.*', 'assignee.name as assigned_name', 'reporter.name as reporter_name']);
        foreach (['module', 'severity', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where("uat_issues.{$filter}", $request->query($filter));
            }
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($query) => $query->where('uat_issues.code', 'like', "%{$search}%")->orWhere('uat_issues.title', 'like', "%{$search}%"));
        }
        $paginator = $query->orderByRaw("CASE uat_issues.severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")->orderByDesc('uat_issues.id')->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('UAT issues loaded.', [
            'items' => $paginator->items(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
            'options' => $this->options(),
        ]);
    }

    public function storeIssue(Request $request)
    {
        $this->authorizePermission($request, 'office.uat.manage');
        $validated = $this->validateIssue($request);
        $id = DB::table('uat_issues')->insertGetId($validated + [
            'code' => $this->nextCode(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('UAT issue recorded.', ['item' => DB::table('uat_issues')->find($id)], 201);
    }

    public function updateIssue(Request $request, int $id)
    {
        $this->authorizePermission($request, 'office.uat.manage');
        abort_unless(DB::table('uat_issues')->where('id', $id)->exists(), 404);
        $validated = $this->validateIssue($request);
        $validated['updated_by'] = $request->user()->id;
        $validated['updated_at'] = now();
        $validated['resolved_at'] = in_array($validated['status'], ['resolved', 'closed'], true) ? now() : null;
        DB::table('uat_issues')->where('id', $id)->update($validated);

        return ApiResponse::success('UAT issue updated.', ['item' => DB::table('uat_issues')->find($id)]);
    }

    public function auditLogs(Request $request)
    {
        $this->authorizePermission($request, 'office.uat.view');
        $query = $this->auditQuery();
        foreach (['app', 'action', 'entity_type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }
        $paginator = $query->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        return ApiResponse::success('Audit log loaded.', [
            'items' => $paginator->items(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ]);
    }

    private function validateIssue(Request $request): array
    {
        return $request->validate([
            'module' => ['required', Rule::in(self::MODULES)],
            'title' => ['required', 'string', 'max:200'],
            'steps' => ['nullable', 'string', 'max:5000'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['required', Rule::in(['open', 'in_progress', 'retest', 'resolved', 'closed'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function options(): array
    {
        return [
            'modules' => collect(self::MODULES)->map(fn ($value) => ['value' => $value, 'label' => str($value)->replace('_', ' ')->title()->toString()]),
            'users' => DB::table('users')->whereIn('role', ['Owner', 'Office Staff'])->orderBy('name')->get(['id', 'name', 'role']),
        ];
    }

    private function auditQuery()
    {
        return DB::table('audit_logs')->orderByDesc('id');
    }

    private function nextCode(): string
    {
        return 'UAT-'.now()->format('Ym').'-'.str_pad((string) (DB::table('uat_issues')->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
