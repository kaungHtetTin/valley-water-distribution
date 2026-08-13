<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('data.user.permissions.0', 'driver.confirm.update');

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
}
