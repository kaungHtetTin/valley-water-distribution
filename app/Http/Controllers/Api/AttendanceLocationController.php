<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AttendanceLocationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission($request, 'office.attendance.view');
        $query = AttendanceLocation::query();

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $query->latest('id')->paginate(min(max((int) $request->query('per_page', 10), 1), 100));

        return ApiResponse::success('Attendance locations loaded.', [
            'items' => collect($paginator->items())->map(fn ($location) => $this->payload($location)),
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
        $this->authorizePermission($request, 'office.attendance.manage');
        $validated = $request->validate($this->rules());
        $validated['code'] = $validated['code'] ?: 'LOC-'.Str::upper(Str::random(8));
        $validated['public_token'] = Str::random(48);
        $validated['is_active'] = $validated['is_active'] ?? true;
        $location = AttendanceLocation::create($validated);

        return ApiResponse::success('Attendance location created.', [
            'location' => $this->payload($location),
        ], 201);
    }

    public function show(Request $request, AttendanceLocation $attendanceLocation)
    {
        $this->authorizePermission($request, 'office.attendance.view');

        return ApiResponse::success('Attendance location loaded.', [
            'location' => $this->payload($attendanceLocation),
        ]);
    }

    public function update(Request $request, AttendanceLocation $attendanceLocation)
    {
        $this->authorizePermission($request, 'office.attendance.manage');
        $validated = $request->validate($this->rules($attendanceLocation->id));
        $validated['code'] = $validated['code'] ?: 'LOC-'.str_pad((string) $attendanceLocation->id, 4, '0', STR_PAD_LEFT);
        $attendanceLocation->update($validated);

        return ApiResponse::success('Attendance location updated.', [
            'location' => $this->payload($attendanceLocation->fresh()),
        ]);
    }

    public function destroy(Request $request, AttendanceLocation $attendanceLocation)
    {
        $this->authorizePermission($request, 'office.attendance.manage');

        if (DB::table('attendance_records')->where('attendance_location_id', $attendanceLocation->id)->exists()) {
            return ApiResponse::error(
                'Attendance location has records and cannot be deleted.',
                ['location' => ['Set the location to inactive instead.']],
                409
            );
        }

        $attendanceLocation->delete();

        return ApiResponse::success('Attendance location deleted.');
    }

    public function rotateToken(Request $request, AttendanceLocation $attendanceLocation)
    {
        $this->authorizePermission($request, 'office.attendance.manage');
        $attendanceLocation->update(['public_token' => Str::random(48)]);

        return ApiResponse::success('Attendance QR token rotated.', [
            'location' => $this->payload($attendanceLocation->fresh()),
        ]);
    }

    private function rules(?int $id = null): array
    {
        return [
            'code' => ['nullable', 'string', 'max:30', Rule::unique('attendance_locations', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'allowed_radius_m' => ['required', 'integer', 'min:1', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function payload(AttendanceLocation $location): array
    {
        return $location->toArray() + [
            'public_url' => url('/attendance/'.$location->public_token),
        ];
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(in_array($permission, AppAccess::permissionsForRole($request->user()?->role), true), 403);
    }
}
