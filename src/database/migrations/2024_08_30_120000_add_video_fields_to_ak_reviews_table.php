<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ak_reviews', function (Blueprint $table) {
            $table->string('video_url', 2048)->nullable()->after('reviewable_id');
            $table->json('video_title')->nullable()->after('video_url');
            $table->json('video_poster')->nullable()->after('video_title');
        });
    }

    public function down(): void
    {
        Schema::table('ak_reviews', function (Blueprint $table) {
            $table->dropColumn(['video_poster', 'video_title', 'video_url']);
        });
    }
};
