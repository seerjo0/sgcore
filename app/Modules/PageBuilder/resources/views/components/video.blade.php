@php
    $sanitizer = app(\App\Modules\PageBuilder\Services\BuilderSanitizer::class);
    $src = $sanitizer->safeUrl((string) ($props['src'] ?? ''));
    $poster = $sanitizer->safeUrl((string) ($props['poster'] ?? ''));
    $ratios = ['16x9', '21x9', '4x3', '1x1'];
    $aspect = in_array((string) ($props['aspect'] ?? '16x9'), $ratios, true) ? (string) $props['aspect'] : '16x9';
@endphp
@if($src !== '')
    <div
        id="{{ $node->id }}"
        class="pb-video ratio ratio-{{ $aspect }} {{ implode(' ', $node->classes) }}"
        @if($node->style !== '') style="{{ $node->style }}" @endif
    >
        <video
            class="pb-video-player"
            src="{{ $src }}"
            @if($poster !== '') poster="{{ $poster }}" @endif
            controls
            preload="metadata"
        ></video>
    </div>
@endif