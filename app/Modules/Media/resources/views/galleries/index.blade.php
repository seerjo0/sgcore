@extends('core::admin.layout')

@section('title', 'Galerias')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold">Galerias</h2>
            <p class="text-sm text-slate-500">Conjuntos de imagens para exibir nos temas.</p>
        </div>
        <a href="{{ route('admin.galerias.create') }}"
           class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
            Nova galeria
        </a>
    </div>

    @if ($galleries->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            Nenhuma galeria criada ainda.
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Título</th>
                        <th class="px-4 py-3">Imagens</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($galleries as $gallery)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $gallery->title }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $gallery->items_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.galerias.edit', $gallery) }}"
                                   class="font-medium text-primary-600 hover:text-primary-500">Editar</a>
                                <form method="POST" action="{{ route('admin.galerias.destroy', $gallery) }}"
                                      class="ml-3 inline" onsubmit="return confirm('Excluir a galeria {{ $gallery->title }}?')">
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
            {{ $galleries->links() }}
        </div>
    @endif
@endsection
