<?php

namespace Backpack\Reviews\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleReviewLocation extends Model
{
    protected $table = 'ak_google_review_locations';

    protected $guarded = ['id'];

    protected $casts = [
        'address' => 'array',
        'metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleReviewConnection::class, 'connection_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(GoogleReview::class, 'location_id');
    }
}
