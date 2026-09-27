<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProductionReadinessCheck extends Command
{
    protected $signature = 'valley:production-check
        {--skip-assets : Skip the compiled frontend manifest check}';

    protected $description = 'Fail fast when required production configuration is unsafe or incomplete.';

    public function handle(): int
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}", []);
        $backupDirectory = (string) config('valley.backup_directory');
        $absoluteBackupPath = preg_match('/^[A-Za-z]:[\\\\\/]/', $backupDirectory) === 1 || str_starts_with($backupDirectory, DIRECTORY_SEPARATOR);
        $checks = [
            ['Production environment', config('app.env') === 'production', 'Set APP_ENV=production.'],
            ['Debug disabled', config('app.debug') === false, 'Set APP_DEBUG=false.'],
            ['HTTPS application URL', str_starts_with((string) config('app.url'), 'https://'), 'Set APP_URL to the public HTTPS URL.'],
            ['Application key', str_starts_with((string) config('app.key'), 'base64:'), 'Generate and securely store a unique APP_KEY.'],
            ['Yangon timezone', config('app.timezone') === 'Asia/Yangon', 'Set APP_TIMEZONE=Asia/Yangon.'],
            ['Demo endpoints disabled', config('valley.demo_endpoints') === false, 'Set VALLEY_DEMO_ENDPOINTS=false.'],
            ['Secure session cookie', config('session.secure') === true, 'Set SESSION_SECURE_COOKIE=true.'],
            ['MySQL database', in_array($database['driver'] ?? null, ['mysql', 'mariadb'], true), 'Use a production MySQL or MariaDB connection.'],
            ['Least privilege database user', filled($database['username'] ?? null) && strtolower((string) $database['username']) !== 'root', 'Use a dedicated non-root DB_USERNAME.'],
            ['Database password', filled($database['password'] ?? null), 'Set a strong DB_PASSWORD.'],
            ['Daily production logs', in_array(config('logging.default'), ['daily', 'stderr'], true), 'Use LOG_CHANNEL=daily or stderr.'],
            ['Absolute backup directory', $absoluteBackupPath, 'Set VALLEY_BACKUP_DIRECTORY to an absolute protected path.'],
        ];

        if (! $this->option('skip-assets')) {
            $checks[] = ['Compiled frontend assets', is_file(public_path('build/manifest.json')), 'Run npm ci && npm run build.'];
        }

        $this->table(
            ['Check', 'Status', 'Required action'],
            array_map(fn ($check) => [$check[0], $check[1] ? 'PASS' : 'FAIL', $check[1] ? '' : $check[2]], $checks),
        );

        $failed = collect($checks)->where(1, false)->count();
        if ($failed > 0) {
            $this->error("Production readiness failed: {$failed} check(s) require attention.");

            return self::FAILURE;
        }

        $this->info('Production configuration readiness passed.');

        return self::SUCCESS;
    }
}
