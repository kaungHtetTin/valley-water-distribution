<?php

namespace Tests\Feature;

use Tests\TestCase;

class PlatformFoundationTest extends TestCase
{
    public function test_root_redirects_to_the_office_application(): void
    {
        $this->get('/')->assertRedirect('office');
    }

    public function test_each_application_shell_is_served_by_the_spa_view(): void
    {
        foreach (['office', 'sales', 'driver', 'client'] as $application) {
            $response = $this->get("/{$application}");

            $response
                ->assertOk()
                ->assertSee('id="app"', false)
                ->assertSee('name="app-base-path"', false)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

            $this->assertNotEmpty($response->headers->get('X-Correlation-ID'));
        }
    }

    public function test_versioned_health_endpoint_reports_platform_context(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('api_version', 'v1')
            ->assertJsonPath('business_timezone', 'Asia/Yangon')
            ->assertJsonStructure(['service', 'environment', 'correlation_id', 'timestamp']);

        $this->assertSame(
            $response->json('correlation_id'),
            $response->headers->get('X-Correlation-ID'),
        );
    }
}
