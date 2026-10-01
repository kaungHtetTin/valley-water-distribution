<?php

namespace Tests\Feature;

use Database\Seeders\SetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SetupSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_seeder_creates_only_administrator_and_initial_configuration(): void
    {
        $this->seed(SetupSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['role' => 'Owner']);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('roles', 9);
        foreach (['Owner', 'Office Staff', 'HR', 'Accountant', 'Finance Manager', 'Customer', 'Sales Representative', 'Sales Supervisor', 'Driver'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
        $this->assertGreaterThan(0, DB::table('permissions')->count());

        foreach ([
            'areas', 'warehouses', 'employees', 'customers', 'orders', 'invoices',
            'deliveries', 'attendance_records', 'collections', 'expenses', 'payrolls',
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
