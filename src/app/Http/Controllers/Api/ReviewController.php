<?php

namespace Backpack\Reviews\app\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

// EXCEPTIONS
use Rd\app\Exceptions\DetailedException;
use \Illuminate\Database\Eloquent\ModelNotFoundException;

use \Rd\app\Traits\RdTrait;

use Backpack\Reviews\app\Models\Review;
use Backpack\Reviews\app\Events\ReviewCreated;

use Backpack\Reviews\app\Services\ReviewTypeResolver;
use Backpack\Reviews\app\Contracts\ReviewableAvailabilityScope;

class ReviewController extends \App\Http\Controllers\Controller
{
  use RdTrait;

  protected $review_model = '';

  public $rd_fields = null;

  public function __construct() {
    $this->review_model = \Settings::get('backpack.reviews.review_model', Review::class);

    // Rd 
    $this->rd_fields = \Settings::get('backpack.reviews.fields');
  }
  
  public function amount(Request $request) {
    $videoOnly = $request->boolean('video');
    $reviewable = $request->input('reviewable_type');
    $typeMap = $this->buildReviewableTypeMap(config('backpack.reviews.morph_aliases', []));
    $reviewableTypes = $this->resolveReviewableTypeVariants($reviewable, $typeMap);
    $filterNullType = $this->isNullReviewableType($reviewable);

    $amount = $this->review_model::query()
          ->select('ak_reviews.*')
          ->distinct('ak_reviews.id')
          ->root()
          ->moderated()
          ->when($filterNullType, function ($query) {
            $query->whereNull('ak_reviews.reviewable_type');
          })
          ->when(!$filterNullType && !empty($reviewableTypes), function ($query) use ($reviewableTypes) {
            if (count($reviewableTypes) === 1) {
              $query->where('ak_reviews.reviewable_type', $reviewableTypes[0]);
            } else {
              $query->whereIn('ak_reviews.reviewable_type', $reviewableTypes);
            }
          })
          ->when($videoOnly, function ($query) {
            $query->where('ak_reviews.is_video', true);
          })
          ->count();

    return $amount;
  }
  /**
   * index
   *
   * @param  mixed $request
   * @return void
  */
  public function index(Request $request) {
    
    $videoOnly = $request->boolean('video');
    $reviewable = $request->input('reviewable_type');
    $typeMap = $this->buildReviewableTypeMap(config('backpack.reviews.morph_aliases', []));
    $reviewableTypes = $this->resolveReviewableTypeVariants($reviewable, $typeMap);
    $filterNullType = $this->isNullReviewableType($reviewable);

    // $reviewable_id = request('reviewable_id');
    // if(request('reviewable_slug') && request('reviewable_type')) {
    //   $reviewable = request('reviewable_type')::where('slug', request('reviewable_slug'))->first();
    //   $reviewable_id = $reviewable? ($reviewable->parent_id ?? $reviewable->id): null;
    // }

    $reviews = $this->review_model::query()
              ->select('ak_reviews.*')
              ->distinct('ak_reviews.id')
              ->root()
              ->moderated()
              // ->when($reviewable_id, function($query) use($reviewable_id){
              //   $query->where('ak_reviews.reviewable_id', $reviewable_id);
              // })
              ->when($filterNullType, function ($query) {
                $query->whereNull('ak_reviews.reviewable_type');
              })
              ->when(!$filterNullType && !empty($reviewableTypes), function ($query) use ($reviewableTypes) {
                if (count($reviewableTypes) === 1) {
                  $query->where('ak_reviews.reviewable_type', $reviewableTypes[0]);
                } else {
                  $query->whereIn('ak_reviews.reviewable_type', $reviewableTypes);
                }
              })
              ->when($videoOnly, function ($query) {
                $query->where('ak_reviews.is_video', true);
              })
              // ->when(!$videoOnly, function ($query) {
              //   $query->where(function ($inner) {
              //     $inner->where('ak_reviews.is_video', false)
              //       ->orWhereNull('ak_reviews.is_video');
              //   });
              // })
              ->orderBy('created_at', 'desc');

    $per_page = request('per_page')? request('per_page'): config('backpack.reviews.per_page', 12);
    $reviews = $reviews->paginate($per_page);

    return config('backpack.reviews.resource.medium', 'Backpack\Reviews\app\Http\Resources\ReviewMediumResource')::collection($reviews);
  }

