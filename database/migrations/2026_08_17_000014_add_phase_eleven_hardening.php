<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $auditedMasters = [
        'companies', 'areas', 'routes', 'warehouses', 'brands', 'products',
        'customers', 'employees', 'vehicles', 'suppliers',
    ];

    public function up()
    {
        foreach ($this->auditedMasters as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name')->nullable();
                $table->string('app', 30)->nullable()->index();
                $table->string('action', 30)->index();
                $table->string('method', 10);
                $table->string('path', 500);
                $table->string('entity_type')->nullable()->index();
                $table->unsignedBigInteger('entity_id')->nullable()->index();
                $table->unsignedSmallInteger('status_code')->index();
                $table->json('changes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        if (! Schema::hasTable('uat_issues')) {
            Schema::create('uat_issues', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('module')->index();
                $table->string('title');
                $table->text('steps')->nullable();
                $table->string('severity')->default('medium')->index();
                $table->string('status')->default('open')->index();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        $this->addIndex('orders', ['customer_id', 'status', 'order_date'], 'orders_customer_status_date_idx');
        $this->addIndex('invoices', ['customer_id', 'status', 'invoice_date'], 'invoices_customer_status_date_idx');
        $this->addIndex('deliveries', ['driver_id', 'status', 'planned_date'], 'deliveries_driver_status_date_idx');
        $this->addIndex('stock_movements', ['warehouse_id', 'product_id', 'movement_date'], 'stock_warehouse_product_date_idx');
        $this->addIndex('collections', ['customer_id', 'status', 'collection_date'], 'collections_customer_status_date_idx');
        $this->addIndex('expenses', ['employee_id', 'status', 'expense_date'], 'expenses_employee_status_date_idx');
        $this->addIndex('vehicle_costs', ['vehicle_id', 'status', 'cost_date'], 'vehicle_cost_status_date_idx');
        $this->addIndex('attendance_records', ['employee_id', 'attendance_at'], 'attendance_employee_date_idx');
    }

    public function down()
    {
        Schema::table('attendance_records', fn (Blueprint $table) => $table->dropIndex('attendance_employee_date_idx'));
        Schema::table('vehicle_costs', fn (Blueprint $table) => $table->dropIndex('vehicle_cost_status_date_idx'));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropIndex('expenses_employee_status_date_idx'));
        Schema::table('collections', fn (Blueprint $table) => $table->dropIndex('collections_customer_status_date_idx'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropIndex('stock_warehouse_product_date_idx'));
        Schema::table('deliveries', fn (Blueprint $table) => $table->dropIndex('deliveries_driver_status_date_idx'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropIndex('invoices_customer_status_date_idx'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_customer_status_date_idx'));

        Schema::dropIfExists('uat_issues');
        Schema::dropIfExists('audit_logs');

        foreach (array_reverse($this->auditedMasters) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('updated_by');
                $table->dropConstrainedForeignId('created_by');
                $table->dropSoftDeletes();
            });
        }
    }

    private function addIndex(string $tableName, array $columns, string $indexName): void
    {
        $exists = DB::getDriverName() === 'sqlite'
            ? collect(DB::select("PRAGMA index_list('{$tableName}')"))->contains(fn ($index) => $index->name === $indexName)
            : collect(DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]))->isNotEmpty();
        if (! $exists) {
            Schema::table($tableName, fn (Blueprint $table) => $table->index($columns, $indexName));
        }
    }
};
