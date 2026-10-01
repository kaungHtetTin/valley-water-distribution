<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_route_visits', function (Blueprint $table) {
            $table->decimal('started_latitude', 10, 7)->nullable()->after('started_at');
            $table->decimal('started_longitude', 10, 7)->nullable()->after('started_latitude');
            $table->decimal('started_accuracy_m', 8, 2)->nullable()->after('started_longitude');
            $table->decimal('completed_latitude', 10, 7)->nullable()->after('completed_at');
            $table->decimal('completed_longitude', 10, 7)->nullable()->after('completed_latitude');
            $table->decimal('completed_accuracy_m', 8, 2)->nullable()->after('completed_longitude');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('gps_accuracy_m', 8, 2)->nullable()->after('longitude');
            $table->timestamp('gps_captured_at')->nullable()->after('gps_accuracy_m');
        });
    }

    public function down(): void
    {
        Schema::table('sales_route_visits', function (Blueprint $table) {
            $table->dropColumn([
                'started_latitude',
                'started_longitude',
                'started_accuracy_m',
                'completed_latitude',
                'completed_longitude',
                'completed_accuracy_m',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'gps_accuracy_m', 'gps_captured_at']);
        });
    }
};
