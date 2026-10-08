@extends('core::admin.layout')

@section('title', 'Editar item do menu')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">Editar item de “{{ $menu->title }}”</h2>
        <a href="{{ route('admin.menus.edit', $menu) }}" class="text-sm text-slate-500 hover:text-slate-700">Voltar ao menu</a>
    </div>

    <div class="max-w-xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.menus.itens.update', [$menu, $item]) }}">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="label" class="mb-1 block text-sm font-medium text-slate-700">Rótulo</label>
                <input type="text" id="label" name="label" required maxlength="120"
                       value="{{ old('label', $item->label) }}"
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @error('label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="type" class="mb-1 block text-sm font-medium text-slate-700">Tipo</label>
                <select id="type" name="type" required onchange="toggleItemFields()"
                        class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <option value="page" @selected(old('type', $item->type) === 'page')>Página do site</option>
                    <option value="custom" @selected(old('type', $item->type) === 'custom')>URL personalizada</option>
                    <option value="anchor" @selected(old('type', $item->type) === 'anchor')>Âncora (#secao)</option>
                </select>
                @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div id="field_page" class="mb-4">
                <label for="page_id" class="mb-1 block text-sm font-medium text-slate-700">Página</label>
                <select id="page_id" name="page_id"
                        class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <option value="">— Selecione —</option>
                    @foreach ($pages as $page)
                        <option value="{{ $page->id }}" @selected((int) old('page_id', $item->page_id) === $page->id)>{{ $page->title }}</option>
                    @endforeach
                </select>
                @error('page_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div id="field_url" class="mb-4">
                <label for="url" class="mb-1 block text-sm font-medium text-slate-700">URL</label>
                <input type="text" id="url" name="url" maxlength="500"
                       value="{{ old('url', $item->url) }}"
                       placeholder="https://… ou /contato ou #secao"
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @error('url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mb-5">
                <label for="parent_id" class="mb-1 block text-sm font-medium text-slate-700">Item pai (opcional)</label>
                <select id="parent_id" name="parent_id"
                        class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <option value="">— Nível superior —</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}" @selected((int) old('parent_id', $item->parent_id) === $parent->id)>{{ $parent->label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">Só itens de nível superior podem ser pai (máximo 1 nível de aninhamento).</p>
                @error('parent_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                    Salvar alterações
                </button>
                <a href="{{ route('admin.menus.edit', $menu) }}" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
        function toggleItemFields() {
            var type = document.getElementById('type').value;
            document.getElementById('field_page').classList.toggle('hidden', type !== 'page');
            document.getElementById('field_url').classList.toggle('hidden', type === 'page');
        }

        toggleItemFields();
    </script>
@endsection
