<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceCheckIn
{
    public static function record(array $attributes): AttendanceRecord
    {
        return DB::transaction(function () use ($attributes) {
            if ($attributes['status'] === 'rejected') {
                return AttendanceRecord::create($attributes);
            }

            // Serialize check-ins for the employee, including public QR submissions.
            $employee = DB::table('employees')->where('id', $attributes['employee_id'])->lockForUpdate()->first();
            $at = Carbon::parse($attributes['attendance_at'])->setTimezone('Asia/Yangon');
            $start = $at->copy()->startOfDay()->setTimezone(config('app.timezone'));
            $end = $at->copy()->endOfDay()->setTimezone(config('app.timezone'));
            $existing = AttendanceRecord::where('employee_id', $employee->id)
                ->whereIn('status', ['accepted', 'late'])->whereBetween('attendance_at', [$start, $end])->first();
            if ($existing) {
                return $existing;
            }

            $company = Company::query()->oldest('id')->first();
            $time = $company?->attendance_start_time ?? '08:00';
            $scheduled = $at->copy()->startOfDay()->setTimeFromTimeString($time);
            $minutes = max(0, (int) ceil(($at->getTimestamp() - $scheduled->getTimestamp()) / 60));
            $salary = (float) ($employee->base_salary ?? SalaryDefaults::forEmployeeType(
                $employee->employee_type, $company?->default_base_salaries ?? SalaryDefaults::AMOUNTS
            ));

            return AttendanceRecord::create(array_merge($attributes, [
                'status' => $minutes > 0 ? 'late' : 'accepted',
                'late_minutes' => $minutes,
                'late_fine' => round($salary / (8 * 60) * $minutes, 2),
                'salary_snapshot' => $salary,
                'start_time_snapshot' => $time,
            ]));
        });
    }
}
