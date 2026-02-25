{{-- 
Custom column for Review Type with icons
Shows text or video icon based on is_video field
--}}

@php
    $type = data_get($entry, 'review_type');
    $isVideo = (bool) data_get($entry, 'is_video', false);

    if (!$type) {
        $type = $isVideo ? 'video' : 'text';
    }

    $typeMap = [
        'text' => [
            'icon' => 'la-comment-dots',
            'text' => 'Текст',
            'color' => '#17a2b8',
        ],
        'video' => [
            'icon' => 'la-video',
            'text' => 'Видео',
            'color' => '#dc3545',
        ],
        'photo' => [
            'icon' => 'la-image',
            'text' => 'Фото',
            'color' => '#fd7e14',
        ],
    ];

    $meta = $typeMap[$type] ?? $typeMap['text'];
    $icon = $meta['icon'];
    $text = $meta['text'];
    $color = $meta['color'];
    
    $iconSize = $column['icon_size'] ?? '20px';
    $showText = $column['show_text'] ?? true;
@endphp

<span style="display: inline-flex; align-items: center; gap: 6px;">
    <i class="la {{ $icon }}" 
       style="font-size: {{ $iconSize }}; color: {{ $color }};" 
       title="{{ $text }}"></i>
    
    @if($showText)
        <span style="color: {{ $color }}; font-weight: 500;">{{ $text }}</span>
    @endif
</span>
