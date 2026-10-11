@php
    $sanitizer = app(\App\Modules\PageBuilder\Services\BuilderSanitizer::class);
    $href = $sanitizer->safeUrl((string) ($props['href'] ?? '')) ?: '#';
    $variant = in_array((string) ($props['variant'] ?? 'primary'), ['primary', 'secondary', 'light', 'dark', 'outline-primary', 'outline-secondary'], true) ? (string) $props['variant'] : 'primary';
    $size = in_array((string) ($props['size'] ?? ''), ['', 'sm', 'lg'], true) ? (string) $props['size'] : '';
$label = trim((string) ($props['text'] ?? '')) !== '' ? (string) $props['text'] : strip_tags((string) ($props['content'] ?? ''));
@endphp
<a
    id="{{ $node->id }}"
    class="btn btn-{{ $variant }}{{ $size !== '' ? ' btn-'.$size : '' }} {{ implode(' ', $node->classes) }}"
    href="{{ $href }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>{{ $label }}</a>