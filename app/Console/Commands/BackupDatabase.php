<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'valley:backup {--dry-run : Validate configuration without writing a backup}';

    protected $description = 'Create a compressed database backup and remove expired backup files.';

    public function handle(): int
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $directory = (string) config('valley.backup_directory');

        if ($this->option('dry-run')) {
            $this->info("Backup configuration valid: {$driver} -> {$directory}");

            return self::SUCCESS;
        }

        File::ensureDirectoryExists($directory, 0750);
        $stamp = now()->format('Ymd-His');
        $filename = "valley-{$connection}-{$stamp}";

        try {
            $path = match ($driver) {
                'mysql', 'mariadb' => $this->backupMysql($directory, $filename, $connection),
                'sqlite' => $this->backupSqlite($directory, $filename, $connection),
                default => throw new RuntimeException("Unsupported backup driver: {$driver}"),
            };
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->prune($directory);
        $this->info("Database backup created: {$path}");

        return self::SUCCESS;
    }

    private function backupMysql(string $directory, string $filename, string $connection): string
    {
        $config = config("database.connections.{$connection}");
        $binary = $this->mysqlDumpBinary();
        $command = [$binary, '--single-transaction', '--routines', '--triggers', '--skip-comments', '--host='.$config['host'], '--port='.(string) $config['port'], '--user='.$config['username']];
        $command[] = $config['database'];
        $environment = empty($config['password']) ? null : ['MYSQL_PWD' => $config['password']];
        $process = new Process($command, base_path(), $environment, null, 300);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput()));
        }
        $path = $directory.DIRECTORY_SEPARATOR.$filename.'.sql.gz';
        File::put($path, gzencode($process->getOutput(), 9));

        return $path;
    }

    private function backupSqlite(string $directory, string $filename, string $connection): string
    {
        $source = config("database.connections.{$connection}.database");
        if (! is_file($source)) {
            throw new RuntimeException("SQLite database does not exist: {$source}");
        }
        $path = $directory.DIRECTORY_SEPARATOR.$filename.'.sqlite';
        File::copy($source, $path);

        return $path;
    }

    private function mysqlDumpBinary(): string
    {
        $configured = config('valley.mysqldump_path');
        $xampp = dirname(base_path(), 2).DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'mysqldump.exe';

        return $configured ?: (is_file($xampp) ? $xampp : 'mysqldump');
    }

    private function prune(string $directory): void
    {
        $cutoff = now()->subDays(max(1, (int) config('valley.backup_retention_days')))->timestamp;
        collect(File::files($directory))->filter(fn ($file) => str_starts_with($file->getFilename(), 'valley-') && $file->getMTime() < $cutoff)->each(fn ($file) => File::delete($file->getPathname()));
    }
}
