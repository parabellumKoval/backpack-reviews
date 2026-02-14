<?php
use Backpack\Reviews\app\Http\Controllers\Admin\GoogleReviewOAuthController;
use Backpack\Reviews\app\Http\Controllers\Api\ReviewAdminApiController;

Route::group([
  'prefix'     => config('backpack.base.route_prefix', 'admin'),
  'middleware' => ['web', config('backpack.base.middleware_key', 'admin')],
  'namespace'  => 'Backpack\Reviews\app\Http\Controllers\Admin',
], function () { 
    Route::crud('review', 'ReviewCrudController');
    Route::crud('google-review', 'GoogleReviewCrudController');
    Route::post('review/{id}/toggle', [
        'as' => 'reviews.toggle',
        'uses' => 'ReviewCrudController@toggleColumnRouter',
        'operation' => 'list',
    ]);

    Route::get('reviews/google/oauth', [GoogleReviewOAuthController::class, 'redirect'])
        ->name('bp.reviews.google.oauth');
    Route::get('reviews/google/callback', [GoogleReviewOAuthController::class, 'callback'])
        ->name('bp.reviews.google.callback');
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
