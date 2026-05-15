<?php

namespace Backpack\Reviews\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;
use Illuminate\Database\Eloquent\Model;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Reviews\database\factories\ReviewFactory;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Backpack\Helpers\Traits\HasDisplayLabel;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use ParabellumKoval\BackpackImages\Services\ImageUploader;
use ParabellumKoval\BackpackImages\Support\ImageUploadOptions;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use ParabellumKoval\BackpackImages\Traits\HasImages;
use Throwable;
use Backpack\Reviews\app\Events\ReviewChanged;
use Backpack\Helpers\Traits\FormatsUniqAttribute;
use Illuminate\Validation\ValidationException;

use Backpack\Schedule\Contracts\SchedulableInterface;
use Backpack\Schedule\Contracts\HasCrudCardInterface;
use Backpack\Schedule\Traits\Schedulable;

class Review extends Model implements SchedulableInterface, HasCrudCardInterface
{
    use CrudTrait;
    use HasFactory;
    use HasDisplayLabel;
    use HasTranslations;
    use HasImages {
        processImagesBeforeSaving as protected processImagesBeforeSavingBase;
        processSingleImage as protected processSingleImageBase;
        isBase64Image as protected isBase64ImageBase;
    }
    use FormatsUniqAttribute;
    use Schedulable;
    
