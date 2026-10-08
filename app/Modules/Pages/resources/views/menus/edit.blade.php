@extends('core::admin.layout')

@section('title', 'Editar menu')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">Editar “{{ $menu->title }}”</h2>
        <a href="{{ route('admin.menus.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Voltar para a lista</a>
    </div>

    <div class="mb-6 max-w-xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <form method="POST" action="{{ route('admin.menus.update', $menu) }}">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="title" class="mb-1 block text-sm font-medium text-slate-700">Título</label>
                <input type="text" id="title" name="title" required maxlength="120"
                       value="{{ old('title', $menu->title) }}"
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label for="location" class="mb-1 block text-sm font-medium text-slate-700">Local</label>
                <select id="location" name="location" required
                        class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @foreach ($locations as $value => $label)
                        <option value="{{ $value }}" @selected(old('location', $menu->location) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('location') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                Salvar menu
            </button>
        </form>
    </div>

    <div class="mb-4">
        <h3 class="font-semibold">Adicionar item</h3>
        <p class="text-sm text-slate-500">Itens podem ser aninados sob outro item de nível superior (1 nível).</p>
    </div>

    <div class="mb-8 max-w-3xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <form method="POST" action="{{ route('admin.menus.itens.store', $menu) }}">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="label" class="mb-1 block text-sm font-medium text-slate-700">Rótulo</label>
                    <input type="text" id="label" name="label" required maxlength="120"
                           value="{{ old('label') }}"
                           class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                           placeholder="Ex.: Sobre nós">
                    @error('label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="type" class="mb-1 block text-sm font-medium text-slate-700">Tipo</label>
                    <select id="type" name="type" required onchange="toggleItemFields()"
                            class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="page" @selected(old('type') === 'page')>Página do site</option>
                        <option value="custom" @selected(old('type') === 'custom')>URL personalizada</option>
                        <option value="anchor" @selected(old('type') === 'anchor')>Âncora (#secao)</option>
                    </select>
                    @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div id="field_page">
                    <label for="page_id" class="mb-1 block text-sm font-medium text-slate-700">Página</label>
                    <select id="page_id" name="page_id"
                            class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">— Selecione —</option>
                        @foreach ($pages as $page)
                            <option value="{{ $page->id }}" @selected((int) old('page_id') === $page->id)>{{ $page->title }}</option>
                        @endforeach
                    </select>
                    @error('page_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div id="field_url">
                    <label for="url" class="mb-1 block text-sm font-medium text-slate-700">URL</label>
                    <input type="text" id="url" name="url" maxlength="500"
                           value="{{ old('url') }}"
                           placeholder="https://… ou /contato ou #secao"
                           class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @error('url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="parent_id" class="mb-1 block text-sm font-medium text-slate-700">Item pai (opcional)</label>
                    <select id="parent_id" name="parent_id"
                            class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">— Nível superior —</option>
                        @foreach ($roots as $root)
                            <option value="{{ $root->id }}" @selected((int) old('parent_id') === $root->id)>{{ $root->label }}</option>
                        @endforeach
                    </select>
                    @error('parent_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit"
                    class="mt-4 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                Adicionar item
            </button>
        </form>
    </div>

    <h3 class="mb-3 font-semibold">Itens do menu ({{ $menu->items()->count() }})</h3>

    @if ($roots->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
            Este menu ainda não tem itens.
        </div>
    @else
        <div class="space-y-2">
            @foreach ($roots as $root)
                @include('pages::menus.item-row', ['item' => $root, 'children' => $children[$root->id] ?? collect(), 'depth' => 0])
            @endforeach
        </div>
    @endif

    <script>
        function toggleItemFields() {
            var type = document.getElementById('type').value;
            document.getElementById('field_page').classList.toggle('hidden', type !== 'page');
            document.getElementById('field_url').classList.toggle('hidden', type === 'page');
            document.getElementById('url').placeholder =
                type === 'anchor' ? '#secao' : 'https://… ou /contato';
        }

        toggleItemFields();
    </script>
@endsection
