<?php

namespace Backpack\Reviews\app\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface ReviewableAvailabilityScope
{
    /**
     * Apply per-model availability constraints that determine if a reviewable
     * entity can still be shown together with its reviews.
     */
    public function scopeReviewableAvailability(Builder $query, array $context = []): Builder;
}
