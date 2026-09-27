<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class ProductionOperationsTest extends TestCase
{
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

    public function test_production_readiness_command_accepts_a_hardened_configuration(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://valley.example.com',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.timezone' => 'Asia/Yangon',
            'valley.demo_endpoints' => false,
            'valley.backup_directory' => storage_path('app/backups'),
            'session.secure' => true,
            'database.default' => 'production_check',
            'database.connections.production_check' => [
                'driver' => 'mysql',
                'username' => 'valley_app',
                'password' => 'test-secret',
            ],
            'logging.default' => 'daily',
        ]);

        $this->artisan('valley:production-check', ['--skip-assets' => true])->assertExitCode(0);
    }
}
