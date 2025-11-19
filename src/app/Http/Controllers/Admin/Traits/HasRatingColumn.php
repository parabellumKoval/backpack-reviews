<?php

namespace Backpack\Reviews\app\Http\Controllers\Admin\Traits;

trait HasRatingColumn
{
    protected function addRatingColumn(array $overrides = []): void
    {
        if (!property_exists($this, 'crud') || ! $this->crud) {
            return;
        }

        $reviewModelClass = $overrides['review_model'] ?? config('backpack.reviews.review_model', \Backpack\Reviews\app\Models\Review::class);
        $reviewableTypeOption = $overrides['reviewable_type'] ?? null;

        $defaults = [
            'name' => 'rating',
            'label' => trans('reviews::column.rating'),
            'type' => 'reviews_rating_summary',
            'max' => 5,
            'rating_attribute' => 'rating',
            'ratings_count_attribute' => 'reviews_with_rating_count',
            'reviews_count_attribute' => 'reviews_count',
            'priority' => 3,
            'review_model' => $reviewModelClass,
        ];

        $columnDefinition = array_merge($defaults, $overrides);

        if (!array_key_exists('orderable', $columnDefinition)) {
            $columnDefinition['orderable'] = true;
        }

        if (!array_key_exists('orderLogic', $overrides)) {
            $columnDefinition['orderLogic'] = function ($query, $column, $columnDirection) use ($reviewModelClass, $reviewableTypeOption) {
                if (!class_exists($reviewModelClass)) {
                    return $query;
                }

                $model = $query->getModel();
                if (!$model || !method_exists($model, 'getTable')) {
                    return $query;
                }

                $table = $model->getTable();
                $keyName = $model->getKeyName();
                $alias = 'reviews_avg_' . uniqid();

                $reviewQuery = $reviewModelClass::query()
                    ->selectRaw('reviewable_id, AVG(rating) as avg_rating')
                    ->where('is_moderated', true);

                $resolvedType = $reviewableTypeOption;
                if (is_callable($reviewableTypeOption)) {
                    $resolvedType = call_user_func($reviewableTypeOption, $model, $column);
                } elseif (!$resolvedType && method_exists($model, 'getMorphClass')) {
                    $resolvedType = $model->getMorphClass();
                }

                if ($resolvedType) {
                    $reviewQuery->where('reviewable_type', $resolvedType);
                }

                $reviewQuery->groupBy('reviewable_id');

                $query->leftJoinSub($reviewQuery, $alias, "{$alias}.reviewable_id", '=', "{$table}.{$keyName}");

                return $query->orderByRaw("COALESCE({$alias}.avg_rating, 0) {$columnDirection}");
            };
        }

        $this->crud->addColumn($columnDefinition);
    }
}
