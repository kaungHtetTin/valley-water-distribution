<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class VerifyDatabaseBackup extends Command
{
    protected $signature = 'valley:backup-verify
        {path? : Backup file to verify; defaults to the newest Valley backup}
        {--dry-run : Validate and inspect the backup without restoring it}';

    protected $description = 'Verify a Valley backup and restore it into an isolated temporary database.';

    public function handle(): int
    {
        try {
            $path = $this->resolvePath();
            $connection = config('database.default');
            $driver = config("database.connections.{$connection}.driver");

            if ($this->option('dry-run')) {
                $this->inspect($path, $driver);
                $this->info("Backup inspection passed: {$path}");

                return self::SUCCESS;
            }

            match ($driver) {
                'mysql', 'mariadb' => $this->verifyMysql($path, $connection),
                'sqlite' => $this->verifySqlite($path),
                default => throw new RuntimeException("Unsupported backup driver: {$driver}"),
            };

            $this->info("Backup restore verification passed: {$path}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolvePath(): string
    {
        $requested = $this->argument('path');
        if ($requested) {
            $absolute = preg_match('/^[A-Za-z]:[\\\\\/]/', $requested) === 1 || str_starts_with($requested, DIRECTORY_SEPARATOR);
            $path = $absolute ? $requested : base_path($requested);
        } else {
            $files = collect(File::files((string) config('valley.backup_directory')))
                ->filter(fn ($file) => str_starts_with($file->getFilename(), 'valley-'))
                ->sortByDesc(fn ($file) => $file->getMTime());
            $path = $files->first()?->getPathname();
        }

        if (! $path || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('A readable Valley backup file was not found.');
        }

        return $path;
    }

    private function inspect(string $path, string $driver): void
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if (! $this->gzipContains($path, 'CREATE TABLE')) {
                throw new RuntimeException('The compressed backup does not contain table definitions.');
            }

            return;
        }

        $this->verifySqlite($path);
    }

    private function verifyMysql(string $path, string $connection): void
    {
        $this->assertMysqlBackupPath($path);
        $config = config("database.connections.{$connection}");
        $database = 'valley_restore_verify_'.now()->format('YmdHis').'_'.bin2hex(random_bytes(3));
        $server = new PDO(
            "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        try {
            $server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $command = [
                $this->mysqlBinary(),
                '--host='.$config['host'],
                '--port='.(string) $config['port'],
                '--user='.$config['username'],
                '--default-character-set=utf8mb4',
                $database,
            ];
            $environment = empty($config['password']) ? null : ['MYSQL_PWD' => $config['password']];
            $input = gzopen($path, 'rb');
            if ($input === false) {
                throw new RuntimeException('The compressed backup cannot be opened.');
            }

            try {
                $process = new Process($command, base_path(), $environment, $input, 300);
                $process->run();
            } finally {
                gzclose($input);
            }
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Backup restore failed: '.trim($process->getErrorOutput()));
            }

            $restored = new PDO(
                "mysql:host={$config['host']};port={$config['port']};dbname={$database};charset=utf8mb4",
                $config['username'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
            $tables = (int) $restored->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            if ($tables < 2) {
                throw new RuntimeException("Restore verification found only {$tables} tables.");
            }
            foreach (['migrations', 'users'] as $required) {
                $statement = $restored->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
                $statement->execute([$required]);
                if ((int) $statement->fetchColumn() !== 1) {
                    throw new RuntimeException("Restore verification is missing the {$required} table.");
                }
            }
        } finally {
            $server->exec("DROP DATABASE IF EXISTS `{$database}`");
        }
    }

    private function verifySqlite(string $path): void
    {
        if (str_ends_with($path, '.gz')) {
            throw new RuntimeException('The configured SQLite connection requires a .sqlite backup.');
        }
        $database = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($database->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
            throw new RuntimeException('SQLite integrity verification failed.');
        }
        $tables = (int) $database->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();
        if ($tables < 1) {
            throw new RuntimeException('The SQLite backup does not contain any tables.');
        }
    }

    private function gzipContains(string $path, string $needle): bool
    {
        $this->assertMysqlBackupPath($path);
        $input = gzopen($path, 'rb');
        if ($input === false) {
            throw new RuntimeException('The compressed backup cannot be opened.');
        }

        $overlap = '';
        try {
            while (! gzeof($input)) {
                $chunk = gzread($input, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('The compressed backup is invalid.');
                }
                $haystack = strtoupper($overlap.$chunk);
                if (str_contains($haystack, $needle)) {
                    return true;
                }
                $overlap = substr($haystack, -strlen($needle));
            }
        } finally {
            gzclose($input);
        }

        return false;
    }

    private function assertMysqlBackupPath(string $path): void
    {
        if (! str_ends_with($path, '.sql.gz')) {
            throw new RuntimeException('MySQL verification requires a .sql.gz backup.');
        }
    }

    private function mysqlBinary(): string
    {
        $configured = config('valley.mysql_path');
        $xampp = dirname(base_path(), 2).DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'mysql.exe';

        return $configured ?: (is_file($xampp) ? $xampp : 'mysql');
    }
}
