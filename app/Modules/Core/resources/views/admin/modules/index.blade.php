@extends('core::admin.layout')

@section('title', __('modules.title'))

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold">{{ __('modules.title') }}</h2>
            <p class="text-sm text-slate-500">{{ __('modules.subtitle') }}</p>
        </div>

        <button type="button" id="toggle-core"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
            <span data-label-show>{{ __('modules.show_core') }}</span>
            <span data-label-hide class="hidden">{{ __('modules.hide_core') }}</span>
        </button>
    </div>

    <form method="POST" action="{{ route('admin.modulos.store') }}" enctype="multipart/form-data"
          class="mb-6 flex flex-wrap items-end gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        @csrf
        <div class="min-w-64">
            <label for="module" class="block text-sm font-medium text-slate-700">{{ __('modules.import_label') }}</label>
            <input id="module" type="file" name="module" accept=".zip,application/zip" required
                   class="mt-1 block w-full text-sm text-slate-600">
            @error('module')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-slate-500">{!! __('modules.import_help', ['maxMb' => $maxUploadMb]) !!}</p>
        </div>
        <button type="submit"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
            {{ __('modules.import_button') }}
        </button>
    </form>

    <p class="mb-4 text-xs text-slate-500">
        {{ __('modules.active_count', ['count' => $activeCount, 'total' => $modules->count()]) }}
    </p>

    <h3 class="mb-3 font-semibold text-slate-700">{{ __('modules.uploaded_section_title') }}</h3>

    @if ($uploaded->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            {{ __('modules.no_uploaded') }}
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($uploaded as $module)
                @php
                    $isActive = ! in_array($module->slug, $inactive, true);
                    $isProtected = in_array($module->slug, $protected, true);
                @endphp
                @include('core::admin.modules.card', ['module' => $module, 'isActive' => $isActive, 'isProtected' => $isProtected])
            @endforeach
        </div>
    @endif

    <section id="core-modules" class="hidden mt-8">
        <h3 class="mb-3 font-semibold text-slate-700">{{ __('modules.core_section_title') }}</h3>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($bundled as $module)
                @php
                    $isActive = ! in_array($module->slug, $inactive, true);
                    $isProtected = in_array($module->slug, $protected, true);
                @endphp
                @include('core::admin.modules.card', ['module' => $module, 'isActive' => $isActive, 'isProtected' => $isProtected])
            @endforeach
        </div>
    </section>

    <script>
        (function () {
            const key = 'sgcore.showCoreModules';
            const section = document.getElementById('core-modules');
            const button = document.getElementById('toggle-core');
            const showLabel = button.querySelector('[data-label-show]');
            const hideLabel = button.querySelector('[data-label-hide]');
            let shown = localStorage.getItem(key) === '1';

            function apply() {
                section.classList.toggle('hidden', !shown);
                showLabel.classList.toggle('hidden', shown);
                hideLabel.classList.toggle('hidden', !shown);
            }

            button.addEventListener('click', function () {
                shown = !shown;
                localStorage.setItem(key, shown ? '1' : '0');
                apply();
            });

            apply();
        })();
    </script>
@endsection