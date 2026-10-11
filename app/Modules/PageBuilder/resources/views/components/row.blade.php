<div
    id="{{ $node->id }}"
    class="pb-row row g-3 {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>{{ $children }}</div>