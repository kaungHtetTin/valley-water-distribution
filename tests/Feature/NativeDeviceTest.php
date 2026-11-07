<?php

namespace Tests\Feature;

use App\Models\NativeDevice;
use App\Models\User;
use App\Services\FirebasePushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NativeDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(\Database\Seeders\DemoSeeder::class);
    }

    public function test_device_registration_is_scoped_to_the_users_apps_and_credentials_are_not_audited(): void
    {
        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $installation = (string) Str::uuid();
        $payload = ['installation_id' => $installation, 'app' => 'driver', 'fcm_token' => 'private-fcm-token'];
        $this->postJson('/api/native/devices', $payload)->assertForbidden();
        $this->postJson('/api/native/devices', array_replace($payload, ['app' => 'sales']))->assertOk()
            ->assertJsonPath('data.tracking_token', null);
        $device = NativeDevice::firstOrFail();
        $this->assertSame('private-fcm-token', $device->fcm_token);
        $this->assertNotSame('private-fcm-token', DB::table('native_devices')->value('fcm_token'));
        $this->assertFalse(DB::table('audit_logs')->where('changes', 'like', '%private-fcm-token%')->exists());
        $this->assertFalse(DB::table('audit_logs')->where('path', 'like', '%native/devices%')->exists());
    }

    public function test_tracking_uses_only_the_authenticated_drivers_active_trip_and_cannot_authenticate_business_apis(): void
    {
        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $this->actingAs($driver);
        $response = $this->postJson('/api/native/devices', ['installation_id' => (string) Str::uuid(), 'app' => 'driver'])->assertOk();
        $token = $response->json('data.tracking_token');
        $this->assertSame(hash('sha256', $token), NativeDevice::firstOrFail()->tracking_token_hash);
        $delivery = DB::table('deliveries')->where('driver_id', $driver->employee_id)->first();
        DB::table('deliveries')->where('id', $delivery->id)->update(['status' => 'on_route']);
        if ($delivery->trip_id) DB::table('delivery_trips')->where('id', $delivery->trip_id)->update(['status' => 'on_route']);
        auth()->forgetGuards();
        $this->withToken($token)->postJson('/api/native/tracking', [
            'latitude' => 20.78, 'longitude' => 97.04, 'accuracy_m' => 12,
            'driver_id' => 9999, 'delivery_id' => 9999,
        ])->assertCreated();
        $this->assertDatabaseHas('delivery_locations', ['driver_id' => $driver->employee_id, 'latitude' => 20.78]);
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/master-data/employees')->assertUnauthorized();
    }

    public function test_expired_and_revoked_credentials_are_rejected_and_no_trip_stores_no_location(): void
    {
        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $this->actingAs($driver);
        $token = $this->postJson('/api/native/devices', ['installation_id' => (string) Str::uuid(), 'app' => 'driver'])->json('data.tracking_token');
        DB::table('deliveries')->where('driver_id', $driver->employee_id)->update(['status' => 'delivered']);
        DB::table('delivery_trips')->where('driver_id', $driver->employee_id)->update(['status' => 'delivered']);
        $this->withToken($token)->postJson('/api/native/tracking')->assertOk()->assertJsonPath('data.tracking', false);
        NativeDevice::query()->update(['tracking_expires_at' => now()->subMinute()]);
        $this->withToken($token)->postJson('/api/native/tracking')->assertUnauthorized();
        NativeDevice::query()->update(['tracking_expires_at' => now()->addDay()]);
        DB::table('employees')->where('id', $driver->employee_id)->update(['is_active' => false]);
        $this->withToken($token)->postJson('/api/native/tracking')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_sessions_devices(): void
    {
        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $this->actingAs($driver);
        $this->postJson('/api/native/devices', ['installation_id' => (string) Str::uuid(), 'app' => 'driver'])->assertOk();
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId())->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('native_devices', 0);
    }

    public function test_push_dispatch_deduplicates_alerts_and_discards_devices_after_access_is_removed(): void
    {
        $driver = User::where('email', 'driver@valley.test')->firstOrFail();
        $this->actingAs($driver)->postJson('/api/native/devices', [
            'installation_id' => (string) Str::uuid(), 'app' => 'driver', 'fcm_token' => 'test-token',
        ])->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'valley-fcm-');
        config(['native.firebase_credentials' => $file]);
        $this->mock(FirebasePushService::class)->shouldReceive('send')->once()
            ->withArgs(fn ($device, $alert) => $device->user_id === $driver->id && $alert['id'] === 'driver-tasks');
        try {
            $this->artisan('valley:push-alerts')->assertSuccessful();
            $this->artisan('valley:push-alerts')->assertSuccessful();
            DB::table('employees')->where('id', $driver->employee_id)->update(['is_active' => false]);
            $this->artisan('valley:push-alerts')->assertSuccessful();
            $this->assertDatabaseCount('native_devices', 0);
        } finally { unlink($file); }
    }
}
