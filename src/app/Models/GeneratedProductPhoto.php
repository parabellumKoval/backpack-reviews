<?php

namespace Backpack\Reviews\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use ParabellumKoval\BackpackImages\Traits\HasImages;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class GeneratedProductPhoto extends Model
{
    use CrudTrait;
    use HasImages;

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    protected $table = 'ak_generated_product_photos';

    protected $guarded = ['id'];

    protected $casts = [
        'prompt_context' => 'array',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public static function imageAttributeName(): string
    {
        return 'image';
    }

    public static function imageStorageFolder(?string $attribute = null): string
    {
        return 'reviews/generated-product-photos';
    }

    public static function imageCollections(): array
    {
        return array_replace_recursive(
            static::defaultImageCollections(),
            [
                'image' => [
                    'label' => 'Сгенерированное фото',
                    'tab' => null,
                    'folder' => 'reviews/generated-product-photos',
                    'column_limit' => 1,
                    'field' => [
                        'label' => 'Сгенерированное фото',
                        'tab' => null,
                        'new_item_label' => 'Добавить фото',
                        'init_rows' => 1,
                        'min_rows' => 0,
                        'max_rows' => 1,
                        'fields' => [
                            [
                                'label' => 'Фото',
                                'aspect_ratio' => 4 / 3,
                            ],
                        ],
                    ],
                    'column' => [
                        'label' => 'Сгенерированное фото',
                    ],
                ],
            ]
        );
    }

    public function product(): BelongsTo
    {
        $model = config('backpack.reviews.generated_product_photos.product_model', \App\Models\Product::class);

        return $this->belongsTo($model, 'product_id');
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->getFirstImageForApi('image')['src'] ?? null;
    }

    public function markApproved(?int $reviewedBy = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_APPROVED,
            'reviewed_by_id' => $reviewedBy,
            'reviewed_at' => now(),
            'approved_at' => now(),
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error_message' => Str::limit(trim($message), 1500, '...'),
        ])->save();
    }
}
