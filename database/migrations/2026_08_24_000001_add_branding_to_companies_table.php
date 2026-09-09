<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('name');
            $table->string('primary_color', 7)->default('#0b84a5')->after('logo_path');
            $table->string('default_theme', 10)->default('light')->after('primary_color');
        });
    }

    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'primary_color', 'default_theme']);
        });
    }
};
