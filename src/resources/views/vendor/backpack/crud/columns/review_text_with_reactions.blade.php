{{--
Review Text with Reactions Column
Displays review text and reactions (likes/dislikes) below it
--}}

@php
    $text = data_get($entry, 'text', '');
    $maxLength = $column['text_limit'] ?? 150;
    $truncated = strlen($text) > $maxLength ? substr($text, 0, $maxLength) . '...' : $text;
    $isVideo = (bool) data_get($entry, 'is_video', false);
    $posterData = $isVideo && method_exists($entry, 'videoPosterForApi') ? $entry->videoPosterForApi() : null;
    $posterUrl = $posterData['url'] ?? null;
    $videoUrl = $isVideo ? data_get($entry, 'video_url') : null;
    
    // Reactions configuration from column settings
    $reactionsColumn = [
        'name' => 'reactions',
        'likes_key' => $column['likes_key'] ?? 'likes',
        'dislikes_key' => $column['dislikes_key'] ?? 'dislikes',
        'likes_color' => $column['likes_color'] ?? '#28a745',
        'dislikes_color' => $column['dislikes_color'] ?? '#dc3545',
        'size' => $column['reactions_size'] ?? '14px',
        'gap' => $column['gap'] ?? '8px',
        'compact' => $column['compact'] ?? true,
        'show_total' => $column['show_total'] ?? true,
        'thousand_sep' => $column['thousand_sep'] ?? ' ',
        'tooltip' => $column['tooltip'] ?? true,
    ];
@endphp

<div class="review-text-with-reactions" style="display: flex; flex-direction: column; gap: 8px;">
    {{-- Review Text or Video Preview --}}
    @if($isVideo)
        <div class="review-video-preview" style="max-height: 150px; max-width: 250px; width: 100%;">
            @if($posterUrl)
                <img src="{{ $posterUrl }}" alt="Видео постер" loading="lazy" style="width: 100%; max-width: 250px; max-height: 150px; object-fit: cover; border-radius: 4px;">
            @elseif($videoUrl)
                <iframe
                    src="{{ $videoUrl }}"
                    title="Видео-отзыв"
                    style="width: 100%; max-width: 250px; height: 150px; border: 0; border-radius: 4px;"
                    allow="autoplay; encrypted-media; picture-in-picture"
                    loading="lazy"
                ></iframe>
            @else
                <div class="text-muted small">Предпросмотр видео недоступен</div>
            @endif
        </div>
    @else
        <div class="review-text" style="color: #333; font-size: 13px; line-height: 1.4;">
            {{ $truncated }}
        </div>
    @endif
    
    {{-- Reactions (include existing blade) --}}
    <div class="review-reactions">
        @include('crud::columns.reactions', ['column' => $reactionsColumn, 'entry' => $entry])
    </div>
</div>
