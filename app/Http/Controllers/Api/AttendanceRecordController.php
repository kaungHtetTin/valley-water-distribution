<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceRecordController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission($request);
        $query = DB::table('attendance_records')
            ->leftJoin('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->leftJoin('attendance_locations', 'attendance_records.attendance_location_id', '=', 'attendance_locations.id')
            ->select('attendance_records.*', 'employees.name as employee_name', 'employees.code as employee_code', 'attendance_locations.name as location_name');

        foreach (['employee_id', 'attendance_location_id', 'status', 'rejection_reason'] as $filter) {
            if ($request->filled($filter)) {
                $query->where('attendance_records.'.$filter, $request->query($filter));
            }
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('attendance_records.entered_employee_code', 'like', "%{$search}%")
                    ->orWhere('employees.name', 'like', "%{$search}%")
                    ->orWhere('employees.code', 'like', "%{$search}%")
                    ->orWhere('attendance_locations.name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('attendance_records.attendance_at', $request->query('date'));
        }

        $paginator = $query->orderByDesc('attendance_records.attendance_at')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Attendance records loaded.', [
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $this->authorizePermission($request);
        $record = DB::table('attendance_records')
            ->leftJoin('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->leftJoin('attendance_locations', 'attendance_records.attendance_location_id', '=', 'attendance_locations.id')
            ->where('attendance_records.id', $id)
            ->select('attendance_records.*', 'employees.name as employee_name', 'employees.code as employee_code', 'attendance_locations.name as location_name')
            ->first();
        abort_unless($record, 404);

        return ApiResponse::success('Attendance record loaded.', ['record' => $record]);
    }

    public function summary(Request $request)
    {
        $this->authorizePermission($request);

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'employee_type' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        [$start, $end] = $this->summaryPeriod($validated);
        $query = DB::table('employees')
            ->leftJoin('attendance_records', function ($join) use ($start, $end) {
                $join->on('employees.id', '=', 'attendance_records.employee_id')
                    ->whereBetween('attendance_records.attendance_at', [$start, $end]);
            })
            ->select(
                'employees.id as employee_id',
                'employees.code as employee_code',
                'employees.name as employee_name',
                'employees.employee_type',
                DB::raw('COUNT(attendance_records.id) as total_records'),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count"),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count"),
                DB::raw("SUM(CASE WHEN attendance_records.rejection_reason = 'gps_denied' THEN 1 ELSE 0 END) as gps_denied_count"),
                DB::raw("SUM(CASE WHEN attendance_records.rejection_reason = 'outside_allowed_radius' THEN 1 ELSE 0 END) as outside_radius_count"),
                DB::raw('MIN(attendance_records.attendance_at) as first_attendance_at'),
                DB::raw('MAX(attendance_records.attendance_at) as last_attendance_at')
            )
            ->where('employees.is_active', true)
            ->groupBy('employees.id', 'employees.code', 'employees.name', 'employees.employee_type');

        if (! empty($validated['employee_type'])) {
            $query->where('employees.employee_type', $validated['employee_type']);
        }

        if ($search = trim((string) ($validated['search'] ?? ''))) {
            $query->where(function ($query) use ($search) {
                $query->where('employees.name', 'like', "%{$search}%")
                    ->orWhere('employees.code', 'like', "%{$search}%")
                    ->orWhere('employees.employee_type', 'like', "%{$search}%");
            });
        }

        $totals = (clone $query)->get()->reduce(function ($carry, $row) {
            $carry['employees']++;
            $carry['records'] += (int) $row->total_records;
            $carry['accepted'] += (int) $row->accepted_count;
            $carry['rejected'] += (int) $row->rejected_count;

            return $carry;
        }, ['employees' => 0, 'records' => 0, 'accepted' => 0, 'rejected' => 0]);

        $paginator = $query->orderBy('employees.name')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return ApiResponse::success('Attendance summary loaded.', [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'totals' => $totals,
            'items' => collect($paginator->items())->map(fn ($row) => [
                'employee_id' => $row->employee_id,
                'employee_code' => $row->employee_code,
                'employee_name' => $row->employee_name,
                'employee_type' => $row->employee_type,
                'total_records' => (int) $row->total_records,
                'accepted_count' => (int) $row->accepted_count,
                'rejected_count' => (int) $row->rejected_count,
                'gps_denied_count' => (int) $row->gps_denied_count,
                'outside_radius_count' => (int) $row->outside_radius_count,
                'first_attendance_at' => $row->first_attendance_at,
                'last_attendance_at' => $row->last_attendance_at,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function summaryPeriod(array $filters): array
    {
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $start = Carbon::parse($filters['date_from'] ?? $filters['date_to'])->startOfDay();
            $end = Carbon::parse($filters['date_to'] ?? $filters['date_from'])->endOfDay();

            return [$start, $end];
        }

        $month = Carbon::createFromFormat('Y-m', $filters['month'] ?? now()->format('Y-m'));

        return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
    }

    private function authorizePermission(Request $request): void
    {
        abort_unless(in_array('office.attendance.view', AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
