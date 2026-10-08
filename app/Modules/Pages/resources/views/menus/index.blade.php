@extends('core::admin.layout')

@section('title', 'Menus')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold">Menus</h2>
            <p class="text-sm text-slate-500">Navegação do site (topo e rodapé).</p>
        </div>
        <a href="{{ route('admin.menus.create') }}"
           class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
            Novo menu
        </a>
    </div>

    @if ($menus->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            Nenhum menu criado ainda.
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Título</th>
                        <th class="px-4 py-3">Local</th>
                        <th class="px-4 py-3">Itens</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($menus as $menu)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $menu->title }}</td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ \App\Modules\Pages\Models\Menu::LOCATIONS[$menu->location] ?? $menu->location }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $menu->items_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.menus.edit', $menu) }}"
                                   class="font-medium text-primary-600 hover:text-primary-500">Editar</a>
                                <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}"
                                      class="ml-3 inline" onsubmit="return confirm('Excluir o menu {{ $menu->title }}? Todos os itens serão removidos.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-500">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $menus->links() }}
        </div>
    @endif
@endsection
