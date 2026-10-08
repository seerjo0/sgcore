@php
    $mode = $mode ?? 'multi';
@endphp

<div id="media-picker" data-mode="{{ $mode }}" data-target="{{ $targetField ?? '' }}" data-preview="{{ $targetPreview ?? '' }}"
     class="fixed inset-0 z-50 hidden bg-black/60 p-4" onclick="if (event.target === this) closeMediaPicker()">
    <div class="mx-auto flex max-h-[85vh] w-full max-w-3xl flex-col rounded-xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h3 class="font-semibold">{{ $title ?? 'Escolher mídia' }}</h3>
            <button type="button" onclick="closeMediaPicker()"
                    class="rounded px-2 py-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Fechar">✕</button>
        </div>

        <div class="border-b border-slate-100 px-5 py-3">
            <input type="search" id="media-picker-search" placeholder="Buscar na biblioteca…"
                   oninput="debouncedMediaPickerSearch()"
                   class="w-full rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
        </div>

        @if ($mode === 'multi')
            <form method="POST" action="{{ $addAction }}">
                @csrf

                <div id="media-picker-results" class="grid grid-cols-3 gap-3 overflow-y-auto p-5 md:grid-cols-4">
                    <p class="col-span-full text-sm text-slate-400">Carregando…</p>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3">
                    <span id="media-picker-count" class="text-sm text-slate-500">Nenhuma selecionada</span>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeMediaPicker()"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                            Adicionar selecionadas
                        </button>
                    </div>
                </div>
            </form>
        @else
            <div id="media-picker-results" class="grid grid-cols-3 gap-3 overflow-y-auto p-5 md:grid-cols-4">
                <p class="col-span-full text-sm text-slate-400">Carregando…</p>
            </div>

            <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3">
                <span id="media-picker-count" class="text-sm text-slate-500">Nenhuma selecionada</span>
                <div class="flex gap-2">
                    <button type="button" onclick="closeMediaPicker()"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button type="button" onclick="usePickerSelection()"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        Usar selecionada
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
    let mediaPickerTimer = null;

    function mediaPickerMode() {
        return document.getElementById('media-picker').dataset.mode;
    }

    function openMediaPicker() {
        const modal = document.getElementById('media-picker');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        loadMediaPickerResults(document.getElementById('media-picker-search').value);
    }

    function closeMediaPicker() {
        const modal = document.getElementById('media-picker');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function debouncedMediaPickerSearch() {
        clearTimeout(mediaPickerTimer);
        mediaPickerTimer = setTimeout(function () {
            loadMediaPickerResults(document.getElementById('media-picker-search').value);
        }, 300);
    }

    async function loadMediaPickerResults(query) {
        const container = document.getElementById('media-picker-results');
        container.innerHTML = '<p class="col-span-full text-sm text-slate-400">Carregando…</p>';

        try {
            const url = '{{ route('admin.midia.buscar') }}?per_page=12&q=' + encodeURIComponent(query);
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const payload = await response.json();
            const mode = mediaPickerMode();
            const target = document.getElementById('media-picker').dataset.target;
            const current = mode === 'single' ? (document.querySelector(target)?.value ?? '') : '';

            if (!payload.data.length) {
                container.innerHTML = '<p class="col-span-full text-sm text-slate-400">Nenhuma imagem encontrada.</p>';
                return;
            }

            container.innerHTML = payload.data.map(function (item) {
                const checked = mode === 'single' && String(item.id) === String(current) ? ' checked' : '';

                return '' +
                    '<label class="cursor-pointer overflow-hidden rounded-lg border-2 border-transparent hover:border-primary-500">' +
                        '<input type="checkbox"' +
                            (mode === 'multi' ? ' name="media_ids[]"' : ' name="media_single"') +
                            ' value="' + item.id + '" data-url="' + item.url + '"' + checked +
                            ' class="hidden" onchange="onMediaPick(this)">' +
                        '<img src="' + item.url + '" alt="' + escapeHtml(item.alt || item.filename) + '" loading="lazy" class="h-28 w-full object-cover">' +
                        '<span class="block truncate px-1 py-1 text-xs text-slate-500">' + escapeHtml(item.filename) + '</span>' +
                    '</label>';
            }).join('');
        } catch (error) {
            container.innerHTML = '<p class="col-span-full text-sm text-red-600">Erro ao carregar as imagens.</p>';
        }
    }

    function onMediaPick(input) {
        if (mediaPickerMode() === 'single') {
            document.querySelectorAll('#media-picker-results input[name="media_single"]').forEach(function (other) {
                other.checked = other === input && input.checked;
            });
        }

        updateMediaPickerCount();
    }

    function usePickerSelection() {
        const modal = document.getElementById('media-picker');
        const checked = document.querySelector('#media-picker-results input[name="media_single"]:checked');

        if (!checked) {
            return;
        }

        document.querySelector(modal.dataset.target).value = checked.value;

        if (modal.dataset.preview) {
            const preview = document.querySelector(modal.dataset.preview);
            preview.src = checked.dataset.url;
            preview.classList.remove('hidden');
        }

        closeMediaPicker();
    }

    function updateMediaPickerCount() {
        const selector = mediaPickerMode() === 'single'
            ? '#media-picker-results input[name="media_single"]:checked'
            : '#media-picker-results input[name="media_ids[]"]:checked';
        const total = document.querySelectorAll(selector).length;

        document.getElementById('media-picker-count').textContent =
            total === 0 ? 'Nenhuma selecionada' : (total === 1 ? '1 selecionada' : total + ' selecionadas');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
</script>
