<?php
use Backpack\Reviews\app\Http\Controllers\Api\ReviewAdminApiController;

Route::group([
  'prefix'     => config('backpack.base.route_prefix', 'admin'),
  'middleware' => ['web', config('backpack.base.middleware_key', 'admin')],
  'namespace'  => 'Backpack\Reviews\app\Http\Controllers\Admin',
], function () { 
    Route::crud('review', 'ReviewCrudController');
});


Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin') . '/reviews',
    'middleware' => array_filter([
        config('backpack.base.web_middleware', 'web'),
        config('backpack.base.middleware_key', 'admin'),
    ]),
], function () {
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


Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin') . '/review',
    'middleware' => array_filter([
        config('backpack.base.web_middleware', 'web'),
        config('backpack.base.middleware_key', 'admin'),
    ]),
], function () {
    Route::post('/{review}/toggle', [ReviewAdminApiController::class, 'toggleIsModeratedRouter'])->name('reviews.toggle');
});

