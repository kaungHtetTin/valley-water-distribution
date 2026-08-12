<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name_en', 120);
            $table->string('name_my', 120)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name_en', 160);
            $table->string('name_my', 160)->nullable();
            $table->text('description')->nullable();
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'brand_id', 'status']);
        });

        Schema::create('skus', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name_en', 180);
            $table->string('name_my', 180)->nullable();
            $table->string('size_label', 60)->nullable();
            $table->string('barcode', 80)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->decimal('volume_ml', 12, 3)->nullable();
            $table->decimal('weight_grams', 12, 3)->nullable();
            $table->unsignedInteger('shelf_life_days')->nullable();
            $table->boolean('track_lot')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->boolean('is_returnable')->default(false);
            $table->decimal('minimum_order_quantity', 18, 3)->default(1);
            $table->decimal('order_step_quantity', 18, 3)->default(1);
            $table->decimal('minimum_delivery_quantity', 18, 3)->default(1);
            $table->string('sale_status', 24)->default('saleable');
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->unique(['organization_id', 'barcode']);
            $table->index(['organization_id', 'product_id', 'status']);
        });

        Schema::create('sku_uom_conversions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('sku_id')->constrained()->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('factor_to_base', 18, 6);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_selling_unit')->default(true);
            $table->boolean('is_kpi_base')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['sku_id', 'uom_id', 'version']);
            $table->index(['organization_id', 'sku_id', 'status', 'effective_from'], 'sku_conversion_lookup');
        });

        Schema::create('price_types', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name_en', 120);
            $table->string('name_my', 120)->nullable();
            $table->unsignedSmallInteger('precedence')->default(100);
            $table->boolean('requires_approval')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('price_books', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('price_type_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name_en', 160);
            $table->string('name_my', 160)->nullable();
            $table->string('currency', 3)->default('MMK');
            $table->string('scope_type', 32)->default('organization_default');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'price_type_id', 'status']);
        });

        Schema::create('price_book_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_book_id')->constrained()->restrictOnDelete();
            $table->foreignId('sku_id')->constrained()->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->unsignedBigInteger('unit_price_minor');
            $table->decimal('minimum_quantity', 18, 3)->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('approval_status', 24)->default('approved');
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->index(['organization_id', 'price_book_id', 'sku_id', 'uom_id'], 'price_item_lookup');
            $table->index(['organization_id', 'status', 'effective_from', 'effective_to'], 'price_item_dates');
        });

        Schema::create('product_cost_histories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sku_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('unit_cost_minor');
            $table->string('currency', 3)->default('MMK');
            $table->string('valuation_method', 32);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('approval_status', 24)->default('pending');
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'warehouse_id', 'sku_id', 'effective_from'], 'product_cost_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_cost_histories');
        Schema::dropIfExists('price_book_items');
        Schema::dropIfExists('price_books');
        Schema::dropIfExists('price_types');
        Schema::dropIfExists('sku_uom_conversions');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
