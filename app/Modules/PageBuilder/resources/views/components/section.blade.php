<section
    id="{{ $node->id }}"
    class="pb-section {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>{{ $children }}</section>