@extends('core::admin.layout')

@section('title', 'Páginas')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Páginas</h2>
            <p class="text-sm text-slate-500">Páginas do site com SEO e conteúdo.</p>
        </div>
        <a href="{{ route('admin.paginas.create') }}"
           class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
            Nova página
        </a>
    </div>

    <form method="GET" action="{{ route('admin.paginas.index') }}" class="mb-4 flex gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por título ou slug…"
               class="w-72 rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
        <button type="submit" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Buscar</button>
    </form>

    @if ($pages->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            Nenhuma página criada ainda.
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Título</th>
                        <th class="px-4 py-3">Slug</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($pages as $page)
                        <tr>
                            <td class="px-4 py-3 font-medium">
                                {{ $page->title }}
                                @if ($page->is_home)
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-700">Início</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">/{{ $page->slug }}</td>
                            <td class="px-4 py-3">
                                @if ($page->isPublished())
                                    <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-semibold text-emerald-700">Publicado</span>
                                @else
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-500">Rascunho</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.paginas.edit', $page) }}"
                                   class="font-medium text-primary-600 hover:text-primary-500">Editar</a>
                                <form method="POST" action="{{ route('admin.paginas.destroy', $page) }}"
                                      class="ml-3 inline" onsubmit="return confirm('Excluir a página {{ $page->title }}?')">
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
            {{ $pages->links() }}
        </div>
    @endif
@endsection
