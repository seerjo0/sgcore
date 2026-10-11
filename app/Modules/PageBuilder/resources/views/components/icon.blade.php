@php
    $icons = config('pagebuilder.icons', []);
    $name = (string) ($props['icon'] ?? 'star');
    $path = is_array($icons) ? ($icons[$name] ?? null) : null;
    $sizes = ['sm' => '1rem', 'md' => '1.75rem', 'lg' => '3rem'];
    $size = array_key_exists((string) ($props['size'] ?? 'md'), $sizes) ? (string) $props['size'] : 'md';
    $dimension = 'height:'.$sizes[$size].';width:'.$sizes[$size];
    $style = $node->style !== '' ? $dimension.';'.$node->style : $dimension;
    $variants = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark', 'light'];
    $variant = in_array((string) ($props['variant'] ?? 'primary'), $variants, true) ? (string) $props['variant'] : 'primary';
@endphp
@if($path !== null)
    <span
        id="{{ $node->id }}"
        class="pb-icon d-inline-flex pb-text-{{ $variant }} {{ implode(' ', $node->classes) }}"
        style="{{ $style }}"
    ><svg viewBox="0 0 24 24" width="100%" height="100%" fill="currentColor" aria-hidden="true" focusable="false"><path d="{{ $path }}"/></svg></span>
@endif