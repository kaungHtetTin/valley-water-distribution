<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('Asia/Yangon')->after('address');
            $table->string('currency', 3)->default('MMK')->after('timezone');
            $table->time('business_day_start')->default('00:00')->after('currency');
            $table->unsignedInteger('lock_version')->default(1)->after('business_day_start');
        });

        Schema::table('warehouses', function (Blueprint $table): void {
            $table->string('contact_name', 160)->nullable()->after('address');
            $table->string('phone', 40)->nullable()->after('contact_name');
            $table->decimal('latitude', 10, 7)->nullable()->after('phone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->time('order_cutoff_time')->nullable()->after('longitude');
            $table->text('service_area_note')->nullable()->after('order_cutoff_time');
            $table->unsignedInteger('lock_version')->default(1)->after('service_area_note');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropColumn(['contact_name', 'phone', 'latitude', 'longitude', 'order_cutoff_time', 'service_area_note', 'lock_version']);
        });
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['timezone', 'currency', 'business_day_start', 'lock_version']);
        });
    }
};
