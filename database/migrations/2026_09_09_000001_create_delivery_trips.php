<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('delivery_trips', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('route_id')->constrained('routes')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->date('planned_date')->index();
            $table->string('status')->default('assigned')->index();
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_quantity', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable()->after('code')->constrained('delivery_trips')->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['reference_type', 'reference_id'], 'stock_movement_reference_idx');
        });
    }

    public function down()
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movement_reference_idx');
        });
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trip_id');
        });
        Schema::dropIfExists('delivery_trips');
    }
};
