<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetDemoData extends Command
{
    protected $signature = 'valley:demo-reset {--force : Skip confirmation} {--dry-run : Check whether reset is allowed}';

    protected $description = 'Reset the local database to initial setup data and one administrator account.';

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

        if (! $this->option('force') && ! $this->confirm('This permanently removes all local business records and keeps only the administrator and initial setup data. Continue?')) {
            return self::FAILURE;
        }

        $result = $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        if ($result !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Valley initial setup reset completed. Operational and demo records were not seeded.');

        return self::SUCCESS;
    }
}
