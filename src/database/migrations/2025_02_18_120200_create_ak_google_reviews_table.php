<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ak_google_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')
                ->nullable()
                ->constrained('ak_google_review_locations')
                ->nullOnDelete();
            $table->string('location_name');
            $table->string('review_name')->unique();
            $table->string('review_id')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->text('reviewer_photo_url')->nullable();
            $table->boolean('reviewer_is_anonymous')->default(false);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('review_created_at')->nullable();
            $table->timestamp('review_updated_at')->nullable();
            $table->text('reply_comment')->nullable();
            $table->timestamp('reply_updated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['location_name', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_google_reviews');
    }
};
