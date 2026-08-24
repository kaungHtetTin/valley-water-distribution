<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('invoice_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('route_id')->constrained('routes')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->date('planned_date')->index();
            $table->string('status')->default('planned')->index();
            $table->decimal('total_quantity', 14, 2)->default(0);
            $table->decimal('delivered_quantity', 14, 2)->default(0);
            $table->decimal('returned_quantity', 14, 2)->default(0);
            $table->decimal('damaged_quantity', 14, 2)->default(0);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('loaded_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_sku');
            $table->string('product_name');
            $table->string('unit')->default('bottle');
            $table->decimal('planned_quantity', 14, 2);
            $table->decimal('loaded_quantity', 14, 2)->default(0);
            $table->decimal('delivered_quantity', 14, 2)->default(0);
            $table->decimal('returned_quantity', 14, 2)->default(0);
            $table->decimal('damaged_quantity', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
    }
};
