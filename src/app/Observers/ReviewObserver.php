<?php

namespace Backpack\Reviews\app\Observers;

use Backpack\Reviews\app\Models\Review;
use Backpack\Reviews\app\Events\ReviewPublished;
use Backpack\Reviews\app\Events\ReviewUnpublished;
use Backpack\Reviews\app\Events\ReviewDeleted;

class ReviewObserver
{
    /**
     * Сразу после создания шлём событие публикации только для реально опубликованных отзывов.
     * Это важно для отложенной публикации: отзыв может быть создан в БД заранее,
     * но считаться опубликованным только в момент установки is_moderated=true.
     */
    public function created(Review $review): void
    {
        if ((bool) $review->is_moderated === true) {
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
    public function deleting(Review $review)
    {
        event(new ReviewDeleted($review));
    }
}
