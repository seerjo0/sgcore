@extends('core::admin.layout')

@section('title', 'Nova galeria')

@section('content')
    <h2 class="mb-4 text-xl font-semibold">Nova galeria</h2>

    <div class="max-w-xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.galerias.store') }}">
            @csrf

            <div class="mb-4">
                <label for="title" class="mb-1 block text-sm font-medium text-slate-700">Título</label>
                <input type="text" id="title" name="title" required maxlength="255"
                       value="{{ old('title') }}"
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                       placeholder="Ex.: Fotos do evento">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                    Criar galeria
                </button>
                <a href="{{ route('admin.galerias.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
