<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ak_reviews', function (Blueprint $table) {
            $table->string('review_type', 16)->nullable()->after('is_video');
            $table->json('photo_gallery')->nullable()->after('video_poster');
            $table->index('review_type');
        });

        DB::table('ak_reviews')
            ->whereNull('review_type')
            ->update([
                'review_type' => DB::raw("CASE WHEN is_video = 1 THEN 'video' ELSE 'text' END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('ak_reviews', function (Blueprint $table) {
            $table->dropIndex(['review_type']);
            $table->dropColumn(['photo_gallery', 'review_type']);
        });
    }
};
