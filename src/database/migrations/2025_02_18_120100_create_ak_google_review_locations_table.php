<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ak_google_review_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')
                ->constrained('ak_google_review_connections')
                ->cascadeOnDelete();
            $table->string('account_name');
            $table->string('account_id')->nullable();
            $table->string('location_name');
            $table->string('location_id')->nullable();
            $table->string('title')->nullable();
            $table->string('store_code')->nullable();
            $table->string('language_code')->nullable();
            $table->json('address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'location_name']);
            $table->index(['account_name', 'location_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_google_review_locations');
    }
};
