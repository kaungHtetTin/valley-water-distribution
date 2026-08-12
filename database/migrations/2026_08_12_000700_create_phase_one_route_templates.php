<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name_en', 160);
            $table->string('name_my', 160)->nullable();
            $table->text('description')->nullable();
            $table->json('service_days');
            $table->time('departure_time')->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'branch_id', 'status']);
        });

        Schema::create('route_template_ways', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('route_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('way_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->timestamps();
            $table->unique(['route_template_id', 'way_id']);
            $table->unique(['route_template_id', 'sequence']);
            $table->index(['organization_id', 'way_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_template_ways');
        Schema::dropIfExists('route_templates');
    }
};
