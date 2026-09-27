<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('default_customer_credit_limit', 14, 2)->default(500000)->after('default_theme');
            $table->unsignedSmallInteger('delivery_credit_due_days')->default(14)->after('default_customer_credit_limit');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('settlement_method')->nullable()->after('status')->index();
            $table->decimal('settlement_amount', 14, 2)->default(0)->after('settlement_method');
            $table->timestamp('settled_at')->nullable()->after('settlement_amount');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['settlement_method']);
            $table->dropColumn(['settlement_method', 'settlement_amount', 'settled_at']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['default_customer_credit_limit', 'delivery_credit_due_days']);
        });
    }
};
