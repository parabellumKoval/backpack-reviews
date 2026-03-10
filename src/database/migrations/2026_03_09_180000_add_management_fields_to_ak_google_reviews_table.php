<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ak_google_reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('ak_google_reviews', 'reviewer_photo_path')) {
                $table->string('reviewer_photo_path')->nullable()->after('reviewer_photo_url');
            }

            if (!Schema::hasColumn('ak_google_reviews', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('metadata');
            }

            if (!Schema::hasColumn('ak_google_reviews', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('ak_google_reviews', function (Blueprint $table) {
            try {
                $table->dropIndex('ak_google_reviews_is_active_sort_order_index');
            } catch (\Throwable $e) {
                // index might not exist in some environments
            }

            if (Schema::hasColumn('ak_google_reviews', 'sort_order')) {
                $table->dropColumn('sort_order');
            }

            if (Schema::hasColumn('ak_google_reviews', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('ak_google_reviews', 'reviewer_photo_path')) {
                $table->dropColumn('reviewer_photo_path');
            }
        });
    }
};
