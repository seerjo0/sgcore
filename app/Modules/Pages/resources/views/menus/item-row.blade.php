@php
    $childMap = $childMap ?? collect();
    $kids = $childMap[$item->id] ?? collect();
    $badge = match ($item->type) {
        'page' => 'Página: /'.($item->page?->slug ?? '—'),
        'anchor' => 'Âncora: '.($item->url ?? ''),
        default => 'URL: '.($item->url ?? ''),
    };
@endphp

<div>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-2 shadow-sm"
         style="margin-left: {{ $depth * 24 }}px">
        <div class="min-w-0">
            <span class="text-sm font-medium">{{ $item->label }}</span>
            <span class="ml-2 text-xs text-slate-400">{{ $badge }}</span>
        </div>

        <div class="flex items-center gap-3 text-sm">
            <div class="flex gap-1">
                <form method="POST" action="{{ route('admin.menus.itens.mover', [$menu, $item]) }}">
                    @csrf
                    <input type="hidden" name="direcao" value="up">
                    <button type="submit" title="Mover para cima"
                            class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">↑</button>
                </form>
                <form method="POST" action="{{ route('admin.menus.itens.mover', [$menu, $item]) }}">
                    @csrf
                    <input type="hidden" name="direcao" value="down">
                    <button type="submit" title="Mover para baixo"
                            class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">↓</button>
                </form>
            </div>

            <a href="{{ route('admin.menus.itens.edit', [$menu, $item]) }}"
               class="font-medium text-primary-600 hover:text-primary-500">Editar</a>

            <form method="POST" action="{{ route('admin.menus.itens.destroy', [$menu, $item]) }}"
                  onsubmit="return confirm('Remover o item {{ $item->label }} e seus subitens?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="font-medium text-red-600 hover:text-red-500">Excluir</button>
            </form>
        </div>
    </div>

    @foreach ($kids as $kid)
        @include('pages::menus.item-row', ['item' => $kid, 'childMap' => $childMap, 'depth' => $depth + 1])
    @endforeach
</div>
