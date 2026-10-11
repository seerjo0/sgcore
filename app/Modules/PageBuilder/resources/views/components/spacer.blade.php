@php
    $height = $node->style !== '' ? $node->style : 'height: '.e($props['height'] ?? '3rem').';';
@endphp
<div class="pb-spacer" style="{{ $height }}"></div>