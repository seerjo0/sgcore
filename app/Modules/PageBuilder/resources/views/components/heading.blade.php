@php
    $level = in_array((string) ($props['level'] ?? '2'), ['1', '2', '3', '4', '5', '6'], true) ? (string) $props['level'] : '2';
    $content = app(\App\Modules\PageBuilder\Services\BuilderSanitizer::class)->sanitizeHtml((string) ($props['content'] ?? ''));
@endphp
<h{{ $level }}
    id="{{ $node->id }}"
    class="pb-heading {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>{!! $content !!}</h{{ $level }}>