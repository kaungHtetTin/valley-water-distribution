<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('contact_channels')->nullable()->after('email');
        });

        DB::table('companies')->orderBy('id')->get(['id', 'phone', 'email'])->each(function ($company) {
            $channels = array_filter([
                'phone' => $company->phone,
                'email' => $company->email,
            ], fn ($value) => filled($value));

            if ($channels !== []) {
                DB::table('companies')->where('id', $company->id)->update([
                    'contact_channels' => json_encode($channels),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('contact_channels');
        });
    }
};
