<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_route_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->string('status', 30)->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'customer_id', 'visit_date'], 'sales_route_visit_daily_unique');
            $table->index(['route_id', 'visit_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_route_visits');
    }
};
