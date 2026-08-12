<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foundation_master_records', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('way_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('price_book_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('foundation_master_records')->restrictOnDelete();
            $table->string('type', 40);
            $table->string('code', 40);
            $table->string('name_en', 180);
            $table->string('name_my', 180)->nullable();
            $table->string('classification', 60)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 180)->nullable();
            $table->text('address')->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'type', 'code']);
            $table->index(['organization_id', 'type', 'status', 'sort_order'], 'foundation_master_lookup');
        });

        Schema::create('master_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('master_type', 40);
            $table->string('source_name', 255);
            $table->string('status', 24);
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('valid_rows');
            $table->unsignedInteger('invalid_rows');
            $table->json('rows');
            $table->json('errors')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'master_type', 'created_at'], 'master_import_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_import_batches');
        Schema::dropIfExists('foundation_master_records');
    }
};
