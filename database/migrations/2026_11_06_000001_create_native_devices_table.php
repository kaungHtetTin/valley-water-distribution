<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('native_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('installation_id');
            $table->string('app', 20);
            $table->string('session_hash', 64)->index();
            $table->text('fcm_token')->nullable();
            $table->string('tracking_token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('tracking_expires_at')->nullable();
            $table->json('alert_state')->nullable();
            $table->timestamps();
            $table->unique(['installation_id', 'app']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('native_devices');
    }
};
