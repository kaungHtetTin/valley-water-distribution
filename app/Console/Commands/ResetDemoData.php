<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetDemoData extends Command
{
    protected $signature = 'valley:demo-reset {--force : Skip confirmation} {--dry-run : Check whether reset is allowed}';

    protected $description = 'Reset the local demo database and reload deterministic UAT seed data.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo reset is disabled outside local and testing environments.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Demo reset is available in this environment.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('This will replace all current local data with demo data. Continue?')) {
            return self::FAILURE;
        }

        $result = $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        if ($result !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Valley demo data reset completed.');

        return self::SUCCESS;
    }
}
