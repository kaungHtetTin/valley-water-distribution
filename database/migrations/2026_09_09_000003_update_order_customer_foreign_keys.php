<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['orders', 'invoices', 'deliveries'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$table}_customer_id_foreign");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL");
        }
    }

    public function down()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['orders', 'invoices', 'deliveries'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$table}_customer_id_foreign");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT");
        }
    }
};
