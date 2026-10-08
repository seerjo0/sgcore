@extends('core::admin.layout')

@section('title', 'Mídia')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Biblioteca de mídia</h2>
            <p class="text-sm text-slate-500">Imagens enviadas para o site.</p>
        </div>
        <form method="GET" action="{{ route('admin.midia.index') }}" class="flex gap-2">
            <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por nome, alt ou legenda…"
                   class="w-64 rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
            <button type="submit" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Buscar</button>
        </form>
    </div>

    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="font-semibold">Enviar imagens</h3>
        <p class="mb-3 text-sm text-slate-500">
            JPG, PNG, WebP ou GIF · até {{ (int) floor(config('cms.media.max_upload_bytes') / 1048576) }} MB por arquivo ·
            imagens com mais de {{ config('cms.media.max_dimension') }}px são redimensionadas automaticamente.
        </p>
        <form method="POST" action="{{ route('admin.midia.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
            @csrf
            <input type="file" name="files[]" multiple required
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   class="text-sm file:mr-3 file:rounded file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500">
            <button type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                Enviar
            </button>
        </form>
        @error('files')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('files.*')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @if ($media->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            Nenhuma imagem encontrada.
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($media as $item)
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <a href="{{ $item->url() }}" target="_blank" rel="noopener">
                        <img src="{{ $item->url() }}" alt="{{ $item->alt ?? $item->filename }}" loading="lazy"
                             class="h-36 w-full object-cover">
                    </a>
                    <div class="p-3">
                        <p class="truncate text-sm font-medium" title="{{ $item->filename }}">{{ $item->filename }}</p>
                        <p class="text-xs text-slate-400">
                            {{ $item->width }}×{{ $item->height }} · {{ number_format($item->size / 1024, 0, ',', '.') }} KB
                        </p>
                        <div class="mt-2 flex items-center justify-between text-sm">
                            <a href="{{ route('admin.midia.edit', $item) }}" class="font-medium text-primary-600 hover:text-primary-500">Editar</a>
                            <form method="POST" action="{{ route('admin.midia.destroy', $item) }}"
                                  onsubmit="return confirm('Excluir a mídia {{ $item->filename }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-red-600 hover:text-red-500">Excluir</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $media->links() }}
        </div>
    @endif
@endsection
