{{--
Review Card Column - displays a review card for use in other CRUD lists
Shows review text, rating, author info, and link to edit
--}}

@php
    $review = $entry ?? null;
    
    if (!$review) {
        echo '<span class="text-muted">—</span>';
        return;
    }
    
    // Column options with defaults
    $textLimit = $column['text_limit'] ?? 100;
    $showRating = $column['show_rating'] ?? true;
    $showAuthor = $column['show_author'] ?? true;
    $showDate = $column['show_date'] ?? true;
    $showVideo = $column['show_video'] ?? true;
    $compact = $column['compact'] ?? false;
    
    // Get review data
    $text = $review->text ?? '';
    $rating = $review->rating ?? 0;
    $reviewType = method_exists($review, 'resolveReviewType')
        ? $review->resolveReviewType()
        : (($review->is_video ?? false) ? 'video' : 'text');
    $isVideo = $reviewType === 'video';
    $isPhoto = $reviewType === 'photo';
    $isModerated = $review->is_moderated ?? false;
    $createdAt = $review->created_at?->format('d.m.Y H:i') ?? '';
    
    // Get author info
    $authorName = null;
    if ($review->owner) {
        $authorName = $review->owner->name ?? $review->owner->email ?? null;
    }
    
    // Truncate text
    $displayText = $text;
    if (mb_strlen($text) > $textLimit) {
        $displayText = mb_substr($text, 0, $textLimit) . '...';
    }
    
    // Edit URL
    $editUrl = backpack_url('review/' . $review->id . '/edit');
    
    // Rating stars HTML
    $starsHtml = '';
    if ($showRating && $rating > 0) {
        $starsHtml = '<div class="review-card-rating" style="margin-bottom: 4px;">';
        for ($i = 1; $i <= 5; $i++) {
            $color = $i <= $rating ? '#f2c200' : '#ddd';
            $starsHtml .= '<i class="la la-star" style="color: ' . $color . '; font-size: 12px;"></i>';
        }
        $starsHtml .= '</div>';
    }
    
    // Status badge
    $statusBadge = $isModerated 
        ? '<span class="badge badge-success" style="font-size: 10px;">Опубликован</span>'
        : '<span class="badge badge-warning" style="font-size: 10px;">На модерации</span>';
@endphp

<div class="review-card" style="display: flex; gap: 10px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 6px; background: #fafafa; {{ $compact ? 'max-width: 300px;' : '' }}">
    {{-- Video/Text Icon --}}
    <div class="review-card-icon" style="flex-shrink: 0;">
        @if($isVideo && $showVideo)
            <div style="width: 40px; height: 40px; background: #e8f4fd; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                <i class="la la-video" style="font-size: 20px; color: #2196F3;"></i>
            </div>
        @elseif($isPhoto)
            <div style="width: 40px; height: 40px; background: #fff4ea; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                <i class="la la-image" style="font-size: 20px; color: #fd7e14;"></i>
            </div>
        @else
            <div style="width: 40px; height: 40px; background: #f5f5f5; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                <i class="la la-comment" style="font-size: 20px; color: #999;"></i>
            </div>
        @endif
    </div>
    
    {{-- Review Content --}}
    <div class="review-card-content" style="flex-grow: 1; min-width: 0;">
        {{-- Rating --}}
        {!! $starsHtml !!}
        
        {{-- Text --}}
        <div class="review-card-text" style="margin-bottom: 4px;">
            <a href="{{ $editUrl }}" 
               style="color: #333; text-decoration: none; font-size: 13px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"
               title="{{ $text }}">
                @if($isVideo && $showVideo)
                    <i class="la la-play-circle" style="color: #2196F3;"></i>
                @elseif($isPhoto)
                    <i class="la la-image" style="color: #fd7e14;"></i>
                @endif
                {{ $displayText ?: 'Без текста' }}
            </a>
        </div>
        
        {{-- Meta: Author, Date, Status --}}
        <div class="review-card-meta" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 11px; color: #888;">
            @if($showAuthor && $authorName)
                <span><i class="la la-user"></i> {{ $authorName }}</span>
            @endif
            
            @if($showDate && $createdAt)
                <span><i class="la la-clock"></i> {{ $createdAt }}</span>
            @endif
            
            {!! $statusBadge !!}
        </div>
    </div>
</div>
