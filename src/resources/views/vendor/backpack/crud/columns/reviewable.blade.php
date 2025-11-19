{{--
Reviewable Column - displays related entity card (Product, Article, etc.)
Uses polymorphic relationship to show appropriate card based on reviewable_type
--}}

@php
    // Get the reviewable entity
    $reviewable = $entry->reviewable ?? null;
    
    if (!$reviewable) {
        echo '<span class="text-muted">—</span>';
        return;
    }
    
    // Get reviewable type
    $reviewableType = get_class($reviewable);
    
    // Get configuration for this type
    $config = config('backpack.reviews.reviewable_cards_config.' . $reviewableType, null);
    
    if (!$config || !isset($config['view'])) {
        // Fallback: display type and ID
        $typeName = config('backpack.reviews.reviewable_types_list.' . $reviewableType, 'Запись');
        echo '<span class="text-muted">' . $typeName . ' #' . $reviewable->id . '</span>';
        return;
    }
    
    // Get edit route
    $editRoute = null;
    if (isset($config['edit_route'])) {
        $editRoute = backpack_url($config['edit_route'] . '/' . $reviewable->id);
    }
@endphp

{{-- Render the appropriate card view --}}
@include($config['view'], [
    'reviewable' => $reviewable,
    'editRoute' => $editRoute
])
