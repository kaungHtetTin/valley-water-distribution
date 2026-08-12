<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ways', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name_en', 160);
            $table->string('name_my', 160)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('way_versions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('way_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->text('boundary_description')->nullable();
            $table->json('service_days');
            $table->time('delivery_window_start')->nullable();
            $table->time('delivery_window_end')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('change_reason', 500)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['way_id', 'version']);
            $table->index(['organization_id', 'area_id', 'status', 'effective_from']);
            $table->index(['organization_id', 'default_warehouse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('way_versions');
        Schema::dropIfExists('ways');
    }
};
