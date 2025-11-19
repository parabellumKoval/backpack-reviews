{{-- 
Custom column for Review Type with icons
Shows text or video icon based on is_video field
--}}

@php
    $value = data_get($entry, $column['name']);
    $isVideo = data_get($entry, 'is_video', false);
    
    // Icon configuration
    $icon = $isVideo ? 'la-video' : 'la-comment-dots';
    $text = $isVideo ? 'Видео' : 'Текст';
    $color = $isVideo ? '#dc3545' : '#17a2b8'; // red for video, blue for text
    
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