  /**
   * indexRelation (QB + полиморфная подгрузка через morph_aliases)
   *
   * Цель: максимум производительности — основной запрос через Query Builder,
   * затем гидрация моделей и ручная подстановка полиморфных связей.
   */
  public function indexRelation(Request $request, $collection = null)
  {
      $perPage = $request->input('per_page', config('backpack.reviews.per_page', 12));
      $morphAliases = config('backpack.reviews.morph_aliases', []);
      $globalCountry = config('backpack.reviews.global_country_code');
      $categoryId     = $request->input('category_id');
      $videoOnly = $request->boolean('video');
      $countryCode = $this->resolveCountryCode($request) ?: $globalCountry;
      $countryCode = $countryCode ? mb_strtolower($countryCode) : null;

      $availabilityContext = [
          'country' => $countryCode,
          'request' => $request,
      ];
      $reviewableTypeMap = $this->buildReviewableTypeMap($morphAliases);
      $reviewableTypes = $this->resolveReviewableTypeVariants($request->input('reviewable_type'), $reviewableTypeMap);
      $filterNullType = $this->isNullReviewableType($request->input('reviewable_type'));

      // 1) Разруливаем фильтр по слагу/типу → получаем reviewable_id
      $reviewableId = $request->input('reviewable_id');
      if ($request->filled('reviewable_slug') && $request->filled('reviewable_type')) {
          /** @var \Illuminate\Database\Eloquent\Model|null $reviewable */
          $reviewable = ($request->input('reviewable_type'))::query()
              ->where('slug', $request->input('reviewable_slug'))
              ->first();

          $reviewableId = $reviewable ? ($reviewable->getReviewableKey() ?? $reviewable->parent_id ?? $reviewable->getKey()) : null;
      }

      // 2) Базовый запрос по отзывам — только root+moderated
      $qb = \DB::table('ak_reviews as r')
          ->select('r.*')
          ->where('r.parent_id', 0)           // scope root()
          ->where('r.is_moderated', 1)        // scope moderated()
          ->when($reviewableId, function ($q) use ($reviewableId) {
              $q->where('r.reviewable_id', $reviewableId);
          })
          ->when($filterNullType, function ($q) {
              $q->whereNull('r.reviewable_type');
          })
          ->when(!$filterNullType && !empty($reviewableTypes), function ($q) use ($reviewableTypes) {
              if (count($reviewableTypes) === 1) {
                  $q->where('r.reviewable_type', $reviewableTypes[0]);
              } else {
                  $q->whereIn('r.reviewable_type', $reviewableTypes);
              }
          })
          ->when($videoOnly, function ($q) {
              $q->where('r.is_video', true);
          })
          // ->when(!$videoOnly, function ($q) {
          //     $q->where(function ($inner) {
          //         $inner->where('r.is_video', false)
          //               ->orWhereNull('r.is_video');
          //     });
          // })
          ->orderBy('r.created_at', 'desc');

      $this->applyReviewableAvailabilityFilter($qb, $reviewableTypeMap, $availabilityContext);

      $qb->when($categoryId !== null, function ($q) use ($morphAliases, $categoryId, $countryCode) {
          $q->where(function ($outer) use ($morphAliases, $categoryId, $countryCode) {
              foreach ($morphAliases as $origType => $alias) {
                  $modelClass   = $alias['model']         ?? null;   // напр. Backpack\Store\app\Models\Catalog
                  $joinKey      = $alias['key']           ?? 'id';   // напр. group_id
                  $countryField = $alias['country_field'] ?? null;   // напр. country_code

                  if (!$modelClass || !class_exists($modelClass)) {
                      continue;
                  }

                  $table = (new $modelClass)->getTable(); // напр. ak_catalog

                  // EXISTS по алиас-таблице: сопоставляем r.reviewable_id с cat.{joinKey}
                  $outer->orWhereExists(function ($sub) use ($table, $joinKey, $countryField, $categoryId, $countryCode) {
                      $sub->select(\DB::raw(1))
                          ->from($table . ' as cat')
                          ->whereColumn('cat.' . $joinKey, 'r.reviewable_id');

                      // фильтр по стране (если указан country_field в алиасе)
                      if ($countryField && $countryCode) {
                          $sub->where('cat.' . $countryField, $countryCode);
                      }

                      // JSON-массив category_ids содержит искомый category_id
                      // Делаем оба варианта (int и string) во избежание несовпадений типов.
                      $sub->where(function ($qq) use ($categoryId) {
                          $qq->whereJsonContains('cat.category_ids', (int) $categoryId)
                            ->orWhereJsonContains('cat.category_ids', (string) $categoryId);
                      });
                  });
              }
          });
      });

      $reviewsAvg = (clone $qb)->avg('r.rating');
      $ratingCount = (clone $qb)->whereNotNull('r.rating')->count();

      // 3) Пагинация Query Builder'ом
      /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
      $paginator = $qb->paginate($perPage);

      $rows = $paginator->items(); // stdClass[]
      if (empty($rows)) {
          $resource = config('backpack.reviews.resource.medium', 'Backpack\Reviews\app\Http\Resources\ReviewMediumResource');
          return $resource::collection($paginator);
      }

      // 4) Готовим "корзины" для батч-загрузки полиморфных связей
      //    Учитываем morph_aliases: меняем класс и ключ, если указано в конфиге.
      $buckets = []; // [modelClass => ['key' => string, 'ids' => int[]]]
      foreach ($rows as $row) {
          $origType = $row->reviewable_type;
          $effectiveClass = $origType;
          $effectiveKey   = 'id';

          if (isset($morphAliases[$origType])) {
              $effectiveClass = $morphAliases[$origType]['model'] ?? $origType;
              $effectiveKey   = $morphAliases[$origType]['key']   ?? 'id';
          }

          if (!isset($buckets[$effectiveClass])) {
              $buckets[$effectiveClass] = ['key' => $effectiveKey, 'ids' => []];
          }
          $buckets[$effectiveClass]['ids'][] = $row->reviewable_id;
      }

      // 5) Батч-фетчим связанные записи по каждому классу
      $loaded = []; // [modelClass => Collection keyed by effectiveKey]
      foreach ($buckets as $modelClass => $meta) {
          $key  = $meta['key'];
          $ids  = array_values(array_unique($meta['ids']));

          if (!class_exists($modelClass)) {
              $loaded[$modelClass] = collect();
              continue;
          }

          $query = $modelClass::query()->whereIn($key, $ids);

          // если для этого класса задан country_field в morph_aliases — применяем фильтр
          $aliasConfig = collect($morphAliases)->firstWhere('model', $modelClass);
          $countryField = $aliasConfig['country_field'] ?? null;

          if ($countryField && $countryCode) {
              $query->where($countryField, $countryCode);
          }

          $query = $this->applyAvailabilityScopeToBuilder($modelClass, $query, $availabilityContext);

          $loaded[$modelClass] = $query->get()->keyBy($key);
      }


      // 6) Гидрируем отзывы в Eloquent модели и прикрепляем relation 'reviewable'
      /** @var \Illuminate\Database\Eloquent\Collection $hydrated */
      $hydrated = $this->review_model::hydrate(array_map('get_object_vars', $rows));

      // Быстрый доступ к stdClass по id для синхронизации атрибутов
      $rowById = [];
      foreach ($rows as $r) {
          $rowById[$r->id] = $r;
      }

      foreach ($hydrated as $review) {
          $r = $rowById[$review->id] ?? null;
          if (!$r) continue;

          $origType = $r->reviewable_type;
          $effectiveClass = $origType;
          $effectiveKey   = 'id';

          if (isset($morphAliases[$origType])) {
              $effectiveClass = $morphAliases[$origType]['model'] ?? $origType;
              $effectiveKey   = $morphAliases[$origType]['key']   ?? 'id';
          }

          $relModel = $loaded[$effectiveClass][$r->reviewable_id] ?? null;

          // Восстанавливаем полиморфную связь "reviewable" (без лишних запросов)
          if ($relModel) {
              $review->setRelation('reviewable', $relModel);
          } else {
              // Чтобы не триггерить ленивую загрузку при сериализации
              $review->setRelation('reviewable', null);
          }
      }

      // 7) Подменяем коллекцию в пагинаторе на гидрированную
      $paginator->setCollection($hydrated);

      $resource = config('backpack.reviews.resource.medium', 'Backpack\Reviews\app\Http\Resources\ReviewMediumResource');
      // 8) Возвращаем ресурсы как и раньше
      if($collection) {
        return new $collection($paginator, ['resource' => $resource, 'rating_count' => $ratingCount, 'reviews_avg' => $reviewsAvg]);
      }else {
        return $resource::collection($paginator);
      }
  }

