<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('allowed_radius_m')->default(20);
            $table->string('public_token', 64)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entered_employee_code')->nullable()->index();
            $table->string('submitted_token', 64)->nullable()->index();
            $table->timestamp('attendance_at')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('distance_m', 10, 2)->nullable();
            $table->string('status')->index();
            $table->string('rejection_reason')->nullable()->index();
            $table->timestamps();

            $table->index(['employee_id', 'attendance_at']);
            $table->index(['attendance_location_id', 'attendance_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_locations');
    }
};
