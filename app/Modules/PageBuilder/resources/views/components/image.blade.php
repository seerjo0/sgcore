@php
    $sanitizer = app(\App\Modules\PageBuilder\Services\BuilderSanitizer::class);
    $src = $sanitizer->safeUrl((string) ($props['src'] ?? ''));
@endphp
@if($src !== '')
    <img
        id="{{ $node->id }}"
        class="pb-media-image img-fluid {{ implode(' ', $node->classes) }}"
        src="{{ $src }}"
        alt="{{ (string) ($props['alt'] ?? '') }}"
        @if(! empty($props['width'])) width="{{ (int) $props['width'] }}" @endif
        @if($node->style !== '') style="{{ $node->style }}" @endif
    >
@endif