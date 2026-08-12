<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_zones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name_en', 160);
            $table->string('name_my', 160)->nullable();
            $table->string('zone_type', 32)->default('storage');
            $table->string('temperature_class', 32)->default('ambient');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
            $table->index(['organization_id', 'warehouse_id', 'status']);
        });

        Schema::create('warehouse_bins', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_zone_id')->constrained()->restrictOnDelete();
            $table->string('code', 48);
            $table->string('label', 160);
            $table->string('bin_type', 32)->default('bulk');
            $table->decimal('capacity_units', 18, 4)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
            $table->index(['organization_id', 'warehouse_zone_id', 'status']);
        });

        Schema::create('warehouse_sku_policies', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('sku_id')->constrained()->restrictOnDelete();
            $table->decimal('safety_stock', 18, 4)->default(0);
            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->decimal('target_stock', 18, 4)->default(0);
            $table->unsignedInteger('replenishment_lead_days')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['warehouse_id', 'sku_id']);
            $table->index(['organization_id', 'warehouse_id', 'status']);
        });

        Schema::create('cash_locations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name_en', 160);
            $table->string('name_my', 160)->nullable();
            $table->string('location_type', 32);
            $table->string('currency', 3)->default('MMK');
            $table->text('description')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_locations');
        Schema::dropIfExists('warehouse_sku_policies');
        Schema::dropIfExists('warehouse_bins');
        Schema::dropIfExists('warehouse_zones');
    }
};
