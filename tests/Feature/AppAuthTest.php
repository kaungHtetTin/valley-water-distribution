<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppAccess;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AppAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_sign_in_to_their_allowed_app()
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'driver@valley.test',
            'password' => 'password',
            'app' => 'driver',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.user.email', 'driver@valley.test')
            ->assertJsonPath('data.user.default_app', 'driver')
            ->assertJsonPath('data.user.allowed_apps.0', 'driver')
            ->assertJsonFragment(['driver.attendance.view'])
            ->assertJsonFragment(['driver.confirm.update'])
            ->assertJsonFragment(['driver.collections.create'])
            ->assertJsonFragment(['driver.expenses.create']);

        $this->assertAuthenticated();
    }

    public function test_user_can_sign_in_with_their_phone_number(): void
    {
        $this->seed();
        $user = User::where('email', 'driver@valley.test')->firstOrFail();

        $this->postJson('/api/auth/login', [
            'email' => $user->phone,
            'password' => 'password',
            'app' => 'driver',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_duplicate_phone_number_cannot_select_an_ambiguous_account(): void
    {
        $this->seed();
        $user = User::where('email', 'driver@valley.test')->firstOrFail();
        User::factory()->create(['phone' => $user->phone]);

        $this->postJson('/api/auth/login', [
            'email' => $user->phone,
            'password' => 'password',
            'app' => 'driver',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_user_cannot_sign_in_to_the_wrong_app()
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'driver@valley.test',
            'password' => 'password',
            'app' => 'office',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonStructure([
                'ok',
                'message',
                'data',
                'errors' => ['app'],
            ]);

        $this->assertGuest();
    }

    public function test_active_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $this->seed();
        $user = User::where('email', 'driver@valley.test')->firstOrFail();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an active account matches that email, a password reset link has been sent.');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, '/reset-password/') && str_contains($url, rawurlencode($user->email));
        });
    }

    public function test_password_reset_request_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();
        $this->seed();

        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'unknown@valley.test'])
            ->assertOk()
            ->assertJsonPath('message', 'If an active account matches that email, a password reset link has been sent.');

        $this->assertSame([], $response->json('errors'));
        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $this->seed();
        $user = User::where('email', 'driver@valley.test')->firstOrFail();
        $token = Password::broker()->createToken($user);

        $this->actingAs($user)->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'New-secure-password-2026',
            'password_confirmation' => 'New-secure-password-2026',
        ])
            ->assertOk()
            ->assertJsonPath('data.redirect_to', url('/driver'));

        $this->assertTrue(Hash::check('New-secure-password-2026', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_use_a_password_reset_token(): void
    {
        $this->seed();
        $user = User::where('email', 'driver@valley.test')->firstOrFail();
        $token = Password::broker()->createToken($user);
        DB::table('employees')->where('id', $user->employee_id)->update(['is_active' => false]);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'New-secure-password-2026',
            'password_confirmation' => 'New-secure-password-2026',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_inactive_employee_cannot_sign_in(): void
    {
        $this->seed();
        $user = User::where('email', 'driver@valley.test')->firstOrFail();
        DB::table('employees')->where('id', $user->employee_id)->update(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'email' => 'driver@valley.test',
            'password' => 'password',
            'app' => 'driver',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.')
            ->assertJsonStructure(['errors' => ['email']]);

        $this->assertGuest();
    }

    public function test_inactive_employee_existing_session_is_terminated(): void
    {
        $this->seed();
        $user = User::where('email', 'sales@valley.test')->firstOrFail();
        DB::table('employees')->where('id', $user->employee_id)->update(['is_active' => false]);

        $this->actingAs($user)
            ->getJson('/api/auth/user')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.');

        $this->assertGuest();
    }

    public function test_inactive_customer_existing_session_is_terminated(): void
    {
        $this->seed();
        $user = User::where('email', 'client@valley.test')->firstOrFail();
        DB::table('customers')->where('id', $user->customer_id)->update(['is_active' => false]);

        $this->actingAs($user)
            ->getJson('/api/auth/user')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.');

        $this->assertGuest();
    }

    public function test_unlinked_owner_account_remains_active(): void
    {
        $this->seed();
        $user = User::where('email', 'owner@valley.test')->firstOrFail();

        $this->actingAs($user)
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.user.role', 'Owner');
    }

    public function test_disabling_a_role_terminates_existing_sessions(): void
    {
        $this->seed();
        $user = User::where('email', 'driver@valley.test')->firstOrFail();
        DB::table('roles')->where('name', 'Driver')->update(['is_active' => false]);

        $this->actingAs($user)
            ->getJson('/api/auth/user')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.');

        $this->assertGuest();
    }

    public function test_sales_access_matches_the_mobile_field_workflow()
    {
        $this->seed();

        $this->actingAs(User::where('email', 'sales@valley.test')->firstOrFail());
        $permissions = $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonFragment(['sales.orders.create'])
            ->assertJsonFragment(['sales.customers.create'])
            ->json('data.user.permissions');

        $this->assertNotContains('sales.deliveries.view', $permissions);
        $this->assertNotContains('sales.route.view', $permissions);
        $this->assertNotContains('sales.collections.view', $permissions);
        $this->assertNotContains('sales.collections.create', $permissions);
        $this->assertContains('sales.finance.view', $permissions);
        $this->assertContains('sales.expenses.view', $permissions);
        $this->assertContains('sales.expenses.create', $permissions);
        $this->assertContains('sales.attendance.view', $permissions);
        $this->assertContains('sales.payroll.view', $permissions);
    }

    public function test_current_user_endpoint_returns_role_access_payload()
    {
        $this->seed();

        $this->actingAs(User::where('email', 'client@valley.test')->first());

        $response = $this->getJson('/api/auth/user');

        $response
            ->assertOk()
            ->assertJsonPath('data.user.role', 'Customer')
            ->assertJsonPath('data.user.default_app', 'client')
            ->assertJsonPath('data.user.allowed_apps.0', 'client')
            ->assertJsonPath('data.user.permissions.0', 'client.deliveries.view');
    }

    public function test_logout_clears_the_session()
    {
        $this->actingAs(User::factory()->create([
            'role' => 'Office Staff',
            'locale' => 'en',
        ]));

        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertGuest();
    }

    public function test_user_can_persist_locale_preference()
    {
        $this->seed();
        $user = User::where('email', 'sales@valley.test')->firstOrFail();

        $this->actingAs($user)
            ->putJson('/api/auth/preferences', ['locale' => 'my'])
            ->assertOk()
            ->assertJsonPath('data.user.locale', 'my');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'my']);
    }

    public function test_user_can_update_profile_information()
    {
        $user = User::factory()->create(['email' => 'old@example.test']);

        $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => 'Updated Admin',
                'email' => 'updated@example.test',
                'phone' => '09 123 456 789',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Updated Admin')
            ->assertJsonPath('data.user.email', 'updated@example.test')
            ->assertJsonPath('data.user.phone', '09 123 456 789');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.test',
        ]);
    }

    public function test_all_app_users_can_upload_profile_photos_without_a_storage_symlink()
    {
        $this->seed();
        $directory = storage_path('framework/testing/profile-photos');
        config([
            'uploads.profile_photos_path' => $directory,
            'uploads.profile_photos_url' => 'uploads/profile-photos',
        ]);
        File::deleteDirectory($directory);
        foreach (['owner@valley.test', 'office@valley.test', 'sales@valley.test', 'driver@valley.test', 'client@valley.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $response = $this->actingAs($user)->post('/api/auth/profile', [
                '_method' => 'PUT',
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_photo' => UploadedFile::fake()->createWithContent(
                    'profile-photo.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
                ),
            ]);

            $response->assertOk()->assertJsonPath('data.user.profile_photo_url', fn ($url) => str_contains($url, '/uploads/profile-photos/'));
            $filename = basename($user->fresh()->profile_photo_path);
            $this->assertTrue(File::isFile($directory.DIRECTORY_SEPARATOR.$filename));
        }
        File::deleteDirectory($directory);
    }

    public function test_profile_photo_endpoint_rejects_an_uncompressed_file_over_500_kb()
    {
        $this->seed();
        $user = User::where('email', 'sales@valley.test')->firstOrFail();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=').str_repeat("\0", 501 * 1024);

        $this->actingAs($user)->withHeader('Accept', 'application/json')->post('/api/auth/profile', [
            '_method' => 'PUT',
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => UploadedFile::fake()->createWithContent('uncompressed.png', $png),
        ])->assertUnprocessable()->assertJsonValidationErrors('profile_photo');
    }

    public function test_shared_profile_photo_is_served_only_to_its_authenticated_owner(): void
    {
        $this->seed();
        $directory = storage_path('framework/testing/shared-profile-photos');
        $filename = 'test-profile.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.$filename, $png);
        config(['uploads.profile_photos_path' => $directory]);

        try {
            $user = User::where('email', 'driver@valley.test')->firstOrFail();
            $user->update(['profile_photo_path' => 'uploads/profile-photos/'.$filename]);

            $response = $this->actingAs($user)
                ->get('/uploads/profile-photos/'.$filename)
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png');
            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));

            $this->actingAs(User::where('email', 'owner@valley.test')->firstOrFail())
                ->get('/uploads/profile-photos/'.$filename)
                ->assertNotFound();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_user_can_change_password_with_current_password()
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)
            ->putJson('/api/auth/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_change_rejects_an_incorrect_current_password()
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)
            ->putJson('/api/auth/password', [
                'current_password' => 'incorrect-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_custom_role_app_access_is_data_driven()
    {
        $this->seed();

        DB::table('roles')->insert([
            'name' => 'Field Supervisor',
            'guard_name' => 'web',
            'allowed_apps' => json_encode(['sales', 'driver']),
            'description' => 'Cross-app field supervisor.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(['sales', 'driver'], AppAccess::allowedAppsForRole('Field Supervisor'));
    }
}
