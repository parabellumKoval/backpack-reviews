{{--
Review Text with Reactions Column
Displays review text and reactions (likes/dislikes) below it
--}}

@php
    $text = data_get($entry, 'text', '');
    $maxLength = $column['text_limit'] ?? 150;
    $truncated = strlen($text) > $maxLength ? substr($text, 0, $maxLength) . '...' : $text;
    
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
    {{-- Review Text --}}
    <div class="review-text" style="color: #333; font-size: 13px; line-height: 1.4;">
        {{ $truncated }}
    </div>
    
    {{-- Reactions (include existing blade) --}}
    <div class="review-reactions">
        @include('crud::columns.reactions', ['column' => $reactionsColumn, 'entry' => $entry])
    </div>
</div>
