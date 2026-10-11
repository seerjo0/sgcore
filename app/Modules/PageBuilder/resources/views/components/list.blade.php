@php
    $sanitizer = app(\App\Modules\PageBuilder\Services\BuilderSanitizer::class);
    $variant = ($props['variant'] ?? 'list-group') === 'unstyled' ? 'unstyled' : 'list-group';
    $rawItems = (string) (($props['items'] ?? '') !== '' ? $props['items'] : ($props['content'] ?? ''));
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawItems)), fn (string $line): bool => $line !== ''));
@endphp
<ul
    id="{{ $node->id }}"
    class="{{ $variant === 'unstyled' ? 'list-unstyled pb-list' : 'list-group pb-list list-group-flush' }} {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>
    @foreach($lines as $line)
        <li class="{{ $variant === 'list-group' ? 'list-group-item' : '' }}">{!! $sanitizer->sanitizeHtml($line) !!}</li>
    @endforeach
</ul>