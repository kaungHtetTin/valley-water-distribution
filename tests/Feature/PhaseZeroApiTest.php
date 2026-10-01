<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseZeroApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_zero_endpoint_returns_seeded_apps_roles_and_users()
    {
        $this->seed();

        $response = $this->getJson('/api/phase-zero');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.apps.0', 'office')
            ->assertJsonPath('data.apps.1', 'client')
            ->assertJsonPath('data.apps.2', 'sales')
            ->assertJsonPath('data.apps.3', 'supervisor')
            ->assertJsonPath('data.apps.4', 'driver')
            ->assertJsonFragment(['name' => 'Owner', 'allowed_apps' => ['office']])
            ->assertJsonFragment(['email' => 'owner@valley.test']);
    }

    public function test_demo_login_validation_uses_standard_api_error_shape()
    {
        $response = $this->postJson('/api/phase-zero/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure([
                'ok',
                'message',
                'data',
                'errors' => ['email', 'password'],
            ]);
    }
}
