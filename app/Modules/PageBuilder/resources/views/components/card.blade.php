<div
    id="{{ $node->id }}"
    class="pb-card card {{ implode(' ', $node->classes) }}"
    @if($node->style !== '') style="{{ $node->style }}" @endif
>
    <div class="card-body">{{ $children }}</div>
</div>