<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\NativeDevice;
use App\Support\ApiResponse;
use App\Support\AppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NativeDeviceController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'installation_id' => ['required', 'uuid'],
            'app' => ['required', Rule::in(['sales', 'supervisor', 'driver'])],
            'fcm_token' => ['nullable', 'string', 'max:4096'],
        ]);
        abort_unless(AppAccess::canAccess($request->user(), $data['app']), 403);
        $trackingToken = $data['app'] === 'driver' ? Str::random(80) : null;
        $device = NativeDevice::firstOrNew([
            'installation_id' => $data['installation_id'], 'app' => $data['app'],
        ]);
        if ((int) $device->user_id !== (int) $request->user()->id) {
            $device->alert_state = null;
            $device->fcm_token = null;
        }
        $device->fill([
            'user_id' => $request->user()->id,
            'session_hash' => hash('sha256', $request->session()->getId()),
            'tracking_token_hash' => $trackingToken ? hash('sha256', $trackingToken) : null,
            'expires_at' => now()->addDays(30),
            'tracking_expires_at' => $trackingToken ? now()->addDay() : null,
        ]);
        if (array_key_exists('fcm_token', $data)) {
            $device->fcm_token = $data['fcm_token'];
        }
        $device->save();

        return ApiResponse::success('Native device registered.', [
            'tracking_token' => $trackingToken,
            'expires_at' => $device->expires_at->toIso8601String(),
            'push_configured' => filled(config('native.firebase_credentials')),
        ]);
    }

    public function destroy(Request $request, string $installationId)
    {
        NativeDevice::where('installation_id', $installationId)->where('user_id', $request->user()->id)->delete();

        return ApiResponse::success('Native device disconnected.');
    }

    // This bearer token can only submit GPS points. It cannot authenticate to business APIs.
    public function track(Request $request, DeliveryController $deliveries)
    {
        $token = $request->bearerToken();
        abort_unless(is_string($token) && strlen($token) === 80, 401);
        $device = NativeDevice::with('user')->where('tracking_token_hash', hash('sha256', $token))
            ->where('app', 'driver')->where('expires_at', '>', now())->where('tracking_expires_at', '>', now())->first();
        abort_unless($device && $device->user && AppAccess::canAccess($device->user, 'driver'), 401);
        abort_unless($device->user->employee_id, 403);
        abort_unless(in_array('driver.route.update', AppAccess::permissionsForRole($device->user->role), true), 403);
        $request->setUserResolver(fn () => $device->user);

        // Resolve the driver's active trip on the server; never accept a client-supplied driver or delivery ID.
        $deliveryId = DB::table('deliveries')->leftJoin('delivery_trips', 'deliveries.trip_id', '=', 'delivery_trips.id')
            ->where('deliveries.driver_id', $device->user->employee_id)
            ->whereRaw("COALESCE(delivery_trips.status, deliveries.status) = 'on_route'")
            ->orderByDesc('deliveries.id')->value('deliveries.id');
        if (! $deliveryId) {
            return ApiResponse::success('No active trip. Location was not stored.', ['tracking' => false]);
        }

        return $deliveries->storeLocation($request, Delivery::findOrFail($deliveryId));
    }
}
