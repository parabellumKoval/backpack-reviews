<?php

namespace Backpack\Reviews\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(GoogleReviewLocation::class, 'location_id');
    }
}
