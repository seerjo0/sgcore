@php
    $html = app(\App\Modules\PageBuilder\Services\BuilderSanitizer::class)->sanitizeHtml((string) ($props['code'] ?? ''));
@endphp
@if($html !== '')
    <div
        id="{{ $node->id }}"
        class="pb-html {{ implode(' ', $node->classes) }}"
        @if($node->style !== '') style="{{ $node->style }}" @endif
    >{!! $html !!}</div>
@endif
