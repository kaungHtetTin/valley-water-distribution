<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('registration_number', 80)->nullable()->after('legal_name');
            $table->string('tax_identifier', 80)->nullable()->after('registration_number');
            $table->string('phone', 40)->nullable()->after('tax_identifier');
            $table->string('email', 160)->nullable()->after('phone');
            $table->text('address')->nullable()->after('email');
            $table->string('document_locale', 10)->default('my-MM')->after('default_locale');
            $table->unsignedInteger('lock_version')->default(1)->after('timezone');
        });

        Schema::create('business_calendars', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name_en', 160);
            $table->string('name_my', 160)->nullable();
            $table->json('weekend_days');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'branch_id', 'status']);
        });

        Schema::create('business_calendar_dates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_calendar_id')->constrained()->cascadeOnDelete();
            $table->date('calendar_date');
            $table->string('day_type', 32);
            $table->string('name_en', 160);
            $table->string('name_my', 160)->nullable();
            $table->timestamps();
            $table->unique(['business_calendar_id', 'calendar_date']);
            $table->index(['organization_id', 'calendar_date']);
        });

        Schema::create('fiscal_periods', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('open');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'starts_on', 'ends_on']);
        });

        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('scope_key', 40);
            $table->string('document_type', 48);
            $table->string('name', 160);
            $table->string('prefix', 32)->nullable();
            $table->string('suffix', 32)->nullable();
            $table->unsignedTinyInteger('padding')->default(6);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('reset_policy', 24)->default('never');
            $table->string('last_reset_period', 10)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'scope_key', 'document_type']);
            $table->index(['organization_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('business_calendar_dates');
        Schema::dropIfExists('business_calendars');
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['registration_number', 'tax_identifier', 'phone', 'email', 'address', 'document_locale', 'lock_version']);
        });
    }
};
