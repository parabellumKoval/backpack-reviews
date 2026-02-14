<?php

namespace Backpack\Reviews\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleReviewConnection extends Model
{
    protected $table = 'ak_google_review_connections';

    protected $guarded = ['id'];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'meta' => 'array',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(GoogleReviewLocation::class, 'connection_id');
    }
}
