@extends('core::admin.layout')

@section('title', $page ? __('themes.page_title', ['title' => $page->title]) : __('themes.content_title'))

@section('content')
    @php
        $oldSlots = old('slots', []);
        $formAction = $page
            ? route('admin.aparencia.conteudo.pagina.salvar', $page)
            : route('admin.aparencia.conteudo.salvar');
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">{{ __('themes.content_title') }}</h2>
            <p class="text-sm text-slate-500">
                {{ __('themes.active_theme') }}: <span class="font-medium text-slate-700">{{ $theme->name() }}</span>
                @if ($page)
                    · {{ __('themes.page_label') }}: <span class="font-medium text-slate-700">{{ $page->title }}</span>
                @endif
            </p>
        </div>
        <div class="flex gap-3 text-sm">
            @if ($page)
                <a href="{{ route('admin.aparencia.conteudo') }}" class="text-slate-500 hover:text-slate-700">{{ __('themes.content_global') }}</a>
            @endif
            <a href="{{ route('admin.aparencia.index') }}" class="text-slate-500 hover:text-slate-700">{{ __('themes.back_appearance') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ $formAction }}" class="max-w-3xl">
        @csrf

        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">{{ $page ? __('themes.slots_page') : __('themes.slots_global') }}</h3>
            <p class="mt-1 text-sm text-slate-500">
                {{ $page ? __('themes.slots_page_help') : __('themes.slots_global_help') }}
            </p>

            @if ($slots === [])
                <p class="mt-5 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                    {{ __('themes.slots_none') }}
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
                        {{ __('themes.slot_type', ['id' => $id, 'type' => $type]) }}
                        @if (! empty($spec['template']))
                            · {{ __('themes.slot_template', ['template' => $spec['template']]) }}
                        @endif
                    </p>

                    @switch($type)
                        @case('richtext')
                            <textarea name="slots[{{ $id }}]" rows="8"
                                      class="w-full rounded border-slate-300 font-mono text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ $current }}</textarea>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ __('themes.richtext_tags') }}<br>
                                {{ __('themes.richtext_note') }}
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
                                    {{ __('themes.choose_library') }}
                                </button>
                                <button type="button" onclick="clearSlotImage('slots[{{ $id }}]', '{{ $previewId }}')"
                                        class="text-sm text-slate-500 hover:text-red-600">{{ __('themes.remove') }}</button>
                            </div>
                        @break

                        @case('gallery')
                            <select name="slots[{{ $id }}]"
                                    class="w-full rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">— {{ __('themes.gallery_none') }} —</option>
                                @foreach ($galleries as $gallery)
                                    <option value="{{ $gallery->id }}"
                                            @selected((string) $current === (string) $gallery->id)>{{ $gallery->title }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-400">{{ __('themes.gallery_help') }}</p>
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
                    {{ $page ? __('themes.save_page') : __('themes.save_global') }}
                </button>
                @if ($page)
                    <a href="{{ route('admin.aparencia.conteudo') }}"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">{{ __('common.cancel') }}</a>
                @endif
            </div>
        </div>
    </form>

    @if ($page === null)
        <div class="mt-6 max-w-3xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">{{ __('themes.per_page_heading') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('themes.per_page_help') }}</p>

            <ul class="mt-4 divide-y divide-slate-100">
                @forelse ($pages as $item)
                    <li class="flex items-center justify-between py-2">
                        <span class="text-sm">
                            {{ $item->title }}
                            @if ($item->is_home)
                                <span class="ml-1 rounded bg-primary-100 px-1.5 py-0.5 text-xs font-semibold text-primary-700">{{ __('pages.home_badge') }}</span>
                            @endif
                            @if (! $item->isPublished())
                                <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500">{{ __('pages.draft') }}</span>
                            @endif
                        </span>
                        <a href="{{ route('admin.aparencia.conteudo.pagina', $item) }}"
                           class="text-sm text-primary-600 hover:text-primary-500">{{ __('themes.edit_slots') }}</a>
                    </li>
                @empty
                    <li class="py-2 text-sm text-slate-400">{{ __('themes.per_page_empty') }}</li>
                @endforelse
            </ul>
        </div>
    @endif

    @include('media::picker', [
        'mode' => 'single',
        'targetField' => '',
        'title' => __('themes.pick_slot_image'),
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