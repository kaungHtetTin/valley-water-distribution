<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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
