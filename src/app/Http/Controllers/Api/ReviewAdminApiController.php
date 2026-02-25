<?php

namespace Backpack\Reviews\app\Http\Controllers\Api;

use Backpack\Reviews\app\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReviewAdminApiController extends Controller
{
    protected array $reviewableDefinitions = [];
    protected bool $reviewableDefinitionsLoaded = false;

    protected function assertCanModerate(): void
    {
        $callback = config('backpack.reviews.can_moderate');

        if (is_callable($callback)) {
            abort_unless(call_user_func($callback), 403);
        }
    }

    protected function reviewModelClass(): string
    {
        return config('backpack.reviews.review_model', Review::class) ?: Review::class;
    }

    protected function newReviewInstance(): Review
    {
        $class = $this->reviewModelClass();

        return new $class();
    }

    protected function resolveReviewable(string $type, int $id): Model
    {
        $class = class_exists($type)
            ? $type
            : (method_exists(Relation::class, 'getMorphedModel')
                ? Relation::getMorphedModel($type)
                : null);

        if (!$class) {
            $map = Relation::morphMap() ?: [];
            $class = $map[$type] ?? null;
        }

        abort_unless($class && class_exists($class), 404, 'Reviewable class not found');

        $model = $this->findReviewableByConfiguredKey($class, $id);
        abort_unless(method_exists($model, 'reviews'), 400, 'Model is not reviewable');

        return $model;
    }

    protected function reviewableDefinitions(): array
    {
        if (! $this->reviewableDefinitionsLoaded) {
            $definitions = \Settings::get('backpack.reviews.reviewable_types_list', []);
            $normalized = [];

            foreach ($definitions as $definition) {
                $model = $definition['model'] ?? null;
                $class = $this->normalizeReviewableClass($model);

                if (!$class) {
                    continue;
                }

                $definition['model'] = $class;
                $normalized[$class] = $definition;
            }

            $this->reviewableDefinitions = $normalized;
            $this->reviewableDefinitionsLoaded = true;
        }

        return $this->reviewableDefinitions;
    }

    protected function findReviewableDefinition(string $class): ?array
    {
        $normalized = $this->normalizeReviewableClass($class);

        if (!$normalized) {
            return null;
        }

        return $this->reviewableDefinitions()[$normalized] ?? null;
    }

    protected function findReviewableByConfiguredKey(string $class, int $identifier): Model
    {
        $normalizedClass = $this->normalizeReviewableClass($class) ?? $class;
        $definition = $this->findReviewableDefinition($normalizedClass);

        /** @var \Illuminate\Database\Eloquent\Model $modelInstance */
        $modelInstance = new $normalizedClass();
        $keyColumn = $definition['reviewable_key'] ?? $modelInstance->getKeyName();

        if ($keyColumn !== $modelInstance->getKeyName()) {
            $record = $normalizedClass::query()->where($keyColumn, $identifier)->first();

            if ($record) {
                return $record;
            }
        }

        return $normalizedClass::query()->findOrFail($identifier);
    }

    protected function determineReviewableIdentifier(Model $model)
    {
        if (method_exists($model, 'getReviewableKey')) {
            $customKey = $model->getReviewableKey();

            if ($customKey !== null) {
                return $customKey;
            }
        }

        $definition = $this->findReviewableDefinition(get_class($model));

        if ($definition && !empty($definition['reviewable_key'])) {
            $attribute = $model->getAttribute($definition['reviewable_key']);

            if ($attribute !== null && $attribute !== '') {
                return $attribute;
            }
        }

        return $model->getKey();
    }

    protected function normalizeReviewableClass(?string $class): ?string
    {
        if (!$class) {
            return null;
        }

        return ltrim($class, '\\');
    }

    public function index(string $type, int $id)
    {
        $this->assertCanModerate();

        $reviewable = $this->resolveReviewable($type, $id);
        $reviewModel = $this->reviewModelClass();

        $items = $reviewModel::query()
            ->where('reviewable_type', $reviewable->getMorphClass())
            ->where('reviewable_id', $this->determineReviewableIdentifier($reviewable))
            ->with('user')
            ->orderBy('lft')
            ->get()
            ->map(fn (Review $review) => $this->transformReview($review));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $this->assertCanModerate();

        $data = $this->validateReviewPayload($request, true);
        $reviewable = $this->resolveReviewable($data['reviewable_type'], (int) $data['reviewable_id']);

        $data['reviewable_type'] = $reviewable->getMorphClass();
        $data['reviewable_id'] = $this->determineReviewableIdentifier($reviewable);

        $review = $this->createReviewRecord($data);

        return response()->json(['data' => $this->transformReview($review)], 201);
    }

    public function reply(Request $request, Review $review)
    {
        $this->assertCanModerate();

        $maxDepth = (int) config('backpack.reviews.max_depth', 3);
        abort_if($review->depth >= $maxDepth, 422, 'Max depth reached');

        $data = $this->validateReviewPayload($request, false);
        $data['reviewable_type'] = $review->reviewable_type;
        $data['reviewable_id'] = $review->reviewable_id;
        $data['parent_id'] = $review->id;

        $reply = $this->createReviewRecord($data);

        return response()->json(['data' => $this->transformReview($reply)], 201);
    }

    public function update(Request $request, Review $review)
    {
        $this->assertCanModerate();

        $data = $this->validateReviewPayload($request, false);

        $this->fillReview($review, $data);
        $review->save();

        return response()->json(['data' => $this->transformReview($review->fresh())]);
    }

    public function destroy(Review $review)
    {
        $this->assertCanModerate();

        DB::transaction(function () use ($review) {
            $review::query()->where('parent_id', $review->id)->delete();
            $review->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function toggleModeration(Review $review)
    {
        $this->assertCanModerate();

        $review->is_moderated = ! $review->is_moderated;
        $review->save();

        return response()->json(['data' => $review]);
    }

    public function like(Review $review)
    {
        $this->assertCanModerate();
        abort_unless(config('backpack.reviews.enable_likes'), 403);

        $review->increment('likes');

        return response()->json(['data' => $review->only(['id', 'likes', 'dislikes'])]);
    }

    public function dislike(Review $review)
    {
        $this->assertCanModerate();
        abort_unless(config('backpack.reviews.enable_likes'), 403);

        $review->increment('dislikes');

        return response()->json(['data' => $review->only(['id', 'likes', 'dislikes'])]);
    }

    public function toggleIsModeratedRouter(Review $review)
    {
        $this->assertCanModerate();

        $review->is_moderated = request()->input('is_active', 0);
        $review->save();

        return response()->json(['success' => true]);
    }

    public function owners(Request $request)
    {
        $this->assertCanModerate();

        $class = config('backpack.reviews.owner_model');
        abort_if(!$class || !class_exists($class), 404, 'Owner model is not configured');

        $query = $class::query();
        if (method_exists($class, 'profile')) {
            $query->with('profile');
        }

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search, $class) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");

                if (method_exists($class, 'profile')) {
                    $builder->orWhereHas('profile', function ($profileQuery) use ($search) {
                        $profileQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
                }
            });
        }

        $owners = $query->orderByDesc($query->getModel()->getKeyName())
            ->limit(20)
            ->get();

        $data = $owners->map(function ($owner) {
            $profile = method_exists($owner, 'relationLoaded') && $owner->relationLoaded('profile')
                ? $owner->getRelation('profile')
                : null;

            $profileName = $profile
                ? trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? ''))
                : null;

            $labelParts = array_filter([
                $owner->name ?? null,
                $profileName ?: null,
                $owner->email ?? null,
            ]);

            $label = $labelParts ? implode(' • ', array_unique($labelParts)) : '#'.$owner->getKey();

            return [
                'id' => $owner->getKey(),
                'text' => $label,
                'name' => $label,
                'email' => $owner->email ?? null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    protected function validateReviewPayload(Request $request, bool $requireReviewable): array
    {
        $reviewType = $this->resolveRequestedReviewType($request);

        $rules = [
            'text'            => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $reviewType === 'text'),
            ],
            'rating'          => ['nullable', 'integer', 'min:1', 'max:5'],
            'extras'          => ['nullable', 'array'],
            'is_moderated'    => ['nullable', 'boolean'],
            'owner_mode'      => ['nullable', 'in:profile,guest'],
            'owner_id'        => ['nullable', 'integer'],
            'guest_name'      => ['nullable', 'string', 'max:255'],
            'guest_email'     => ['nullable', 'email', 'max:255'],
            'guest_phone'     => ['nullable', 'string', 'max:255'],
            'verified_purchase' => ['nullable', 'boolean'],
            'advantages'      => ['nullable', 'string'],
            'flaws'           => ['nullable', 'string'],
            'likes'           => ['nullable', 'integer', 'min:0'],
            'dislikes'        => ['nullable', 'integer', 'min:0'],
            'review_type'     => ['nullable', Rule::in(['text', 'video', 'photo'])],
            'is_video'        => ['nullable', 'boolean'],
            'video_url'       => ['nullable', 'url', 'max:2048', Rule::requiredIf(fn () => $reviewType === 'video')],
            'video_title'     => ['nullable'],
            'video_poster'    => ['nullable'],
            'photo_gallery'   => ['nullable', 'array', 'max:' . (int) config('backpack.reviews.photo_review.max_files', 5), Rule::requiredIf(fn () => $reviewType === 'photo')],
            'photo_gallery.*.src' => ['required_with:photo_gallery', 'string'],
            'lang'            => ['nullable', 'string', 'min:2', 'max:5'],
            'country'         => ['nullable', 'string', 'size:2'],
        ];

        if ($requireReviewable) {
            $rules['reviewable_type'] = ['required', 'string'];
            $rules['reviewable_id']   = ['required', 'integer'];
        }

        return $request->validate($rules);
    }

    protected function resolveRequestedReviewType(Request $request): string
    {
        $type = strtolower((string) $request->input('review_type', ''));
        if (in_array($type, ['text', 'video', 'photo'], true)) {
            return $type;
        }

        if ($request->boolean('is_video')) {
            return 'video';
        }

        if ($request->filled('photo_gallery')) {
            return 'photo';
        }

        return 'text';
    }

    protected function createReviewRecord(array $data): Review
    {
        $review = $this->newReviewInstance();
        $review->reviewable_type = $data['reviewable_type'];
        $review->reviewable_id = $data['reviewable_id'];
        $review->parent_id = $data['parent_id'] ?? null;

        $this->fillReview($review, $data);
        $review->save();

        return $review->fresh();
    }

    protected function fillReview(Review $review, array $data): void
    {
        $review->text = $data['text'];
        $review->rating = $data['rating'] ?? null;

        if (array_key_exists('is_moderated', $data)) {
            $review->is_moderated = (bool) $data['is_moderated'];
        }

        if (array_key_exists('likes', $data)) {
            $review->likes = (int) $data['likes'];
        }

        if (array_key_exists('dislikes', $data)) {
            $review->dislikes = (int) $data['dislikes'];
        }

        if (array_key_exists('is_video', $data)) {
            $review->is_video = (bool) $data['is_video'];
        }

        if (array_key_exists('review_type', $data)) {
            $review->review_type = $data['review_type'];
        }

        if (array_key_exists('video_url', $data)) {
            $review->video_url = $data['video_url'];
        }

        if (array_key_exists('video_title', $data)) {
            $review->video_title = $data['video_title'];
        }

        if (array_key_exists('video_poster', $data)) {
            $review->video_poster = $data['video_poster'];
        }

        if (array_key_exists('photo_gallery', $data)) {
            $review->photo_gallery = $data['photo_gallery'];
        }

        $effectiveType = $review->resolveReviewType($data['review_type'] ?? null);
        $review->review_type = $effectiveType;
        $review->is_video = $effectiveType === 'video';

        if ($effectiveType !== 'video') {
            $review->video_url = null;
            $review->video_title = [];
            $review->video_poster = [];
        }

        if ($effectiveType !== 'photo') {
            $review->photo_gallery = [];
        }

        if (array_key_exists('lang', $data)) {
            $review->lang = $data['lang'];
        }

        if (array_key_exists('country', $data)) {
            $review->country = $data['country'];
        }

        $ownerModel = $this->resolveOwnerModel($data);

        if ($ownerModel) {
            $review->owner_id = $ownerModel->getKey();
        } elseif (($data['owner_mode'] ?? null) === 'guest') {
            $review->owner_id = null;
        }

        $review->extras = $this->buildExtras($data, $review, $ownerModel);
    }

    protected function resolveOwnerModel(array $data): ?Model
    {
        $ownerMode = $data['owner_mode'] ?? null;
        $ownerId = $data['owner_id'] ?? null;

        if ($ownerMode === 'profile') {
            abort_unless($ownerId, 422, trans('reviews::field.owner_required'));

            $owner = $this->findOwnerModel((int) $ownerId);
            abort_unless($owner, 422, trans('reviews::field.owner_required'));

            return $owner;
        }

        if ($ownerId) {
            return $this->findOwnerModel((int) $ownerId);
        }

        return null;
    }

    protected function findOwnerModel(?int $ownerId): ?Model
    {
        if (!$ownerId) {
            return null;
        }

        $class = config('backpack.reviews.owner_model');
        if (!$class || !class_exists($class)) {
            return null;
        }

        return $class::query()->find($ownerId);
    }

    protected function buildExtras(array $data, Review $review, ?Model $owner = null): array
    {
        $extras = is_array($review->extras) ? $review->extras : [];

        if (isset($data['extras']) && is_array($data['extras'])) {
            $extras = array_merge($extras, $data['extras']);
        }

        foreach (['advantages', 'flaws'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if ($value !== null && $value !== '') {
                    $extras[$key] = $value;
                } else {
                    unset($extras[$key]);
                }
            }
        }

        if (array_key_exists('verified_purchase', $data)) {
            $extras['verified_purchase'] = (bool) $data['verified_purchase'];
        }

        $ownerMode = $data['owner_mode'] ?? null;

        if ($ownerMode === 'guest') {
            $guestOwner = array_filter([
                'name' => $data['guest_name'] ?? null,
                'email' => $data['guest_email'] ?? null,
                'phone' => $data['guest_phone'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            if (!empty($guestOwner)) {
                $extras['owner'] = $guestOwner;
            } else {
                unset($extras['owner']);
            }
        } elseif ($owner) {
            $extras['owner'] = $this->ownerArrayFromModel($owner);
        }

        return $extras;
    }

    protected function ownerArrayFromModel(Model $owner): array
    {
        if (method_exists($owner, 'toReviewArray')) {
            return $owner->toReviewArray();
        }

        $name = $owner->name ?? null;
        $firstName = $owner->first_name ?? null;
        $lastName = $owner->last_name ?? null;
        $fullName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

        return array_filter([
            'id' => $owner->getKey(),
            'name' => $name ?: ($fullName ?: null),
            'email' => $owner->email ?? null,
            'phone' => $owner->phone ?? null,
            'photo' => $owner->avatar ?? (method_exists($owner, 'avatarUrl') ? $owner->avatarUrl() : null),
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected function extrasArray(Review $review): array
    {
        $extras = $review->extras;

        if (is_array($extras)) {
            return $extras;
        }

        if (is_string($extras)) {
            $decoded = json_decode($extras, true);

            return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function reviewOwnerPayload(Review $review, array $extras = []): ?array
    {
        $owner = $review->ownerModelOrInfo ?? null;

        if ($owner instanceof Model) {
            $payload = $this->ownerArrayFromModel($owner);

            return !empty($payload) ? $payload : null;
        }

        $raw = $owner ?? ($extras['owner'] ?? null);
        if (is_array($raw)) {
            $payload = array_filter([
                'id' => $raw['id'] ?? null,
                'name' => $raw['name'] ?? ($raw['first_name'] ?? null),
                'email' => $raw['email'] ?? null,
                'phone' => $raw['phone'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            return !empty($payload) ? $payload : null;
        }

        return null;
    }

    protected function transformReview(Review $review): array
    {
        $extras = $this->extrasArray($review);

        return [
            'id' => $review->id,
            'parent_id' => $review->parent_id,
            'depth' => $review->depth,
            'text' => $review->text,
            'rating' => $review->rating,
            'is_moderated' => (bool) $review->is_moderated,
            'likes' => (int) $review->likes,
            'dislikes' => (int) $review->dislikes,
            'advantages' => $extras['advantages'] ?? null,
            'flaws' => $extras['flaws'] ?? null,
            'verified_purchase' => (bool) ($extras['verified_purchase'] ?? false),
            'owner' => $this->reviewOwnerPayload($review, $extras),
            'video' => $review->videoData(true),
            'is_video' => (bool) $review->is_video,
            'review_type' => $review->resolveReviewType(),
            'photos' => $review->photoGalleryForApi(),
            'lang' => $review->lang,
            'country' => $review->country,
            'created_at' => optional($review->created_at)->toDateTimeString(),
            'updated_at' => optional($review->updated_at)->toDateTimeString(),
        ];
    }
}
