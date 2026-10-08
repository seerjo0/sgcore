@extends('core::admin.layout')

@section('title', 'Editar mídia')

@section('content')
    <h2 class="mb-4 text-xl font-semibold">Editar {{ $medium->filename }}</h2>

    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <img src="{{ $medium->url() }}" alt="{{ $medium->alt ?? $medium->filename }}"
                 class="max-h-96 w-full rounded object-contain bg-slate-50">
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('admin.midia.update', $medium) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="alt" class="mb-1 block text-sm font-medium text-slate-700">Texto alternativo (alt)</label>
                    <input type="text" id="alt" name="alt" maxlength="255"
                           value="{{ old('alt', $medium->alt) }}"
                           class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                           placeholder="Descreva a imagem para buscadores e leitores de tela">
                    @error('alt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mb-5">
                    <label for="caption" class="mb-1 block text-sm font-medium text-slate-700">Legenda</label>
                    <textarea id="caption" name="caption" rows="3" maxlength="1000"
                              class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('caption', $medium->caption) }}</textarea>
                    @error('caption') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                        Salvar alterações
                    </button>
                    <a href="{{ route('admin.midia.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Voltar</a>
                </div>
            </form>

            <div class="mt-6 border-t border-slate-100 pt-4 text-sm text-slate-500">
                <p><span class="font-medium text-slate-700">Arquivo:</span> {{ $medium->filename }}</p>
                <p><span class="font-medium text-slate-700">Tipo:</span> {{ $medium->mime_type }}</p>
                <p><span class="font-medium text-slate-700">Dimensões:</span> {{ $medium->width }}×{{ $medium->height }} px</p>
                <p><span class="font-medium text-slate-700">Tamanho:</span> {{ number_format($medium->size / 1024, 0, ',', '.') }} KB</p>
                <p><span class="font-medium text-slate-700">Enviada em:</span> {{ $medium->created_at?->format('d/m/Y H:i') }}</p>
            </div>
        </div>
    </div>
@endsection
