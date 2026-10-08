@extends('core::admin.layout')

@section('title', 'Novo menu')

@section('content')
    <h2 class="mb-4 text-xl font-semibold">Novo menu</h2>

    <div class="max-w-xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @if (empty($locations))
            <p class="text-sm text-slate-500">
                Todos os locais de menu já estão em uso. Edite um menu existente ou exclua-o antes de criar outro.
            </p>
            <a href="{{ route('admin.menus.index') }}" class="mt-3 inline-block text-sm text-primary-600 hover:text-primary-500">Voltar</a>
        @else
            <form method="POST" action="{{ route('admin.menus.store') }}">
                @csrf

                <div class="mb-4">
                    <label for="title" class="mb-1 block text-sm font-medium text-slate-700">Título</label>
                    <input type="text" id="title" name="title" required maxlength="120"
                           value="{{ old('title') }}"
                           class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                           placeholder="Ex.: Principal">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mb-5">
                    <label for="location" class="mb-1 block text-sm font-medium text-slate-700">Local</label>
                    <select id="location" name="location" required
                            class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        @foreach ($locations as $value => $label)
                            <option value="{{ $value }}" @selected(old('location') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Cada local comporta apenas um menu.</p>
                    @error('location') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                        Criar menu
                    </button>
                    <a href="{{ route('admin.menus.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
                </div>
            </form>
        @endif
    </div>
@endsection
