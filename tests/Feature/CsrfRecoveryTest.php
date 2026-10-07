<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CsrfRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Laravel normally bypasses CSRF in tests; exercise real verification.
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken {
            protected function runningUnitTests() { return false; }
        });
        Route::middleware('web')->post('/csrf-test-action', fn (Request $request) => response()->json(['saved' => true]));
    }

    public function test_stale_token_is_rejected_and_current_token_allows_the_action(): void
    {
        $this->withSession(['_token' => 'current-token']);
        $this->postJson('/csrf-test-action', [], ['X-CSRF-TOKEN' => 'old-token'])->assertStatus(419);
        $response = $this->getJson('/api/auth/csrf')->assertOk()
            ->assertJsonPath('data.csrf_token', 'current-token')
            ->assertJsonPath('data.user_id', null);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->postJson('/csrf-test-action', [], ['X-CSRF-TOKEN' => $response->json('data.csrf_token')])
            ->assertOk()->assertJsonPath('saved', true);
    }

    public function test_refresh_does_not_rotate_token_and_app_shell_is_not_cached(): void
    {
        $this->withSession(['_token' => 'shared-token']);
        foreach (['office', 'sales', 'driver', 'client', 'supervisor'] as $app) {
            $response = $this->get('/'.$app)->assertOk();
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->getJson('/api/auth/csrf')->assertJsonPath('data.csrf_token', 'shared-token');
        }
    }
}
