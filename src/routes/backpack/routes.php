<?php
use App\Http\Controllers\Admin\GenerationRunController;
use Backpack\Reviews\app\Http\Controllers\Admin\GoogleReviewOAuthController;
use Backpack\Reviews\app\Http\Controllers\Api\ReviewAdminApiController;

Route::group([
  'prefix'     => config('backpack.base.route_prefix', 'admin'),
  'middleware' => ['web', config('backpack.base.middleware_key', 'admin')],
  'namespace'  => 'Backpack\Reviews\app\Http\Controllers\Admin',
], function () { 
    Route::crud('review', 'ReviewCrudController');
    Route::crud('google-review', 'GoogleReviewCrudController');
    Route::crud('generated-product-photo', 'GeneratedProductPhotoCrudController');
    Route::post('google-review/sync', 'GoogleReviewCrudController@syncNow')
        ->name('bp.reviews.google.sync');
    Route::post('review/{id}/toggle', [
        'as' => 'reviews.toggle',
        'uses' => 'ReviewCrudController@toggleColumnRouter',
        'operation' => 'list',
    ]);
    Route::post('google-review/{id}/toggle', [
        'as' => 'google-review.toggle',
        'uses' => 'GoogleReviewCrudController@toggleColumnRouter',
        'operation' => 'list',
    ]);
    Route::post('generated-product-photo/{id}/toggle', [
        'as' => 'generated-product-photo.toggle',
        'uses' => 'GeneratedProductPhotoCrudController@toggleColumnRouter',
        'operation' => 'list',
    ]);

    Route::get('reviews/google/oauth', [GoogleReviewOAuthController::class, 'redirect'])
        ->name('bp.reviews.google.oauth');
    Route::get('reviews/google/callback', [GoogleReviewOAuthController::class, 'callback'])
        ->name('bp.reviews.google.callback');

    Route::group([
        'prefix' => 'review/generation-runs',
        'defaults' => ['generation_type' => \App\Models\GenerationRun::TYPE_PRODUCT_REVIEWS],
    ], function () {
        Route::get('/', [GenerationRunController::class, 'index'])->name('bp.reviews.generations.index');
        Route::post('/', [GenerationRunController::class, 'storeReviews'])->name('bp.reviews.generations.store');
        Route::get('{run}', [GenerationRunController::class, 'show'])->name('bp.reviews.generations.show');
    });

    Route::group([
        'prefix' => 'review/photo-generation-runs',
        'defaults' => ['generation_type' => \App\Models\GenerationRun::TYPE_PRODUCT_REVIEW_PHOTOS],
    ], function () {
        Route::get('/', [GenerationRunController::class, 'index'])->name('bp.reviews.photo_generations.index');
        Route::post('/', [GenerationRunController::class, 'storePhotos'])->name('bp.reviews.photo_generations.store');
        Route::get('{run}', [GenerationRunController::class, 'show'])->name('bp.reviews.photo_generations.show');
    });

    Route::get('generated-product-photo/moderation', 'GeneratedProductPhotoCrudController@moderation')
        ->name('bp.reviews.generated-photos.moderation');
    Route::post('generated-product-photo/moderation/approve', 'GeneratedProductPhotoCrudController@moderateBatch')
        ->name('bp.reviews.generated-photos.moderation.approve');
});


Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin') . '/reviews',
    'middleware' => array_filter([
        config('backpack.base.web_middleware', 'web'),
        config('backpack.base.middleware_key', 'admin'),
    ]),
], function () {
    Route::get('owners', [ReviewAdminApiController::class, 'owners'])->name('bp.reviews.owners');
    // Получить дерево отзывов для reviewable
    Route::get('{type}/{id}', [ReviewAdminApiController::class, 'index'])->name('bp.reviews.index');

    // Создать новый отзыв
    Route::post('/', [ReviewAdminApiController::class, 'store'])->name('bp.reviews.store');

    // Ответить на отзыв
    Route::post('{review}/reply', [ReviewAdminApiController::class, 'reply'])->name('bp.reviews.reply');

    // Обновить текст/рейтинг
    Route::patch('{review}', [ReviewAdminApiController::class, 'update'])->name('bp.reviews.update');

    // Удалить
    Route::delete('{review}', [ReviewAdminApiController::class, 'destroy'])->name('bp.reviews.destroy');

    // Модерация (toggle is_moderated)
    Route::post('{review}/moderate', [ReviewAdminApiController::class, 'toggleModeration'])->name('bp.reviews.moderate');

    // Лайк/дизлайк (опционально)
    Route::post('{review}/like', [ReviewAdminApiController::class, 'like'])->name('bp.reviews.like');
    Route::post('{review}/dislike', [ReviewAdminApiController::class, 'dislike'])->name('bp.reviews.dislike');

      
});
