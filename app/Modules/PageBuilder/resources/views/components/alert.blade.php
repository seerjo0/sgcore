@php
    $variants = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
    $variant = in_array((string) ($props['variant'] ?? 'primary'), $variants, true) ? (string) $props['variant'] : 'primary';
    $content = app(\App\Modules\PageBuilder\Services\BuilderSanitizer::class)->sanitizeHtml((string) ($props['content'] ?? ''));
@endphp
<div
    id="{{ $node->id }}"
    class="alert alert-{{ $variant }} pb-alert {{ implode(' ', $node->classes) }}"
    role="alert"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>{!! $content !!}</div>