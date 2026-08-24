<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
