<div
    id="{{ $node->id }}"
    class="pb-col col {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>{{ $children }}</div>