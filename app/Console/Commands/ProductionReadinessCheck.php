<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

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
        $adminEmail = (string) config('valley.initial_admin.email');
        $adminPassword = (string) config('valley.initial_admin.password');
        $ownerExists = $this->ownerExists();
        $profilePhotoDirectory = (string) config('uploads.profile_photos_path');
        $absoluteProfilePhotoPath = preg_match('/^[A-Za-z]:[\\\\\/]/', $profilePhotoDirectory) === 1 || str_starts_with($profilePhotoDirectory, DIRECTORY_SEPARATOR);
        $mailHost = (string) config('mail.mailers.smtp.host');
        $mailFrom = (string) config('mail.from.address');
        $trustedProxies = trim((string) config('trustedproxy.proxies'));
        $checks = [
            ['Production environment', config('app.env') === 'production', 'Set APP_ENV=production.'],
            ['Debug disabled', config('app.debug') === false, 'Set APP_DEBUG=false.'],
            ['HTTPS application URL', str_starts_with((string) config('app.url'), 'https://'), 'Set APP_URL to the public HTTPS URL.'],
            ['Trusted proxy scope', ! in_array($trustedProxies, ['*', '**'], true), 'List only the reverse proxy IP addresses or CIDR ranges; never trust every caller.'],
            ['Application key', str_starts_with((string) config('app.key'), 'base64:'), 'Generate and securely store a unique APP_KEY.'],
            ['Yangon timezone', config('app.timezone') === 'Asia/Yangon', 'Set APP_TIMEZONE=Asia/Yangon.'],
            ['Demo endpoints disabled', config('valley.demo_endpoints') === false, 'Set VALLEY_DEMO_ENDPOINTS=false.'],
            ['Shared cache driver', in_array(config('cache.default'), ['database', 'redis'], true), 'Set CACHE_DRIVER=database or redis for shared scheduler locks.'],
            ['Persistent session driver', in_array(config('session.driver'), ['database', 'redis'], true), 'Set SESSION_DRIVER=database or redis so sessions survive deployments.'],
            ['Encrypted sessions', config('session.encrypt') === true, 'Set SESSION_ENCRYPT=true.'],
            ['Secure session cookie', config('session.secure') === true, 'Set SESSION_SECURE_COOKIE=true.'],
            ['MySQL database', in_array($database['driver'] ?? null, ['mysql', 'mariadb'], true), 'Use a production MySQL or MariaDB connection.'],
            ['Least privilege database user', filled($database['username'] ?? null) && strtolower((string) $database['username']) !== 'root', 'Use a dedicated non-root DB_USERNAME.'],
            ['Database password', filled($database['password'] ?? null), 'Set a strong DB_PASSWORD.'],
            ['Daily production logs', in_array(config('logging.default'), ['daily', 'stderr'], true), 'Use LOG_CHANNEL=daily or stderr.'],
            ['Initial administrator email', $ownerExists || (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) !== false && ! str_ends_with(strtolower($adminEmail), '@example.com')), 'Set VALLEY_ADMIN_EMAIL to a real administrator address for the first seed.'],
            ['Initial administrator password', $ownerExists || (strlen($adminPassword) >= 12 && ! in_array(strtolower($adminPassword), ['password', 'changeme', 'change-me'], true)), 'Set VALLEY_ADMIN_PASSWORD to a unique value of at least 12 characters for the first seed.'],
            ['Absolute backup directory', $absoluteBackupPath, 'Set VALLEY_BACKUP_DIRECTORY to an absolute protected path.'],
            ['Writable backup directory', is_dir($backupDirectory) && is_writable($backupDirectory), 'Create VALLEY_BACKUP_DIRECTORY and grant the deployment account write access.'],
            ['Absolute profile photo directory', $absoluteProfilePhotoPath, 'Set PROFILE_PHOTOS_PATH to an absolute shared path that persists across releases.'],
            ['Writable profile photo directory', is_dir($profilePhotoDirectory) && is_writable($profilePhotoDirectory), 'Create PROFILE_PHOTOS_PATH and grant the web account write access.'],
            ['Production SMTP host', filled($mailHost) && ! in_array(strtolower($mailHost), ['mailpit', 'localhost', '127.0.0.1'], true), 'Set MAIL_HOST to the production SMTP server.'],
            ['Production sender address', filter_var($mailFrom, FILTER_VALIDATE_EMAIL) !== false && ! str_ends_with(strtolower($mailFrom), '@example.com'), 'Set MAIL_FROM_ADDRESS to the business sender address.'],
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

    private function ownerExists(): bool
    {
        try {
            return Schema::hasTable('users')
                && Schema::hasColumn('users', 'role')
                && DB::table('users')->where('role', 'Owner')->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
