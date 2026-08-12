<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_accounts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_book_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('acquiring_sales_profile_id')->nullable()->constrained('foundation_master_records')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name_en', 180);
            $table->string('name_my', 180)->nullable();
            $table->string('legal_name', 180)->nullable();
            $table->string('searchable_alias', 180)->nullable();
            $table->string('category', 80)->nullable();
            $table->string('preferred_language', 10)->default('my-MM');
            $table->string('acquisition_source', 80)->nullable();
            $table->string('settlement_policy', 32)->default('COD_CASH');
            $table->string('lifecycle_status', 32)->default('prospect');
            $table->boolean('credit_hold')->default(false);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'lifecycle_status', 'name_en']);
        });

        Schema::create('client_outlets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_account_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name_en', 180);
            $table->string('name_my', 180)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 24)->default('active');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'client_account_id', 'status']);
        });

        Schema::create('client_contacts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_outlet_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->string('phone', 40);
            $table->string('phone_normalized', 32);
            $table->string('email', 180)->nullable();
            $table->boolean('is_primary_ordering')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->index(['organization_id', 'phone_normalized']);
            $table->index(['organization_id', 'client_account_id', 'status']);
        });

        Schema::create('client_outlet_addresses', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('label', 80)->default('Primary delivery');
            $table->string('township', 120)->nullable();
            $table->string('ward_village', 160)->nullable();
            $table->text('street_address');
            $table->string('landmark', 255)->nullable();
            $table->text('delivery_note')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->time('service_window_start')->nullable();
            $table->time('service_window_end')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->index(['organization_id', 'client_outlet_id', 'status'], 'client_address_lookup');
        });

        Schema::create('outlet_way_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('way_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('role', 24)->default('primary');
            $table->string('change_reason', 500)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->index(['organization_id', 'client_outlet_id', 'role', 'effective_from'], 'outlet_way_history_lookup');
            $table->index(['organization_id', 'way_id', 'status', 'effective_from'], 'outlet_way_active_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_way_assignments');
        Schema::dropIfExists('client_outlet_addresses');
        Schema::dropIfExists('client_contacts');
        Schema::dropIfExists('client_outlets');
        Schema::dropIfExists('client_accounts');
    }
};
