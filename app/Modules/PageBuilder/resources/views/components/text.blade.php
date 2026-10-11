@php
    $content = app(\App\Modules\PageBuilder\Services\BuilderSanitizer::class)->sanitizeHtml((string) ($props['content'] ?? ''));
@endphp
<div
    id="{{ $node->id }}"
    class="pb-text {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>{!! $content !!}</div>