<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ak_reviews', function (Blueprint $table) {
            $table->string('lang', 8)->nullable()->after('extras');
            $table->string('country', 2)->nullable()->after('lang');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::table('ak_reviews', function (Blueprint $table) {
            $table->dropIndex(['country']);
            $table->dropColumn(['country', 'lang']);
        });
    }
};