    protected string $schedulePublishField = 'is_moderated';
    protected bool $scheduleOverwriteCreatedAt = true;
    /**
     * Override to prevent double json_decode on extras column.
     * The NormalizedExtrasCast already decodes and normalizes the data.
     */
    public function shouldDecodeFake($column)
    {
        // extras is handled by NormalizedExtrasCast, so never decode it again
        if ($column === 'extras') {
            return false;
        }

        return parent::shouldDecodeFake($column);
    }
    
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_reviews';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
      'text',
      'is_moderated',
      'rating',
      'likes',
      'dislikes',
      'owner_id',
      'parent_id',
      'reviewable_type',
      'reviewable_id',
      'is_video',
      'review_type',
      'video_url',
      'video_title',
      'video_poster',
      'photo_gallery',
      'extras',
      'lang',
      'country',
      'created_at',
    ];
    // protected $hidden = [];
    // protected $dates = [];
	
    // !!!!
	  // protected $with = ['owner'];

    protected $casts = [
      'is_moderated' => 'bool',
      'is_video' => 'bool',
      'review_type' => 'string',
      'extras' => \Backpack\Reviews\app\Casts\NormalizedExtrasCast::class,
      'video_poster' => 'array',
    ];

    protected $translatable = [
      'video_title',
    ];
	
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * __construct
     *
     * @param  mixed $attributes
     * @return void
     */
    // public function __construct(array $attributes = array()) {
    //   parent::__construct($attributes);
    // }

    public function toArray()
    {
      return [
        "id" => $this->id,
        "owner" => $this->ownerModelOrInfo,
        "rating" => $this->rating,
        "likes" => $this->likes? $this->likes: 0,
        "dislikes" => $this->dislikes? $this->dislikes: 0,
        "text" => $this->text,
        "video" => $this->videoData(true),
        "is_video" => (bool) $this->is_video,
        "review_type" => $this->resolveReviewType(),
        "photos" => $this->photoGalleryForApi(),
        "extras" => $this->extras,
        "lang" => $this->lang,
        "country" => $this->country,
        "created_at" => $this->created_at,
        "children" => $this->children,
      ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return ReviewFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $review): void {
            $reviewType = $review->resolveReviewType();

            $review->review_type = $reviewType;
            $review->is_video = $reviewType === 'video';
        });

        static::saved(function (self $review): void {
            event(ReviewChanged::for($review, 'saved'));
        });

        static::deleted(function (self $review): void {
            event(ReviewChanged::for($review, 'deleted'));
        });
    }

    public static function imageCollections(): array
    {
        return array_replace_recursive(
            static::defaultImageCollections(),
            [
                'video_poster' => [
                    'label' => 'Постер видео',
                    'tab' => null,
                    'folder' => 'reviews/video-posters',
                    'column_limit' => 1,
                    'field' => [
                        'label' => 'Постер видео',
                        'tab' => null,
                        'new_item_label' => 'Добавить постер',
                        'init_rows' => 1,
                        'min_rows' => 0,
                        'max_rows' => 1,
                        'fields' => [
                            [
                                'label' => 'Постер',
                                'aspect_ratio' => 16 / 9,
                            ],
                        ],
                    ],
                    'column' => [
                        'label' => 'Постер видео',
                    ],
                ],
                'photo_gallery' => [
                    'label' => 'Фото отзыва',
                    'tab' => null,
                    'folder' => 'reviews/photos',
                    'column_limit' => 5,
                    'field' => [
                        'label' => 'Фото отзыва',
                        'tab' => null,
                        'new_item_label' => 'Добавить фото',
                        'init_rows' => 0,
                        'min_rows' => 0,
                        'max_rows' => (int) config('backpack.reviews.photo_review.max_files', 5),
                        'fields' => [
                            [
                                'label' => 'Фото',
                                'aspect_ratio' => 4 / 3,
                            ],
                            [
                                'name' => 'alt',
                                'label' => 'ALT (опционально)',
                                'type' => 'text',
                            ],
                        ],
                    ],
                    'column' => [
                        'label' => 'Фото отзыва',
                    ],
                ],
            ]
        );
    }

    protected function displayLabelConfig(): array
    {
        // Считаем всё заранее и просто возвращаем массив
        $prefix = 'Отзыв';

        $text   = $this->text && mb_strlen($this->text) > 15 ?  mb_strcut($this->text, 0, 15) . '...': $this->text;
        $user = $this->user? '👨‍💻 ' . $this->user->name: null; 
        $time   = '🕒 ' . $this->created_at->format('Y-m-d H:i');

        return [
            'prefix' => $prefix,
            'parts'   => array_filter([$text, $user, $time]),
            'join'    => ' / ',
            // 'country' => $this->country_code ?? '',
            // 'html_template' => 'crud::columns.order_display_label'
        ];
    }

    public function videoData(bool $includeTranslations = false): ?array
    {
        $hasTitle = $this->hasVideoTitle();
        $poster = $this->videoPosterForApi();
        $hasVideo = $this->video_url || $hasTitle || $poster;

        if (!$hasVideo) {
            return null;
        }

        $data = [
            'url' => $this->video_url,
            'title' => $this->translateVideoTitle(app()->getLocale()),
            'poster' => $poster,
        ];

        if ($includeTranslations) {
            $data['title_translations'] = $this->getTranslations('video_title');
        }

        return $data;
    }

    public function videoPosterForApi(): ?array
    {
        $image = $this->getFirstImage('video_poster');

        if (!$image) {
            return null;
        }

        $path = $image['src'] ?? $image['path'] ?? null;
        $url = $image['url'] ?? null;

        if ($path) {
            $url = $this->formatImageUrlForAttribute('video_poster', $path);
        } elseif ($url) {
            $path = $image['path'] ?? null;
        } else {
            return null;
        }

        return [
            'url' => $url,
            'path' => $path,
        ];
    }

    public function photoGalleryForApi(): array
    {
        return $this->getImagesCollection('photo_gallery')
            ->map(function (array $image): ?array {
                $path = $image['src'] ?? $image['path'] ?? null;
                $url = $image['url'] ?? null;

                if ($path) {
                    $url = $this->formatImageUrlForAttribute('photo_gallery', $path);
                }

                if (!$url) {
                    return null;
                }

                return array_filter([
                    'url' => $url,
                    'path' => $path,
                    'alt' => $image['alt'] ?? null,
                    'title' => $image['title'] ?? null,
                ], fn ($value) => $value !== null && $value !== '');
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function translateVideoTitle(string $locale): ?string
    {
        if (!$this->hasVideoTitle()) {
            return null;
        }

        $translation = $this->getTranslation('video_title', $locale, false);

        if ($translation !== '') {
            return $translation;
        }

        // fallback to default locale
        return $this->getTranslation('video_title', config('app.locale'), false) ?: null;
    }

    protected function hasVideoTitle(): bool
    {
        $translations = $this->getTranslations('video_title');

        if (!is_array($translations) || empty($translations)) {
            return false;
        }

        foreach ($translations as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    public function resolveReviewType(?string $candidate = null): string
    {
        $explicit = $this->normalizeReviewType(
            $candidate
            ?? ($this->attributes['review_type'] ?? null)
            ?? $this->getRawOriginal('review_type')
        );
        if ($explicit) {
            return $explicit;
        }

        $hasVideoContent = !empty($this->video_url)
            || $this->hasVideoTitle()
            || !empty($this->video_poster)
            || (bool) $this->is_video;

        if ($hasVideoContent) {
            return 'video';
        }

        if ($this->hasPhotoGalleryContent()) {
            return 'photo';
        }

        return 'text';
    }

    public function setReviewTypeAttribute($value): void
    {
        $this->attributes['review_type'] = $this->normalizeReviewType($value) ?? null;
    }

    public function getReviewTypeAttribute($value): string
    {
        return $this->resolveReviewType($value);
    }

    protected function normalizeReviewType($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['text', 'video', 'photo'], true)) {
            return $normalized;
        }

        return null;
    }

    protected function hasPhotoGalleryContent(): bool
    {
        $raw = $this->photo_gallery;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (!is_array($raw)) {
            return false;
        }

        foreach ($raw as $item) {
            if (is_array($item) && !empty($item['src'] ?? $item['path'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    
    public function user()
    {
      $model = config('backpack.reviews.owner_model', null);

      if(!$model)
        return new BelongsTo($this->newQuery(), $this, '', '', '');

      return $this->belongsTo($model, 'owner_id');
    }
    
    public function parent()
    {
      return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
      return $this->hasMany(self::class, 'parent_id');
    }
    
    public function reviewable()
    {
      return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeRoot($query)
    {
      return $query->where('parent_id', 0)->orWhere('parent_id', null);
    }

    public function scopeModerated($query)
    {
      return $query->where('is_moderated', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function getUniqStringAttribute(): string
    {
        $subject = $this->reviewable_type
            ? sprintf('%s #%s', class_basename($this->reviewable_type), $this->reviewable_id ?? '?')
            : null;

        return $this->formatUniqString([
            '#'.$this->id,
            sprintf('rating: %s', $this->rating ?? 0),
            $subject,
            sprintf('owner #%s', $this->owner_id ?? '?'),
            $this->country,
            $this->is_moderated ? 'moderated' : 'pending',
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $subject = $this->reviewable_type
            ? sprintf('%s #%s', class_basename($this->reviewable_type), $this->reviewable_id ?? '?')
            : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            sprintf('rating: %s', $this->rating ?? 0),
        ]);

        return $this->formatUniqHtml($headline, [
            $subject,
            Str::limit($this->text ?? '', 60),
            $this->country,
            $this->is_moderated ? 'moderated' : 'pending',
            $this->created_at ? $this->created_at->format('Y-m-d H:i') : null,
        ]);
    }
    protected function resolveOwnerExtras(): ?array
    {
        $owner = $this->extras['owner'] ?? null;

        if (is_string($owner)) {
            $decoded = json_decode($owner, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $owner = $decoded;
            }
        }

        if (is_array($owner) && !Arr::isAssoc($owner) && isset($owner[0]) && is_array($owner[0])) {
            $owner = $owner[0];
        }

        return is_array($owner) ? $owner : null;
    }

    public function getReactionsAttribute(): array
    {
        return [
            'likes' => (int) ($this->attributes['likes'] ?? 0),
            'dislikes' => (int) ($this->attributes['dislikes'] ?? 0),
        ];
    }

    /**
     * getShortReviewableAttribute
     *
     * @return void
     */
    public function getShortReviewableAttribute() {
      if(!$this->reviewable)
        return null;
      
      return [
        'id' => $this->reviewable->id,
        'name' => $this->reviewable->name,
        'slug' => $this->reviewable->slug,
        'class' => get_class($this->reviewable)
      ];
    }
    
    /**
     * getDetailedRatingAvrAttribute
     *
     * @return void
     */
    public function getDetailedRatingAvrAttribute() {
      if(isset($this->extras['rating']) && count($this->extras['rating'])) {
        return array_sum($this->extras['rating']) / count($this->extras['rating']);
      }else{
        return 0;
      }
    }

    public function getRatingAttribute($value)
    {
        return $this->normalizeNumericAttribute($value);
    }

    public function getLikesAttribute($value)
    {
        $normalized = $this->normalizeNumericAttribute($value);

        return $normalized ?? 0;
    }

    public function getDislikesAttribute($value)
    {
        $normalized = $this->normalizeNumericAttribute($value);

        return $normalized ?? 0;
    }
    
    /**
     * getOwnerModelOrInfoAttribute
     *
     * @return void
     */
    public function getOwnerModelOrInfoAttribute() {
      $owner = $this->resolveOwnerExtras();

      if($owner){
        return $owner;
      }elseif($this->user){
        return $this->user;
      }else {
        return null;
      }
    }
    
    /**
     * getShortIdentityAttribute
     *
     * @return void
     */
    public function getShortIdentityAttribute() {
      $identity_string = "id - {$this->id}";

      if(!empty($this->text))
        $identity_string .= " / " . mb_substr($this->text, 0, 70) . '...';

      return $identity_string;
    }
    
    /**
     * getPhotoAnywayAttribute
     *
     * @return void
     */
    public function getPhotoAnywayAttribute() {
      if($this->user && $this->user->photo){
        return $this->user->photo;
      }else if($this->extrasOwnerPhoto) {
        return $this->extrasOwnerPhoto;
      }else {
        return null;
      }
    }

    public function getOwnerAttribute() {
      $owner = $this->resolveOwnerExtras();

      return $owner ? [$owner] : null;
    }

    public function getAdvantagesAttribute()
    {
        $value = $this->extras['advantages'] ?? null;

        if (is_array($value)) {
            return implode("\n", array_filter($value, 'is_string'));
        }

        return $value;
    }

    public function getFlawsAttribute()
    {
        $value = $this->extras['flaws'] ?? null;

        if (is_array($value)) {
            return implode("\n", array_filter($value, 'is_string'));
        }

        return $value;
    }

    public function getExtrasOwnerIdAttribute() {
      return $this->resolveOwnerExtras()['id'] ?? null;
    }

    public function getExtrasOwnerFullnameAttribute() {
      return $this->resolveOwnerExtras()['name'] ?? null;
    }
    
    public function getExtrasOwnerEmailAttribute() {
      return $this->resolveOwnerExtras()['email'] ?? null;
    }
    
    public function getExtrasOwnerPhotoAttribute() {
      return $this->resolveOwnerExtras()['photo'] ?? null;
    }

    // public function getOwnerAttribute() {
    //   dd($this->extras['owner']);
    // }

    /*
    |--------------------------------------------------------------------------
    | HasCrudCardInterface Implementation
    |--------------------------------------------------------------------------
    */
    
    /**
     * Получить HTML карточки для отображения в CRUD списке
     */
    public function getCrudCardHtml(array $options = []): string
    {
        $textLimit = $options['text_limit'] ?? 80;
        $showRating = $options['show_rating'] ?? true;
        $compact = $options['compact'] ?? true;
        
        $text = $this->text ?? '';
        $displayText = mb_strlen($text) > $textLimit 
            ? mb_substr($text, 0, $textLimit) . '...' 
            : ($text ?: 'Без текста');
        
        $rating = $this->rating ?? 0;
        $reviewType = $this->resolveReviewType();
        $isVideo = $reviewType === 'video';
        $isPhoto = $reviewType === 'photo';
        $isModerated = $this->is_moderated ?? false;
        $editUrl = $this->getCrudEditUrl();
        
        // Rating stars
        $starsHtml = '';
        if ($showRating && $rating > 0) {
            $starsHtml = '<div style="margin-bottom: 2px;">';
            for ($i = 1; $i <= 5; $i++) {
                $color = $i <= $rating ? '#f2c200' : '#ddd';
                $starsHtml .= '<i class="la la-star" style="color: ' . $color . '; font-size: 11px;"></i>';
            }
            $starsHtml .= '</div>';
        }
        
        // Status badge
        $statusBadge = $isModerated 
            ? '<span class="badge badge-success" style="font-size: 9px;">Опубликован</span>'
            : '<span class="badge badge-warning" style="font-size: 9px;">Модерация</span>';
        
        // Icon
        $iconBg = $isVideo ? '#e8f4fd' : ($isPhoto ? '#fff4ea' : '#f5f5f5');
        $iconClass = $isVideo ? 'la-video' : ($isPhoto ? 'la-image' : 'la-comment');
        $iconColor = $isVideo ? '#2196F3' : ($isPhoto ? '#fd7e14' : '#999');
        
        $html = '<div class="review-crud-card" style="display: flex; gap: 8px; padding: 6px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fafafa; ' . ($compact ? 'max-width: 280px;' : '') . '">';
        $html .= '<div style="flex-shrink: 0; width: 32px; height: 32px; background: ' . $iconBg . '; border-radius: 4px; display: flex; align-items: center; justify-content: center;">';
        $html .= '<i class="la ' . $iconClass . '" style="font-size: 16px; color: ' . $iconColor . ';"></i>';
        $html .= '</div>';
        $html .= '<div style="flex-grow: 1; min-width: 0;">';
        $html .= $starsHtml;
        $html .= '<a href="' . e($editUrl) . '" style="color: #333; text-decoration: none; font-size: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="' . e($text) . '">';
        $html .= e($displayText);
        $html .= '</a>';
        $html .= '<div style="margin-top: 2px;">' . $statusBadge . '</div>';
        $html .= '</div></div>';
        
        return $html;
    }

    /**
     * Получить URL для редактирования записи в админке
     */
    public function getCrudEditUrl(): ?string
    {
        return backpack_url('review/' . $this->id . '/edit');
    }

    /**
     * Получить название записи для отображения
     */
    public function getCrudCardTitle(): string
    {
        $text = $this->text ?? '';
        if (mb_strlen($text) > 50) {
            return mb_substr($text, 0, 50) . '...';
        }
        return $text ?: 'Отзыв #' . $this->id;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    public function setExtrasAttribute($value): void
    {
        $normalized = $this->normalizeExtrasValue($value);

        $this->attributes['extras'] = !empty($normalized)
            ? json_encode($normalized, JSON_UNESCAPED_UNICODE)
            : null;
    }

    protected function normalizeNumericAttribute($value): ?int
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            $value = $value->first();
        }

        if (is_array($value)) {
            $flattened = Arr::flatten($value);
            $value = $flattened ? reset($flattened) : null;
        }

        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function normalizeExtrasValue($value): array
    {
        $extras = $this->decodeJsonValue($value);
        $extras = is_array($extras) ? $extras : [];

        $previous = $this->getOriginalExtras();

        if (array_key_exists('owner', $extras)) {
            $extras['owner'] = $this->normalizeOwnerData($extras['owner'], $previous['owner'] ?? null);
        } elseif (isset($previous['owner'])) {
            $extras['owner'] = $previous['owner'];
        }

        // Normalize advantages and flaws to strings (they may come as arrays from AI generation)
        foreach (['advantages', 'flaws'] as $key) {
            if (isset($extras[$key]) && is_array($extras[$key])) {
                $extras[$key] = implode("\n", array_filter($extras[$key], 'is_string'));
            }
        }

        return array_filter($extras, function ($item) {
            if (is_array($item)) {
                return !empty(array_filter($item, fn ($value) => $value !== null && $value !== ''));
            }

            return $item !== null && $item !== '';
        });
    }

    protected function normalizeOwnerData($owner, $previousOwner = null): ?array
    {
        $owner = $this->decodeJsonValue($owner);

        if (!is_array($owner)) {
            return null;
        }

        if (!Arr::isAssoc($owner) && isset($owner[0]) && is_array($owner[0])) {
            $owner = $owner[0];
        }

        if (!is_array($owner)) {
            return null;
        }

        $owner = Arr::only($owner, [
            'id',
            'name',
            'first_name',
            'last_name',
            'photo',
            'photo_path',
            'email',
            'phone',
        ]);

        $photoProvided = array_key_exists('photo', $owner);
        $photoValue = $owner['photo'] ?? null;

        $owner = array_filter($owner, function ($item, $key) use ($photoProvided) {
            if (in_array($key, ['photo', 'photo_path'], true)) {
                return true;
            }

            return $item !== null && $item !== '';
        }, ARRAY_FILTER_USE_BOTH);

        if (empty($owner) && !$photoProvided) {
            return null;
        }

        if ($photoProvided) {
            if (is_string($photoValue) && trim($photoValue) === '') {
                unset($owner['photo'], $owner['photo_path']);
            } else {
                $previous = [
                    'url' => $previousOwner['photo'] ?? null,
                    'path' => $previousOwner['photo_path'] ?? null,
                ];

                $imageData = $this->storeImageUsingUploader($photoValue, 'reviews/avatars', $previous, false);

                if ($imageData) {
                    $owner['photo'] = $imageData['url'] ?? null;
                    $owner['photo_path'] = $imageData['path'] ?? null;
                } else {
                    unset($owner['photo'], $owner['photo_path']);
                }
            }
        }

        if (!$photoProvided && $previousOwner) {
            if (isset($previousOwner['photo']) && !isset($owner['photo'])) {
                $owner['photo'] = $previousOwner['photo'];
            }

            if (isset($previousOwner['photo_path']) && !isset($owner['photo_path'])) {
                $owner['photo_path'] = $previousOwner['photo_path'];
            }
        }

        return !empty(array_filter($owner, fn ($item) => $item !== null && $item !== '')) ? $owner : null;
    }

    public function setLangAttribute($value): void
    {
        $this->attributes['lang'] = $this->normalizeLang($value);
    }

    public function setCountryAttribute($value): void
    {
        $this->attributes['country'] = $this->normalizeCountry($value);
    }

    protected function normalizeLang($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(substr((string) trim($value), 0, 5));

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeCountry($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = preg_replace('/[^a-zA-Z]/', '', (string) $value);
        $normalized = strtoupper(substr($cleaned, 0, 2));

        return strlen($normalized) === 2 ? $normalized : null;
    }

    protected function processImagesBeforeSaving(string $attribute, array $images): array
    {
        if ($attribute !== 'photo_gallery') {
            return $this->processImagesBeforeSavingBase($attribute, $images);
        }

        $maxFiles = (int) config('backpack.reviews.photo_review.max_files', 5);

        if (count($images) > $maxFiles) {
            throw ValidationException::withMessages([
                'photo_gallery' => [sprintf('Максимально допустимое количество фото: %d.', $maxFiles)],
            ]);
        }

        $options = static::imageUploadOptions('photo_gallery');
        $prefix = static::imageFieldPrefix('photo_gallery');

        return collect($images)
            ->map(function (array $image) use ($options, $prefix) {
                if (!array_key_exists('src', $image)) {
                    return $image;
                }

                $src = trim((string) $image['src']);
                if ($src === '') {
                    return null;
                }

                if ($this->isBase64ImageBase($src)) {
                    $image['src'] = $this->optimizeReviewPhotoBase64($src);
                }

                return $this->processSingleImageBase($image, clone $options, $prefix);
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function optimizeReviewPhotoBase64(string $dataUri): string
    {
        if (!preg_match('#^data:(?P<mime>[^;]+);base64,(?P<data>.+)$#si', $dataUri, $matches)) {
            throw ValidationException::withMessages([
                'photo_gallery' => ['Некорректный формат изображения.'],
            ]);
        }

        $encoded = preg_replace('/\s+/', '', (string) ($matches['data'] ?? ''));
        $binary = base64_decode($encoded, true);

        if ($binary === false) {
            throw ValidationException::withMessages([
                'photo_gallery' => ['Не удалось декодировать изображение.'],
            ]);
        }

        $maxInputSize = (int) config('backpack.reviews.photo_review.max_input_file_size_kb', 12288) * 1024;
        if ($maxInputSize > 0 && strlen($binary) > $maxInputSize) {
            throw ValidationException::withMessages([
                'photo_gallery' => ['Исходное изображение слишком большое.'],
            ]);
        }

        $maxWidth = (int) config('backpack.reviews.photo_review.max_resolution.width', 1920);
        $maxHeight = (int) config('backpack.reviews.photo_review.max_resolution.height', 1920);
        $quality = (int) config('backpack.reviews.photo_review.jpeg_quality', 84);
        $minQuality = (int) config('backpack.reviews.photo_review.min_jpeg_quality', 60);
        $maxFileSize = (int) config('backpack.reviews.photo_review.max_file_size_kb', 4096) * 1024;

        try {
            $image = ImageManager::gd()->read($binary);
        } catch (Throwable $exception) {
            Log::warning('Failed to process review photo image.', [
                'mime' => $matches['mime'] ?? null,
                'decoded_size' => strlen($binary),
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'photo_gallery' => ['Не удалось обработать изображение.'],
            ]);
        }

        if ($maxWidth > 0 && $maxHeight > 0) {
            $image = $image->scaleDown($maxWidth, $maxHeight);
        }

        $encodedImage = $image->toJpg(quality: $quality);

        while ($maxFileSize > 0 && strlen((string) $encodedImage) > $maxFileSize && $quality > $minQuality) {
            $quality -= 5;
            $encodedImage = $image->toJpg(quality: $quality);
        }

        if ($maxFileSize > 0 && strlen((string) $encodedImage) > $maxFileSize) {
            throw ValidationException::withMessages([
                'photo_gallery' => ['Файл превышает допустимый размер даже после оптимизации.'],
            ]);
        }

        return 'data:image/jpeg;base64,'.base64_encode((string) $encodedImage);
    }

    protected function storeImageUsingUploader($value, string $folder, ?array $previous = null, bool $uploadRemote = true): ?array
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            $candidate = [
                'url' => $value['url'] ?? $value['photo'] ?? $value['src'] ?? null,
                'path' => $value['path'] ?? $value['photo_path'] ?? $value['src_path'] ?? null,
            ];

            if ($candidate['url'] || $candidate['path']) {
                return $candidate;
            }

            $value = $candidate['url'] ?? $candidate['path'] ?? null;
        }

        $options = new ImageUploadOptions(folder: $folder);

        if ($value instanceof SymfonyFile) {
            try {
                $stored = $this->imageUploader()->uploadFromFile($value, $options);
            } catch (Throwable $exception) {
                report($exception);

                return $previous;
            }

            return [
                'url' => $stored->url,
                'path' => $stored->path,
            ];
        }

        if (!is_string($value)) {
            return $previous;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if ($previous && ($value === ($previous['url'] ?? null) || $value === ($previous['path'] ?? null))) {
            return $previous;
        }

        try {
            if (Str::startsWith($value, 'data:image')) {
                $stored = $this->imageUploader()->uploadFromBase64($value, $options);
            } elseif (Str::startsWith($value, ['http://', 'https://'])) {
                if (!$uploadRemote) {
                    return [
                        'url' => $value,
                        'path' => $previous['path'] ?? null,
                    ];
                }

                $stored = $this->imageUploader()->upload($value, $options);
            } else {
                return [
                    'url' => $this->makeUrlFromPath($value),
                    'path' => $value,
                ];
            }
        } catch (Throwable $exception) {
            report($exception);

            return $previous;
        }

        return [
            'url' => $stored->url,
            'path' => $stored->path,
        ];
    }

    protected function imageUploader(): ImageUploader
    {
        return app(ImageUploader::class);
    }

    protected function decodeJsonValue($value)
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    protected function makeUrlFromPath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return url($path);
    }

    protected function getOriginalExtras(): array
    {
        $raw = $this->getOriginal('extras');

        if (!$raw && isset($this->attributes['extras'])) {
            $raw = $this->attributes['extras'];
        }

        $decoded = $this->decodeJsonValue($raw);

        return is_array($decoded) ? $decoded : [];
    }

    // public function setOwnerAttribute($value) {
    //   // dd(['owner', $value]);
    //   // echo 'set';
    // }

    // public function setOwnerIdAttribute($value) {
    //   $extras = $this->extras;
    //   $extras['owner']['id'] = $value;
    //   $this->extras = $extras;
    // }
    
    // public function setExtrasOwnerEmailAttribute($value) {
    //   dd(['ownerEmail', $value]);
    //   $extras = $this->extras;
    //   $extras['owner']['email'] = $value;
    //   $this->extras = $extras;
    // }
    
    // public function setOwnerFullnameAttribute($value) {
    //   dd(['ownerFullname', $value]);
    //   $extras = $this->extras;
    //   $extras['owner']['name'] = $value;
    //   $this->extras = $extras;
    // }

    // public function setExtrasOwnerPhotoAttribute($value) {
    //   $extras = $this->extras;
    //   $extras['owner']['photo'] = $value;
    //   $this->extras = $extras;
    // }
}
