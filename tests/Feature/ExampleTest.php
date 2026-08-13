<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_the_office_app()
    {
        $response = $this->get('/');

        $response->assertRedirect('/office');
    }

    public function test_phase_zero_app_routes_are_available()
    {
        foreach (['/office', '/client', '/sales', '/driver'] as $route) {
            $this->get($route)->assertOk();
        }
    }
}
