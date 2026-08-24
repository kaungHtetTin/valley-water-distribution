<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicAttendanceController extends Controller
{
    public function show(string $token)
    {
        $location = AttendanceLocation::query()->where('public_token', $token)->first();
        abort_unless($location, 404, 'Attendance QR token is invalid.');

        return ApiResponse::success('Attendance location loaded.', [
            'location' => [
                'name' => $location->name,
                'address' => $location->address,
                'allowed_radius_m' => $location->allowed_radius_m,
                'is_active' => $location->is_active,
            ],
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $validated = $request->validate([
            'employee_code' => ['required', 'string', 'max:30'],
            'gps_denied' => ['sometimes', 'boolean'],
            'latitude' => ['required_unless:gps_denied,true', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_unless:gps_denied,true', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        $location = AttendanceLocation::query()->where('public_token', $token)->first();
        $employeeCode = strtoupper(trim($validated['employee_code']));
        $employee = DB::table('employees')->whereRaw('UPPER(code) = ?', [$employeeCode])->where('is_active', true)->first();
        $reason = null;
        $distance = null;

        if (! $location) {
            $reason = 'invalid_qr_token';
        } elseif (! $location->is_active) {
            $reason = 'inactive_qr_token';
        } elseif (! $employee) {
            $reason = 'invalid_employee_id';
        } elseif ($request->boolean('gps_denied')) {
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
            'attendance_location_id' => $location?->id,
            'employee_id' => $employee?->id,
            'entered_employee_code' => $employeeCode,
            'submitted_token' => $token,
            'attendance_at' => now(),
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'distance_m' => $distance === null ? null : round($distance, 2),
            'status' => $reason ? 'rejected' : 'accepted',
            'rejection_reason' => $reason,
        ]);

        return ApiResponse::success($reason ? 'Attendance was rejected.' : 'Attendance recorded successfully.', [
            'result' => [
                'record_id' => $record->id,
                'status' => $record->status,
                'rejection_reason' => $record->rejection_reason,
                'distance_m' => $record->distance_m,
                'allowed_radius_m' => $location?->allowed_radius_m,
                'employee_name' => $employee?->name,
                'location_name' => $location?->name,
                'attendance_at' => $record->attendance_at,
            ],
        ], 201);
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
