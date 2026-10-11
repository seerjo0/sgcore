@php
    $variants = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
    $variant = in_array((string) ($props['variant'] ?? 'primary'), $variants, true) ? (string) $props['variant'] : 'primary';
@endphp
<span
    id="{{ $node->id }}"
    class="badge text-bg-{{ $variant }} pb-badge {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>{{ (string) ($props['content'] ?? '') }}</span>