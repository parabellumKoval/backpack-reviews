<?php

namespace Backpack\Reviews\app\Services;

use Backpack\Reviews\app\Models\Review;
use Illuminate\Database\Eloquent\Relations\Relation;

class ReviewTypeResolver
{
    /**
     * Возвращает ключ типа (product, article, …) из настройки
     * backpack.reviews.reviewable_types_list для данного review.
     */
    public static function keyFor(Review $review): ?string
    {
        $list = (array) \Settings::get('backpack.reviews.reviewable_types_list', []);

        $rawType = (string) $review->reviewable_type;
        $class   = static::normalizeMorphClass($rawType);

        foreach ($list as $key => $meta) {
            $model = $meta['model'] ?? null;
            if (!$model) {
                continue;
            }
            // Совпадение по FQCN или по наследованию (Admin\Product extends Product и т.п.)
            if ($class === $model || is_a($class, $model, true)) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * Приводит morph type к FQCN (если задан алиас через Relation::morphMap()).
     */
    public static function normalizeMorphClass(string $type): string
    {
        // Если $type — алиас из morphMap → вернём соответствующий класс
        if ($morphed = Relation::getMorphedModel($type)) {
            return $morphed;
        }

        // Если это уже FQCN или произвольная строка — вернём как есть
        return $type;
    }

    public static function publishStrategy(Review $review): string
    {
        // Пытаемся вывести ключ типа (product/article/…)
        $typeKey = ReviewTypeResolver::keyFor($review);

        // 1) Если найден тип, читаем прицельную стратегию: rw.{type}.publish_strategy
        if ($typeKey) {
            $v = \Settings::get("rw.$typeKey.publish_strategy");
            if ($v !== null && $v !== '') {
                return (string) $v; // 'no_moderation' | 'with_moderation'
            }
        }

        // 2) Фолбек — глобальная настройка
        return (string) \Settings::get('rw.default.publish_strategy', 'with_moderation');
    }

    public static function withModeration($review) {
      return ReviewTypeResolver::publishStrategy($review) === 'with_moderation';
    }
}
