<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('inventory_valuation_method', 32)->default('weighted_average')->after('currency');
        });
        Schema::table('product_cost_histories', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(1)->before('created_at');
            $table->string('status', 24)->default('active')->before('created_at');
        });
        Schema::create('price_book_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_book_id')->constrained()->restrictOnDelete();
            $table->string('target_type', 32);
            $table->string('target_key', 80);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->index(['organization_id', 'target_type', 'target_key', 'status'], 'price_assignment_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_book_assignments');
        Schema::table('product_cost_histories', fn (Blueprint $table) => $table->dropColumn(['lock_version', 'status']));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('inventory_valuation_method'));
    }
};
