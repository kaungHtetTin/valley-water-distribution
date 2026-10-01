<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Database\Seeders\DemoMonthSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoMonth extends Command
{
    protected $signature = 'valley:demo-seed {--month= : Completed month in YYYY-MM format} {--dry-run : Show the month without writing data}';

    protected $description = 'Seed a complete month of linked demo operations in an empty local database.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo seeding is available only in local and testing environments.');

            return self::FAILURE;
        }

        $month = $this->option('month') ?: CarbonImmutable::now()->subMonthNoOverflow()->format('Y-m');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month < '2026-09' || $month >= CarbonImmutable::now()->format('Y-m')) {
            $this->error('Choose a completed month from 2026-09 onward using --month=YYYY-MM.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("Demo seed would target {$month}; no data changed. An empty operational database is required.");

            return self::SUCCESS;
        }

        foreach (['employees', 'customers', 'orders', 'invoices', 'deliveries', 'collections', 'expenses', 'supplier_invoices', 'payrolls'] as $table) {
            if (DB::table($table)->exists()) {
                $this->error("{$table} already contains data. Demo seeding requires an empty operational database.");

                return self::FAILURE;
            }
        }

        app(DemoMonthSeeder::class)->seedMonth($month);
        $this->info("Demo operations for {$month} are ready. Demo staff accounts use the password documented in the UAT plan.");

        return self::SUCCESS;
    }
}
