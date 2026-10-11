<div
    id="{{ $node->id }}"
    class="pb-container container {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>{{ $children }}</div>