<?php

namespace Backpack\Reviews\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GoogleReview extends Model
{
    use CrudTrait;

    protected $table = 'ak_google_reviews';

    protected $guarded = ['id'];

    protected $casts = [
        'review_created_at' => 'datetime',
        'review_updated_at' => 'datetime',
        'reply_updated_at' => 'datetime',
        'synced_at' => 'datetime',
        'metadata' => 'array',
        'reviewer_is_anonymous' => 'bool',
        'is_active' => 'bool',
        'sort_order' => 'int',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $review): void {
            if (blank($review->review_name)) {
                $review->review_name = 'manual/' . Str::uuid()->toString();
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(GoogleReviewLocation::class, 'location_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
