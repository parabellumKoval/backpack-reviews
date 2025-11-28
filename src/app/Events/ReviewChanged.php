<?php

namespace Backpack\Reviews\app\Events;

use Backpack\Reviews\app\Models\Review;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $reviewId,
        public bool $isVideo,
        public string $action
    ) {
    }

    public static function for(Review $review, string $action): self
    {
        return new self($review->id, (bool) $review->is_video, $action);
    }
}
