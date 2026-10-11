@php
    $content = app(\App\Modules\PageBuilder\Services\BuilderSanitizer::class)->sanitizeHtml((string) ($props['content'] ?? ''));
    $cite = trim((string) ($props['cite'] ?? ''));
@endphp
<figure
    id="{{ $node->id }}"
    class="pb-blockquote {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>
    <blockquote class="blockquote mb-0">{!! $content !!}</blockquote>
    @if($cite !== '')
        <figcaption class="blockquote-footer mb-0 mt-2">{{ $cite }}</figcaption>
    @endif
</figure>