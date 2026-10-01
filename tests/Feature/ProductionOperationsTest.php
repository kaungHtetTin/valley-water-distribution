<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Tests\TestCase;

class ProductionOperationsTest extends TestCase
{
    public function test_health_endpoint_checks_runtime_dependencies_without_caching_the_response(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertCookieMissing(config('session.cookie'))
            ->assertExactJson([
                'status' => 'ok',
                'checks' => [
                    'database' => true,
                    'cache' => true,
                    'storage' => true,
                ],
            ]);
    }

    public function test_health_endpoint_returns_service_unavailable_when_a_dependency_fails(): void
    {
        Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('Cache unavailable'));
        Cache::shouldReceive('forget')->once()->andThrow(new RuntimeException('Cache unavailable'));

        $this->getJson('/health')
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('checks.database', true)
            ->assertJsonPath('checks.cache', false)
            ->assertJsonPath('checks.storage', true);
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $originalEnvironment = app()->environment();
        $exception = null;
        app()->detectEnvironment(fn () => 'production');

        try {
            app(DemoSeeder::class)->run();
        } catch (RuntimeException $error) {
            $exception = $error;
        } finally {
            app()->detectEnvironment(fn () => $originalEnvironment);
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame('Demo data may only be seeded in local and testing environments.', $exception->getMessage());
    }

    public function test_sqlite_backup_can_be_restored_and_verified_in_isolation(): void
    {
        $directory = storage_path('framework/testing/backup-verify-'.uniqid());
        $source = $directory.DIRECTORY_SEPARATOR.'source.sqlite';
        File::ensureDirectoryExists($directory);
        touch($source);
        $database = new PDO('sqlite:'.$source);
        $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $database->exec("INSERT INTO users (name) VALUES ('Backup test')");
        $database = null;

        config([
            'database.default' => 'backup_verify',
            'database.connections.backup_verify' => ['driver' => 'sqlite', 'database' => $source, 'prefix' => ''],
            'valley.backup_directory' => $directory,
        ]);

        try {
            $this->artisan('valley:backup')->assertExitCode(0);
            $this->artisan('valley:backup-verify')->assertExitCode(0);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_compressed_mysql_backup_can_be_inspected_without_restoring(): void
    {
        $directory = storage_path('framework/testing/backup-inspect-'.uniqid());
        $path = $directory.DIRECTORY_SEPARATOR.'valley-test.sql.gz';
        File::ensureDirectoryExists($directory);
        File::put($path, gzencode('CREATE TABLE `users` (`id` bigint);'));
        config([
            'database.default' => 'backup_inspect',
            'database.connections.backup_inspect' => ['driver' => 'mysql'],
            'valley.backup_directory' => $directory,
        ]);

        try {
            $this->artisan('valley:backup-verify', ['path' => $path, '--dry-run' => true])->assertExitCode(0);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_production_readiness_command_accepts_a_hardened_configuration(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://valley.example.com',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.timezone' => 'Asia/Yangon',
            'valley.demo_endpoints' => false,
            'valley.initial_admin.email' => 'owner@valley.test',
            'valley.initial_admin.password' => 'production-test-secret',
            'valley.backup_directory' => storage_path('app/backups'),
            'uploads.profile_photos_path' => storage_path('app/backups'),
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'database.default' => 'production_check',
            'database.connections.production_check' => [
                'driver' => 'mysql',
                'username' => 'valley_app',
                'password' => 'test-secret',
            ],
            'logging.default' => 'daily',
            'mail.mailers.smtp.host' => 'smtp.valley.test',
            'mail.from.address' => 'system@valley.test',
        ]);

        $this->artisan('valley:production-check', ['--skip-assets' => true])->assertExitCode(0);
    }

    public function test_production_readiness_rejects_demo_admin_credentials(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://valley.example.com',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.timezone' => 'Asia/Yangon',
            'valley.demo_endpoints' => false,
            'valley.initial_admin.email' => 'admin@example.com',
            'valley.initial_admin.password' => 'password',
            'valley.backup_directory' => storage_path('app/backups'),
            'uploads.profile_photos_path' => storage_path('app/backups'),
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.encrypt' => true,
            'session.secure' => true,
            'database.default' => 'production_check',
            'database.connections.production_check' => [
                'driver' => 'mysql',
                'username' => 'valley_app',
                'password' => 'test-secret',
            ],
            'logging.default' => 'daily',
            'mail.mailers.smtp.host' => 'smtp.valley.test',
            'mail.from.address' => 'system@valley.test',
        ]);

        $this->artisan('valley:production-check', ['--skip-assets' => true])
            ->expectsOutputToContain('Initial administrator email')
            ->expectsOutputToContain('Initial administrator password')
            ->assertExitCode(1);
    }
}