  protected function buildReviewableTypeMap(array $morphAliases): array
  {
    $map = [];
    $configuredTypes = config('backpack.reviews.reviewable_types_list', []);

    foreach ($configuredTypes as $definition) {
      $modelClass = $definition['model'] ?? null;
      if (!$modelClass) {
        continue;
      }

      $map[$modelClass] = [
        'model' => $modelClass,
        'key' => $definition['key'] ?? 'id',
        'country_field' => null,
      ];
    }

    foreach ($morphAliases as $reviewableType => $alias) {
      $aliasModel = $alias['model'] ?? null;
      if (!$aliasModel) {
        continue;
      }

      $map[$reviewableType] = [
        'model' => $aliasModel,
        'key' => $alias['key'] ?? 'id',
        'country_field' => $alias['country_field'] ?? null,
      ];
    }

      return $map;
  }

  protected function resolveReviewableTypeVariants($type, array $typeMap): array
  {
    if ($this->isNullReviewableType($type)) {
      return [];
    }

    $normalized = ReviewTypeResolver::normalizeMorphClass((string) $type);
    $variants = array_filter([(string) $type, $normalized]);
    $targetModel = $typeMap[$type]['model'] ?? null;

    foreach ($typeMap as $candidateType => $meta) {
      if ($candidateType === $type) {
        continue;
      }

      $candidateModel = $meta['model'] ?? null;

      if ($targetModel && $candidateModel === $targetModel) {
        $variants[] = $candidateType;
        continue;
      }

      if ($normalized && ReviewTypeResolver::normalizeMorphClass((string) $candidateType) === $normalized) {
        $variants[] = $candidateType;
      }
    }

    return array_values(array_unique(array_filter($variants)));
  }

