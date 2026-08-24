<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('start_odometer_km', 12, 2)->nullable()->after('damaged_quantity');
            $table->decimal('end_odometer_km', 12, 2)->nullable()->after('start_odometer_km');
            $table->decimal('distance_km', 12, 2)->nullable()->after('end_odometer_km');
        });

        Schema::create('vehicle_costs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('cost_date')->index();
            $table->string('record_type')->default('cost')->index();
            $table->string('cost_type')->index();
            $table->string('description');
            $table->string('vendor')->nullable();
            $table->decimal('odometer_km', 12, 2)->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('payment_method')->default('cash');
            $table->string('reference_no')->nullable();
            $table->string('issue_severity')->nullable();
            $table->string('source_app')->default('office');
            $table->string('status')->default('approved')->index();
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('vehicle_costs');
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['start_odometer_km', 'end_odometer_km', 'distance_km']);
        });
    }
};
