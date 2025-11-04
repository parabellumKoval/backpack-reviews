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
use ParabellumKoval\BackpackImages\Services\ImageUploader;
use ParabellumKoval\BackpackImages\Support\ImageUploadOptions;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use ParabellumKoval\BackpackImages\Traits\HasImages;
use Throwable;

class Review extends Model
{
    use CrudTrait;
    use HasFactory;
    use HasDisplayLabel;
    use HasTranslations;
    use HasImages;
    
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
      'video_url',
      'video_title',
      'video_poster',
      'extras'
    ];
    // protected $hidden = [];
    // protected $dates = [];
	
    // !!!!
	  // protected $with = ['owner'];

    protected $casts = [
      'is_moderated' => 'bool',
      'is_video' => 'bool',
      'extras' => 'array',
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
        "extras" => $this->extras,
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
            $hasVideoContent = !empty($review->video_url)
                || $review->hasVideoTitle()
                || !empty($review->video_poster);

            if ($review->isDirty('is_video')) {
                $review->is_video = (bool) $review->is_video;
            } elseif ($hasVideoContent) {
                $review->is_video = true;
            } else {
                $review->is_video = false;
            }
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