  protected function isNullReviewableType($type): bool
  {
    return $type === null || $type === '' || $type === 'null';
  }

  protected function applyReviewableAvailabilityFilter(QueryBuilder $query, array $typeMap, array $context): void
  {
    if (empty($typeMap)) {
      return;
    }

    $countryCode = $context['country'] ?? null;
    $countryCode = $countryCode ? mb_strtolower($countryCode) : null;

    $constraints = [];
    foreach ($typeMap as $reviewableType => $meta) {
      $constraint = $this->makeReviewableConstraint($reviewableType, $meta, $countryCode, $context);
      if ($constraint) {
        $constraints[$reviewableType] = $constraint;
      }
    }

    if (empty($constraints)) {
      return;
    }

    $query->where(function (QueryBuilder $outer) use ($constraints) {
      $knownTypes = array_keys($constraints);

      if (!empty($knownTypes)) {
        $outer->whereNotIn('r.reviewable_type', $knownTypes);
      }

      foreach ($constraints as $type => $constraint) {
        $outer->orWhere(function (QueryBuilder $branch) use ($type, $constraint) {
          $branch->where('r.reviewable_type', $type);
          $constraint($branch);
        });
      }
    });
  }

  protected function makeReviewableConstraint(string $reviewableType, array $meta, ?string $countryCode, array $context): ?callable
  {
    $modelClass = $meta['model'] ?? null;
    if (!$modelClass || !class_exists($modelClass)) {
      return null;
    }

    $builder = $modelClass::query();
    $model = $builder->getModel();
    $table = $model->getTable();

    if (!Schema::hasTable($table)) {
      return null;
    }

    $key = $meta['key'] ?? $model->getKeyName();
    $countryField = $meta['country_field'] ?? null;

    $builder->select("{$table}.{$key}");

    if ($countryField && $countryCode) {
      $builder->where("{$table}.{$countryField}", $countryCode);
    }

    $builder = $this->applyAvailabilityScopeToBuilder($modelClass, $builder, $context);

    $alias = 'rel_' . substr(md5($reviewableType . $key), 0, 8);

    return function (QueryBuilder $branch) use ($builder, $key, $alias) {
      $branch->whereExists(function (QueryBuilder $exists) use ($builder, $key, $alias) {
        $exists->selectRaw('1')
          ->fromSub(clone $builder, $alias)
          ->whereColumn("{$alias}.{$key}", 'r.reviewable_id');
      });
    };
  }

