<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustHosts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

class PhaseElevenHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_api_route_is_authenticated_or_explicitly_public_and_throttled_where_sensitive()
    {
        $public = [
            'api/auth/user', 'api/auth/login', 'api/auth/register', 'api/auth/google/redirect', 'api/auth/google/callback', 'api/auth/logout',
            'api/phase-zero', 'api/phase-zero/login',
            'api/public/attendance/{token}',
        ];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            if (in_array($route->uri(), $public, true)) {
                if (in_array($route->uri(), ['api/auth/login', 'api/auth/register', 'api/auth/google/redirect', 'api/auth/google/callback', 'api/phase-zero/login', 'api/public/attendance/{token}'], true)) {
                    $this->assertTrue(collect($middleware)->contains(fn ($item) => str_starts_with($item, 'throttle:')), "{$route->uri()} must be throttled");
                }

                continue;
            }
            $this->assertTrue(in_array('auth', $middleware, true) || in_array('auth:sanctum', $middleware, true), "{$route->uri()} is missing authentication");
        }

        $this->assertTrue(true);
    }

    public function test_security_headers_are_applied_and_demo_discovery_is_local_only()
    {
        $this->get('/office')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        config(['valley.demo_endpoints' => false]);
        $this->getJson('/api/phase-zero')->assertNotFound();
        config(['valley.demo_endpoints' => true]);
    }

    public function test_production_https_responses_send_hsts(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $request = Request::create('https://valley.example.com/office');
            $response = app(SecurityHeaders::class)->handle($request, fn () => response('OK'));

            $this->assertSame('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_untrusted_hosts_are_rejected(): void
    {
        config(['app.url' => 'https://valley.example.com']);
        Request::setTrustedHosts(array_filter(app(TrustHosts::class)->hosts()));

        try {
            $this->expectException(SuspiciousOperationException::class);
            Request::create('https://attacker.example/office')->getHost();
        } finally {
            Request::setTrustedHosts([]);
        }
    }

    public function test_demo_login_checks_the_password_instead_of_only_the_email()
    {
        $this->seed();

        $this->postJson('/api/phase-zero/login', ['email' => 'owner@valley.test', 'password' => 'incorrect-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/phase-zero/login', ['email' => 'owner@valley.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonMissing(['password']);
    }

    public function test_master_data_records_receive_audit_fields_and_are_soft_deleted()
    {
        $this->seed();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $this->actingAs($owner);

        $response = $this->postJson('/api/master-data/areas', [
            'code' => 'UAT-AREA',
            'name' => 'UAT Recovery Area',
            'description' => 'Created during the recoverable delete test.',
            'is_active' => true,
        ])->assertCreated();
        $id = $response->json('data.item.id');

        $this->assertDatabaseHas('areas', ['id' => $id, 'created_by' => $owner->id, 'updated_by' => $owner->id]);
        $this->deleteJson("/api/master-data/areas/{$id}")->assertOk();
        $this->assertNotNull(DB::table('areas')->where('id', $id)->value('deleted_at'));
        $this->getJson("/api/master-data/areas/{$id}")->assertNotFound();
        $this->getJson('/api/master-data/areas?search=UAT+Recovery')->assertJsonPath('data.meta.total', 0);
    }

    public function test_successful_mutations_are_recorded_in_the_audit_log()
    {
        $this->seed();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $this->actingAs($owner);

        $this->postJson('/api/uat/issues', [
            'module' => 'orders',
            'title' => 'Confirm touch target on compact phone',
            'steps' => 'Open Client Orders and place a two-line order.',
            'severity' => 'medium',
            'status' => 'open',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'app' => 'office',
            'action' => 'create',
            'method' => 'POST',
            'path' => '/api/uat/issues',
            'status_code' => 201,
        ]);
    }

    public function test_uat_workspace_tracks_findings_retests_and_permission_scope()
    {
        $this->seed();
        $owner = User::where('email', 'owner@valley.test')->firstOrFail();
        $this->actingAs($owner);

        $created = $this->postJson('/api/uat/issues', [
            'module' => 'delivery',
            'title' => 'GPS sharing field check',
            'steps' => 'Start route and share three location points.',
            'severity' => 'high',
            'status' => 'open',
        ])->assertCreated();
        $id = $created->json('data.item.id');

        $this->putJson("/api/uat/issues/{$id}", [
            'module' => 'delivery',
            'title' => 'GPS sharing field check',
            'steps' => 'Start route and share three location points.',
            'severity' => 'high',
            'status' => 'retest',
            'resolution' => 'Location interval and active-route scope verified.',
        ])->assertOk()->assertJsonPath('data.item.status', 'retest');

        $this->getJson('/api/uat/overview')->assertOk()->assertJsonPath('data.summary.open', 2)->assertJsonCount(11, 'data.modules');
        $this->getJson('/api/uat/issues?module=delivery&status=retest')->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->actingAs(User::where('email', 'client@valley.test')->firstOrFail());
        $this->getJson('/api/uat/overview')->assertForbidden();
        $this->postJson('/api/uat/issues', [])->assertForbidden();
    }

    public function test_backup_and_demo_reset_commands_have_safe_dry_runs()
    {
        $this->artisan('valley:backup', ['--dry-run' => true])->assertExitCode(0);
        $this->artisan('valley:demo-reset', ['--dry-run' => true])->assertExitCode(0);
    }
}
