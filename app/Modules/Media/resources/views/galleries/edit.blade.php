@extends('core::admin.layout')

@section('title', 'Editar galeria')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">Editar “{{ $gallery->title }}”</h2>
        <a href="{{ route('admin.galerias.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Voltar para a lista</a>
    </div>

    <div class="mb-6 max-w-xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <form method="POST" action="{{ route('admin.galerias.update', $gallery) }}">
            @csrf
            @method('PUT')
            <label for="title" class="mb-1 block text-sm font-medium text-slate-700">Título</label>
            <div class="flex gap-2">
                <input type="text" id="title" name="title" required maxlength="255"
                       value="{{ old('title', $gallery->title) }}"
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <button type="submit"
                        class="shrink-0 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                    Salvar
                </button>
            </div>
            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </form>
    </div>

    <div class="mb-4 flex items-center justify-between">
        <h3 class="font-semibold">Imagens da galeria ({{ $gallery->items->count() }})</h3>
        <button type="button" onclick="openMediaPicker()"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
            Adicionar imagens
        </button>
    </div>

    @if ($gallery->items->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            Esta galeria ainda não tem imagens. Clique em “Adicionar imagens”.
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($gallery->items as $item)
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <img src="{{ $item->media?->url() }}" alt="{{ $item->media?->alt ?? '' }}" loading="lazy"
                         class="h-36 w-full object-cover bg-slate-50">

                    <div class="p-3">
                        <form method="POST" action="{{ route('admin.galerias.itens.update', [$gallery, $item]) }}">
                            @csrf
                            @method('PUT')
                            <div class="flex gap-1">
                                <input type="text" name="caption" maxlength="255"
                                       value="{{ old('captions.'.$item->id, $item->caption) }}"
                                       placeholder="Legenda…"
                                       class="w-full rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <button type="submit" title="Salvar legenda"
                                        class="shrink-0 rounded bg-primary-600 px-2 py-1 text-xs font-semibold text-white hover:bg-primary-500">
                                    OK
                                </button>
                            </div>
                            @error('caption') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </form>

                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex gap-1">
                                <form method="POST" action="{{ route('admin.galerias.itens.mover', [$gallery, $item]) }}">
                                    @csrf
                                    <input type="hidden" name="direcao" value="up">
                                    <button type="submit" title="Mover para cima"
                                            class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">↑</button>
                                </form>
                                <form method="POST" action="{{ route('admin.galerias.itens.mover', [$gallery, $item]) }}">
                                    @csrf
                                    <input type="hidden" name="direcao" value="down">
                                    <button type="submit" title="Mover para baixo"
                                            class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">↓</button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('admin.galerias.itens.destroy', [$gallery, $item]) }}"
                                  onsubmit="return confirm('Remover esta imagem da galeria?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-500">Remover</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @include('media::picker', [
        'addAction' => route('admin.galerias.itens.store', $gallery),
        'title' => 'Adicionar imagens da biblioteca',
    ])
@endsection