  protected function applyAvailabilityScopeToBuilder(string $modelClass, EloquentBuilder $builder, array $context): EloquentBuilder
  {
    if (!$this->modelSupportsAvailabilityScope($modelClass)) {
      return $builder;
    }

    return $builder->reviewableAvailability($context);
  }

  protected function modelSupportsAvailabilityScope(string $modelClass): bool
  {
    if (!class_exists($modelClass)) {
      return false;
    }

    $interfaces = class_implements($modelClass) ?: [];
    if (in_array(ReviewableAvailabilityScope::class, $interfaces, true)) {
      return true;
    }

    return method_exists($modelClass, 'scopeReviewableAvailability');
  }

  protected function resolveCountryCode(Request $request): ?string
  {
    $input = $this->normalizeCountry($request->input('country'));
    if ($input) {
      return $input;
    }

    $headerCountry = $request->header('X-Region') ?? $request->header('X-Country');

    return $this->normalizeCountry($headerCountry);
  }

  protected function normalizeCountry(?string $value): ?string
  {
    if (!$value) {
      return null;
    }

    $cleaned = preg_replace('/[^a-zA-Z]/', '', $value);
    $code = strtoupper(substr($cleaned, 0, 2));

    return strlen($code) === 2 ? $code : null;
  }


 /** 
 * Create new review
 *
 * @param Request $request
 * @return Review
 **/

  public function create(Request $request) {
    try {
      // Validate data using RdTrait validation method
      $data = $this->validateData($request);
    } catch(DetailedException $e) {
      return response()->json([
        'message' => $e->getMessage(),
        'options' => $e->getOptions()
      ], $e->getCode());
    }

    $photoRules = [
      'review_type' => ['nullable', Rule::in(['text', 'video', 'photo'])],
      'photo_gallery' => ['nullable', 'array', 'max:' . (int) config('backpack.reviews.photo_review.max_files', 5)],
      'photo_gallery.*.src' => ['required_with:photo_gallery', 'string'],
      'photo_gallery.*.alt' => ['nullable', 'string', 'max:255'],
      'photo_gallery.*.title' => ['nullable', 'string', 'max:255'],
      'photo_gallery.*.size' => ['nullable', Rule::in(['cover', 'contain'])],
    ];

    $photoValidator = Validator::make($request->all(), $photoRules);
    if ($photoValidator->fails()) {
      return response()->json([
        'message' => 'Data Validation Error',
        'options' => $photoValidator->errors()->toArray(),
      ], 422);
    }

    // Create new model
    $review = new $this->review_model();

    // Fill model with data using RdTrait
    $review = $this->setRequestFields($review, $data);
    $review = $this->applyReviewContentType($review, $data);

    //
    $review = $this->moderationPolicy($review);

    // SET EXTRAS FROM REQUEST
    try {
      [$owner_id, $owner_model] = $this->getUserData($data);
      $review->owner_id = $owner_id;

      if($owner_model) {
        $review->extras = $this->addToExtras($review->extras, 'owner', $owner_model->toReviewArray());
      }
    }catch(\Exception $e) {
      return response()->json($e->getMessage(), $e->getCode());
    }

    // CREATE REVIEW
    try {
      // Save order
      $review->save();
      ReviewCreated::dispatch($review);
    }catch(\Throwable $e) {
      $status = $e->getCode();
      if (!is_int($status) || $status < 400 || $status > 599) {
        $status = 422;
      }

      return response()->json($e->getMessage(), $status);
    }

    return response()->json($review);
  }
     
