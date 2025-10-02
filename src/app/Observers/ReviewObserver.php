<?php

namespace Backpack\Reviews\app\Observers;

use Backpack\Reviews\app\Models\Review;
use Backpack\Store\app\Models\Product;
use App\Notifications\ReviewBonus;

use Backpack\Reviews\app\Services\ReviewTypeResolver;

use Backpack\Reviews\app\Events\ReviewPublished;
use Backpack\Reviews\app\Events\ReviewUnpublished;
use Backpack\Reviews\app\Events\ReviewDeleted;

class ReviewObserver
{
/**
     * Сразу после создания отзыва:
     * - если стратегия "no_moderation" → считаем опубликованным и диспатчим ReviewPublished
     * - если "with_moderation" → ждём модерации (ничего не шлём)
     */
    public function created(Review $review): void
    {
        if (!ReviewTypeResolver::withModeration($review)) {
            // Можно просто шлём событие (флаг в БД можешь не трогать, если не требуется)
            event(new ReviewPublished($review));
        }
    }

    /**
     * При обновлении: отслеживаем смену флага is_moderated
     * false -> true  => ReviewPublished
     * true  -> false => ReviewUnpublished
     */
    public function updated(Review $review): void
    {
        if ($review->wasChanged('is_moderated')) {
            if ((bool) $review->is_moderated === true) {
                event(new ReviewPublished($review));
            } else {
                event(new ReviewUnpublished($review));
            }
        }
    }

    /**
     * Определение стратегии публикации по типу reviewable.
     * Ключи настроек:
     *  - rw.product.publish_strategy
     *  - rw.article.publish_strategy
     * Фолбек: rw.default.publish_strategy = 'with_moderation'
     */

    public function deleting(Review $review) {
        event(new ReviewDeleted($review));
    }
  
}
