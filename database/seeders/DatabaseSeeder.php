<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SetupSeeder::class);

        // Operational fixtures are only needed by automated workflow tests.
        // `db:seed` and local setup resets must never populate demo business data.
        if (app()->runningUnitTests()) {
            $this->call(DemoSeeder::class);
        }

        $this->call(DemoSeeder::class);
        // Assign role defaults after any existing or test fixture employees are present.
        $this->call(KpiDefaultTargetsSeeder::class);
    }
}