  /**
   * moderationPolicy
   *
   * @param  mixed $model
   * @return void
   */
  protected function moderationPolicy($model) {
    if(!ReviewTypeResolver::withModeration($model)) {
      $model->is_moderated = true;
    }else {
      $model->is_moderated = false;
    }

    return $model;
  }

  protected function applyReviewContentType($review, array $data)
  {
    $requestedType = isset($data['review_type'])
      ? strtolower((string) $data['review_type'])
      : null;

    if (!in_array($requestedType, ['text', 'video', 'photo'], true)) {
      $requestedType = !empty($data['is_video']) ? 'video' : null;
    }

    if ($requestedType === null) {
      if (!empty($data['photo_gallery']) && is_array($data['photo_gallery'])) {
        $requestedType = 'photo';
      } elseif (!empty($data['video_url'])) {
        $requestedType = 'video';
      } else {
        $requestedType = 'text';
      }
    }

    $review->review_type = $requestedType;
    $review->is_video = $requestedType === 'video';

    if ($requestedType !== 'video') {
      $review->video_url = null;
      $review->video_title = [];
      $review->video_poster = [];
    }

    if ($requestedType !== 'photo') {
      $review->photo_gallery = [];
    }

    return $review;
  }
  
  /**
   * addToExtras
   *
   * @param  mixed $extras
   * @param  mixed $key
   * @param  mixed $data
   * @return void
   */
  protected function addToExtras(array $extras, string $key, array $data) {
    // add new data
    $extras[$key] = $data;

    return $extras;
  } 

  /**
   * getUserData
   *
   * @param  mixed $data
   * @return void
   */
  protected function getUserData(array $data = null) {
    
    // INIT OWNER MODEL
    $owner = [
      'id' => null,
      'model' => null
    ];

    // Set owner by id
    if($data['provider'] === 'id')
    {
      try{
        $class = config('backpack.reviews.owner_model', 'Backpack\Profile\app\Models\Profile');
        $owner_model = $class::findOrFail((int)$data['owner']['id']);
      }catch(ModelNotFoundException $e) {
        throw new \Exception($e->getMessage(), $e->getCode());
      }

      $owner = [
        'id' => $owner_model->id,
        'model' => $owner_model
      ];

    }

    // Set owner from auth session
    else if($data['provider'] === 'auth') 
    {
      // $auth_guard = config('backpack.reviews.auth_guard', 'profile');
      $auth_guard = 'sanctum';

      if(!Auth::guard($auth_guard)->check()){
        throw new \Exception('User not authenticated', 401);
      }

      $owner_model = Auth::guard($auth_guard)->user();

      $owner = [
        'id' => $owner_model->id,
        'model' => $owner_model
      ];
    }

    // Set owner by exterior data
    else if($data['provider'] === 'data')
    {
      $owner = [
        'id' => null,
        'model' => null
      ];
    }

    return [$owner['id'], $owner['model']];
  }

  /**
   * likeOrDislike
   *
   * @param  mixed $request
   * @param  mixed $id
   * @return void
   */
  public function likeOrDislike(Request $request, $id) {
    $data = $request->only(['direction', 'type']);

    $validator = Validator::make($data, [
      'direction' => [ 
        'nullable',
        Rule::in(['minus', 'plus'])
      ],
      'type' => [
        'required',
        Rule::in(['likes', 'dislikes'])
      ]
    ]);

    $dir = isset($data['direction'])? $data['direction']: 'plus';

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    // if(!Auth::guard(config('backpack.reviews.auth_guard', 'profile'))->check()){
    //   return response()->json('User not authenticated', 401);
    // }

    try {
      $review_model = $this->review_model::findOrFail($id);
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }
    
    if($dir === 'plus')
      $review_model->{$data['type']} = $review_model->{$data['type']} + 1;
    else
      $review_model->{$data['type']} = $review_model->{$data['type']} > 0? $review_model->{$data['type']} - 1: 0;


    $review_model->save();

    return response()->json([
        $data['type'] => $review_model->{$data['type']}
    ]);
  }

}
