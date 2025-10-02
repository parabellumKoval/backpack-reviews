<?php

namespace Backpack\Reviews\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Backpack\Reviews\app\Models\Review;

class ReviewAdminApiController extends Controller
{
    protected function assertCanModerate()
    {
        $callback = config('backpack.reviews.can_moderate');
        
        if (is_callable($callback)) {
            abort_unless(call_user_func($callback), 403);
        }
        
        // По умолчанию разрешаем модерацию для авторизованных админов
        return true;
    }

    // protected function resolveReviewable(string $type, int $id): Model
    // {
    //     // Преобразуем aliased FQN из morphMap если есть
    //     $map = array_flip(\Illuminate\Database\Eloquent\Relations\Relation::morphMap() ?? []);
    //     $class = $map[$type] ?? $type;
    //     abort_unless(class_exists($class), 404, 'Reviewable class not found');

    //     $model = $class::query()->findOrFail($id);
    //     abort_unless(method_exists($model, 'reviews'), 400, 'Model is not reviewable');
    //     return $model;
    // }
    protected function resolveReviewable(string $type, int $id): Model
    {
        // $type может быть alias из morphMap или FQCN
        $class = class_exists($type)
            ? $type
            : (method_exists(Relation::class, 'getMorphedModel')
                ? (Relation::getMorphedModel($type) ?: null)
                : null);

        if (!$class) {
            $map = Relation::morphMap() ?: [];
            $class = $map[$type] ?? null;
        }

        abort_unless($class && class_exists($class), 404, 'Reviewable class not found');

        $model = $class::query()->findOrFail($id);
        abort_unless(method_exists($model, 'reviews'), 400, 'Model is not reviewable');
        return $model;
    }

    public function index(string $type, int $id)
    {
        $this->assertCanModerate();
        $reviewModel = config('backpack.reviews.review_model', Review::class);

        $reviewable = $this->resolveReviewable($type, $id);

        $query = $reviewModel::query()
            ->where('reviewable_type', $reviewable->getMorphClass())
            ->where('reviewable_id', $reviewable->getKey())
            ->orderBy('lft'); // предполагаем nested set (или по created_at)

        $items = $query->get()->map(fn($r) => [
            'id'           => $r->id,
            'parent_id'    => $r->parent_id,
            'depth'        => $r->depth,
            'text'         => $r->text,
            'rating'       => $r->rating,
            'is_moderated' => (bool)$r->is_moderated,
            'likes'        => $r->likes,
            'dislikes'     => $r->dislikes,
            'extras'       => $r->extras,
            'created_at'   => $r->created_at?->toDateTimeString(),
            'updated_at'   => $r->updated_at?->toDateTimeString(),
        ]);

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $this->assertCanModerate();
        $reviewModel = config('backpack.reviews.review_model', Review::class);

        $data = $request->validate([
            'reviewable_type' => 'required|string',
            'reviewable_id'   => 'required|integer',
            'text'            => 'required|string',
            'rating'          => 'nullable|integer|min:1|max:5',
            'extras'          => 'nullable|array',
            'is_moderated'    => 'nullable|boolean',
        ]);

        /** @var Review $review */
        $review = $reviewModel::create([
            'reviewable_type' => $data['reviewable_type'],
            'reviewable_id'   => $data['reviewable_id'],
            'text'            => $data['text'],
            'rating'          => $data['rating'] ?? null,
            'extras'          => $data['extras'] ?? null,
            'is_moderated'    => (bool)($data['is_moderated'] ?? false),
        ]);

        // если используете nested set — вставьте в конец
        // $review->makeRoot(); или пересчёт lft/rgt по своей логике

        return response()->json(['data' => $review], 201);
    }

    public function reply(Request $request, Review $review)
    {
        $this->assertCanModerate();

        $maxDepth = (int)config('backpack.reviews.max_depth', 3);

        $payload = $request->validate([
            'text'         => 'required|string',
            'rating'       => 'nullable|integer|min:1|max:5',
            'extras'       => 'nullable|array',
            'is_moderated' => 'nullable|boolean',
        ]);

        abort_if($review->depth >= $maxDepth, 422, 'Max depth reached');

        /** @var Review $reply */
        $reply = $review->replicate(['id', 'text', 'rating', 'extras', 'likes', 'dislikes', 'is_moderated', 'parent_id', 'lft', 'rgt', 'depth', 'created_at', 'updated_at']);
        $reply->text = $payload['text'];
        $reply->rating = $payload['rating'] ?? null;
        $reply->extras = $payload['extras'] ?? null;
        $reply->is_moderated = (bool)($payload['is_moderated'] ?? true);
        $reply->parent_id = $review->id;
        $reply->lft = 0; $reply->rgt = 0; $reply->depth = $review->depth + 1;
        $reply->setAttribute('reviewable_type', $review->reviewable_type);
        $reply->setAttribute('reviewable_id', $review->reviewable_id);
        $reply->save();

        // пересчёт дерева при необходимости

        return response()->json(['data' => $reply], 201);
    }

    public function update(Request $request, Review $review)
    {
        $this->assertCanModerate();

        $data = $request->validate([
            'text'         => 'required|string',
            'rating'       => 'nullable|integer|min:1|max:5',
            'extras'       => 'nullable|array',
            'is_moderated' => 'nullable|boolean',
        ]);

        $review->update([
            'text'         => $data['text'],
            'rating'       => $data['rating'] ?? null,
            'extras'       => $data['extras'] ?? null,
            'is_moderated' => (bool)($data['is_moderated'] ?? $review->is_moderated),
        ]);

        return response()->json(['data' => $review]);
    }

    public function destroy(Review $review)
    {
        $this->assertCanModerate();

        DB::transaction(function () use ($review) {
            // если nested set — удаляем поддерево; если adjacency — удаляем детей вручную
            Review::where('parent_id', $review->id)->delete();
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
        return response()->json(['data' => $review->only(['id','likes','dislikes'])]);
    }

    public function dislike(Review $review)
    {
        $this->assertCanModerate();
        abort_unless(config('backpack.reviews.enable_likes'), 403);
        $review->increment('dislikes');
        return response()->json(['data' => $review->only(['id','likes','dislikes'])]);
    }



    /**
     * Method toggleIsModeratedRouter
     *
     * @param $id $id [explicite description]
     *
     * @return void
     */
    public function toggleIsModeratedRouter(Review $review)
    {
        $this->assertCanModerate();

        $review->is_moderated = request()->input('is_moderated', 0);
        $review->save();

        return response()->json(['success' => true]);
    }
}
