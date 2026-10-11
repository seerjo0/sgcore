@php
    $id = trim((string) ($props['id'] ?? ''));
    $ratios = ['16x9', '21x9', '4x3', '1x1'];
    $aspect = in_array((string) ($props['aspect'] ?? '16x9'), $ratios, true) ? (string) $props['aspect'] : '16x9';
@endphp
@if(preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1)
    <div
        id="{{ $node->id }}"
        class="pb-youtube ratio ratio-{{ $aspect }} {{ implode(' ', $node->classes) }}"
        @if($node->style !== '') style="{{ $node->style }}" @endif
    >
        <iframe
            class="pb-youtube-player"
            src="https://www.youtube-nocookie.com/embed/{{ $id }}"
            title="{{ (string) ($props['title'] ?? 'YouTube') }}"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
        ></iframe>
    </div>
@endif