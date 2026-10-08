@extends('core::admin.layout')

@section('title', $page ? 'Conteúdo — '.$page->title : 'Conteúdo do tema')

@section('content')
    @php
        $oldSlots = old('slots', []);
        $formAction = $page
            ? route('admin.aparencia.conteudo.pagina.salvar', $page)
            : route('admin.aparencia.conteudo.salvar');
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Conteúdo do tema</h2>
            <p class="text-sm text-slate-500">
                Tema ativo: <span class="font-medium text-slate-700">{{ $theme->name() }}</span>
                @if ($page)
                    · Página: <span class="font-medium text-slate-700">{{ $page->title }}</span>
                @endif
            </p>
        </div>
        <div class="flex gap-3 text-sm">
            @if ($page)
                <a href="{{ route('admin.aparencia.conteudo') }}" class="text-slate-500 hover:text-slate-700">Conteúdo global</a>
            @endif
            <a href="{{ route('admin.aparencia.index') }}" class="text-slate-500 hover:text-slate-700">Voltar para Aparência</a>
        </div>
    </div>

    <form method="POST" action="{{ $formAction }}" class="max-w-3xl">
        @csrf

        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">{{ $page ? 'Slots da página' : 'Slots globais' }}</h3>
            <p class="mt-1 text-sm text-slate-500">
                {{ $page
                    ? 'Conteúdo específico desta página nos elementos do tema.'
                    : 'Conteúdo compartilhado por todas as páginas (cabeçalho, rodapé, identidade).' }}
            </p>

            @if ($slots === [])
                <p class="mt-5 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                    Este tema não declara slots editáveis neste escopo.
                </p>
            @endif

            @foreach ($slots as $id => $spec)
                @php
                    $type = $spec['type'] ?? 'text';
                    $label = $spec['label'] ?? $id;
                    $current = $oldSlots[$id] ?? ($values[$id] ?? '');
                @endphp

                <div class="mt-5 border-t border-slate-100 pt-5 first:mt-4 first:border-t-0 first:pt-0">
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ $label }}</label>
                    <p class="mb-2 text-xs text-slate-400">
                        Slot: <code>{{ $id }}</code> · tipo: {{ $type }}
                        @if (! empty($spec['template']))
                            · template: {{ $spec['template'] }}
                        @endif
                    </p>

                    @switch($type)
                        @case('richtext')
                            <textarea name="slots[{{ $id }}]" rows="8"
                                      class="w-full rounded border-slate-300 font-mono text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ $current }}</textarea>
                            <p class="mt-1 text-xs text-slate-400">
                                Tags permitidas: p, br, strong, em, a, img, ul, ol, li, h2–h4, blockquote, hr, code, pre.
                                Scripts e estilos são removidos ao salvar.
                            </p>
                        @break

                        @case('image')
                        @case('logo')
                            @php
                                $previewId = 'slot-preview-'.md5($id);
                                $previewUrl = $previews[$id] ?? '';
                            @endphp
                            <input type="hidden" name="slots[{{ $id }}]" value="{{ $current }}">
                            <div class="flex items-center gap-3">
                                <img id="{{ $previewId }}" src="{{ $previewUrl }}" alt=""
                                     class="h-16 w-16 rounded border border-slate-200 object-cover {{ $previewUrl !== '' ? '' : 'hidden' }}">
                                <button type="button" onclick="pickSlotImage('slots[{{ $id }}]', '{{ $previewId }}')"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">
                                    Escolher da biblioteca
                                </button>
                                <button type="button" onclick="clearSlotImage('slots[{{ $id }}]', '{{ $previewId }}')"
                                        class="text-sm text-slate-500 hover:text-red-600">Remover</button>
                            </div>
                        @break

                        @case('gallery')
                            <select name="slots[{{ $id }}]"
                                    class="w-full rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">— Nenhuma —</option>
                                @foreach ($galleries as $gallery)
                                    <option value="{{ $gallery->id }}"
                                            @selected((string) $current === (string) $gallery->id)>{{ $gallery->title }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-400">As imagens e legendas vêm de Galerias.</p>
                        @break

                        @default
                            <input type="text" name="slots[{{ $id }}]" maxlength="500"
                                   value="{{ $current }}"
                                   class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @endswitch

                    @error('slots.'.$id)
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="mt-6 flex items-center gap-3 border-t border-slate-100 pt-5">
                <button type="submit"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                    {{ $page ? 'Salvar conteúdo da página' : 'Salvar conteúdo global' }}
                </button>
                @if ($page)
                    <a href="{{ route('admin.aparencia.conteudo') }}"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
                @endif
            </div>
        </div>
    </form>

    @if ($page === null)
        <div class="mt-6 max-w-3xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">Conteúdo por página</h3>
            <p class="mt-1 text-sm text-slate-500">
                Slots com escopo de página (hero, conteúdo, galeria) são editados em cada página.
            </p>

            <ul class="mt-4 divide-y divide-slate-100">
                @forelse ($pages as $item)
                    <li class="flex items-center justify-between py-2">
                        <span class="text-sm">
                            {{ $item->title }}
                            @if ($item->is_home)
                                <span class="ml-1 rounded bg-primary-100 px-1.5 py-0.5 text-xs font-semibold text-primary-700">Início</span>
                            @endif
                            @if (! $item->isPublished())
                                <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500">Rascunho</span>
                            @endif
                        </span>
                        <a href="{{ route('admin.aparencia.conteudo.pagina', $item) }}"
                           class="text-sm text-primary-600 hover:text-primary-500">Editar slots</a>
                    </li>
                @empty
                    <li class="py-2 text-sm text-slate-400">Nenhuma página cadastrada ainda.</li>
                @endforelse
            </ul>
        </div>
    @endif

    @include('media::picker', [
        'mode' => 'single',
        'targetField' => '',
        'title' => 'Escolher imagem do slot',
    ])

    <script>
        function pickSlotImage(name, previewId) {
            var picker = document.getElementById('media-picker');
            picker.dataset.target = '[name="' + name + '"]';
            picker.dataset.preview = '#' + previewId;
            openMediaPicker();
        }

        function clearSlotImage(name, previewId) {
            document.querySelector('[name="' + name + '"]').value = '';
            var preview = document.getElementById(previewId);
            preview.src = '';
            preview.classList.add('hidden');
        }
    </script>
@endsection
