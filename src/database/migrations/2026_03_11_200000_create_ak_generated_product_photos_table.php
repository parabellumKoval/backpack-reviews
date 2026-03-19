<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ak_generated_product_photos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->json('image')->nullable();
            $table->string('status', 32)->default('pending_review')->index();
            $table->longText('prompt')->nullable();
            $table->json('prompt_context')->nullable();
            $table->text('reference_image_url')->nullable();
            $table->string('reference_image_path')->nullable();
            $table->string('driver', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->unsignedBigInteger('generation_run_id')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('reviewed_by_id')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_generated_product_photos');
    }
};
