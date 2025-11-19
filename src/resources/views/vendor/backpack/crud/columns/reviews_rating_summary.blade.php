{{-- Rating summary column --}}
@php
    $max = (int) ($column['max'] ?? 5);
    $ratingAttribute = $column['rating_attribute'] ?? $column['name'] ?? 'rating';
    $ratingsCountAttr = $column['ratings_count_attribute'] ?? 'reviews_with_rating_count';
    $reviewsCountAttr = $column['reviews_count_attribute'] ?? 'reviews_count';
    $reviewableTypeOption = $column['reviewable_type'] ?? null;
    $reviewModelClass = $column['review_model'] ?? config('backpack.reviews.review_model', \Backpack\Reviews\app\Models\Review::class);

    $rating = null;
    $ratingsCount = 0;
    $reviewsCount = 0;

    $shouldQuery = $reviewableTypeOption !== null && $reviewableTypeOption !== '' && class_exists($reviewModelClass);

    if ($shouldQuery && $entry) {
        $resolvedType = is_callable($reviewableTypeOption)
            ? call_user_func($reviewableTypeOption, $entry, $column)
            : $reviewableTypeOption;

        if ($resolvedType) {
            $stats = $reviewModelClass::query()
                ->where('reviewable_type', $resolvedType)
                ->where('reviewable_id', $entry->getKey())
                ->where('is_moderated', true)
                ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as reviews_count, COUNT(rating) as ratings_count')
                ->first();

            if ($stats) {
                $rating = $stats->avg_rating !== null ? round((float) $stats->avg_rating, 1) : null;
                $reviewsCount = (int) $stats->reviews_count;
                $ratingsCount = (int) $stats->ratings_count;
            }
        }
    } else {
        $ratingValue = data_get($entry, $ratingAttribute);
        $rating = is_numeric($ratingValue) ? round((float) $ratingValue, 1) : null;
        $ratingsCount = (int) data_get($entry, $ratingsCountAttr, 0);
        $reviewsCount = (int) data_get($entry, $reviewsCountAttr, 0);
    }

    $rounded = $rating !== null ? round($rating * 2) / 2 : 0;
    $full = (int) floor($rounded);
    $half = $rating !== null && ($rounded - $full) >= 0.5 ? 1 : 0;
    $empty = max(0, $max - $full - $half);
    $title = $rating !== null ? ($rating . ' / ' . $max) : trans('reviews::column.no_rating');
@endphp

<div class="reviews-rating-summary-column">
    <div class="reviews-rating-stars mb-1" title="{{ $title }}" style="color:#f2c200; display:inline-flex; gap:2px;">
        @for ($i = 0; $i < $full; $i++)
            <i class="la la-star" aria-hidden="true"></i>
        @endfor
        @if ($half)
            <i class="la la-star-half-o" aria-hidden="true"></i>
        @endif
        @for ($i = 0; $i < $empty; $i++)
            <i class="la la-star-o" aria-hidden="true"></i>
        @endfor
    </div>
    <div class="text-muted small d-flex flex-column gap-1">
        <span>{{ trans('reviews::column.ratings_label', ['count' => $ratingsCount]) }}</span>
        <span>{{ trans('reviews::column.reviews_label', ['count' => $reviewsCount]) }}</span>
    </div>
</div>
