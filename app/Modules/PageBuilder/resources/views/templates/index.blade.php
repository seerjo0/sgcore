@extends('core::admin.layout')

@section('title', __('pagebuilder.templates_title'))

@section('content')
    <div class="mb-4">
        <h2 class="text-xl font-semibold">{{ __('pagebuilder.templates_title') }}</h2>
        <p class="text-sm text-slate-500">{{ __('pagebuilder.templates_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('admin.builder.templates.design') }}"
          class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        @csrf
        <h3 class="mb-1 text-sm font-semibold">{{ __('pagebuilder.design_system_title') }}</h3>
        <p class="mb-3 text-xs text-slate-500">{{ __('pagebuilder.design_system_subtitle') }}</p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark', 'light'] as $name)
                <label class="block text-xs font-medium text-slate-600">
                    {{ __('pagebuilder.color_'.$name) }}
                    <input type="color" name="colors[{{ $name }}]" value="{{ $tokens['colors'][$name] }}"
                           class="mt-1 h-9 w-full cursor-pointer rounded border border-slate-300 bg-white">
                </label>
            @endforeach
        </div>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-medium text-slate-600">
                {{ __('pagebuilder.font_body') }}
                <input type="text" name="fonts[body]" value="{{ $tokens['fonts']['body'] }}"
                       class="mt-1 w-full rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </label>
            <label class="block text-xs font-medium text-slate-600">
                {{ __('pagebuilder.font_heading') }}
                <input type="text" name="fonts[heading]" value="{{ $tokens['fonts']['heading'] }}"
                       class="mt-1 w-full rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </label>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <p class="col-span-full text-xs font-semibold text-slate-600">{{ __('pagebuilder.design_spacing_title') }}</p>
            @foreach (['xs', 'sm', 'md', 'lg'] as $name)
                <label class="block text-xs font-medium text-slate-600">
                    {{ strtoupper($name) }}
                    <input type="text" name="spacing[{{ $name }}]" value="{{ $tokens['spacing'][$name] }}"
                           class="mt-1 w-full rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </label>
            @endforeach
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <p class="col-span-full text-xs font-semibold text-slate-600">{{ __('pagebuilder.design_breakpoints_title') }}</p>
            @foreach (['sm', 'md', 'lg', 'xl'] as $name)
                <label class="block text-xs font-medium text-slate-600">
                    {{ $name }}
                    <input type="text" name="breakpoints[{{ $name }}]" value="{{ $tokens['breakpoints'][$name] }}"
                           class="mt-1 w-full rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </label>
            @endforeach
        </div>
        <div class="mt-3">
            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                {{ __('pagebuilder.btn_save_design') }}
            </button>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('pagebuilder.template_type') }}</th>
                    <th class="px-4 py-3">{{ __('pagebuilder.template_version') }}</th>
                    <th class="px-4 py-3">{{ __('pagebuilder.template_published_at') }}</th>
                    <th class="px-4 py-3">{{ __('pagebuilder.template_enabled') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100" id="pb-templates-body">
                @foreach ($templates as $template)
                    <tr data-type="{{ $template->type }}">
                        <td class="px-4 py-3 font-medium">{{ __(config('pagebuilder.templates')[$template->type]) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-field="version">{{ $template->version }}</td>
                        <td class="px-4 py-3 text-slate-500" data-field="published_at">
                            {{ $template->published_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span data-field="state"
                                  class="rounded px-1.5 py-0.5 text-xs font-semibold {{ $template->enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $template->enabled ? __('pagebuilder.template_on') : __('pagebuilder.template_off') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.builder.templates.edit', $template->type) }}"
                               class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('pagebuilder.open_editor') }}</a>
                            <button type="button"
                                    class="pb-toggle-template ml-3 font-medium text-slate-600 hover:text-slate-800"
                                    data-url="{{ route('admin.builder.templates.toggle', $template->type) }}"
                                    data-type="{{ $template->type }}"
                                    data-enabled="{{ $template->enabled ? '1' : '0' }}">
                                {{ $template->enabled ? __('pagebuilder.template_disable') : __('pagebuilder.template_enable') }}
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            document.querySelectorAll('.pb-toggle-template').forEach((button) => {
                button.addEventListener('click', async () => {
                    const enabled = button.dataset.enabled !== '1';
                    const row = button.closest('tr');

                    const response = await fetch(button.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ enabled }),
                    });

                    if (!response.ok) {
                        return;
                    }

                    const body = await response.json();
                    const state = row.querySelector('[data-field="state"]');

                    state.textContent = body.enabled
                        ? '{{ __('pagebuilder.template_on') }}'
                        : '{{ __('pagebuilder.template_off') }}';
                    state.className = 'rounded px-1.5 py-0.5 text-xs font-semibold '
                        + (body.enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500');
                    button.dataset.enabled = body.enabled ? '1' : '0';
                    button.textContent = body.enabled
                        ? '{{ __('pagebuilder.template_disable') }}'
                        : '{{ __('pagebuilder.template_enable') }}';
                });
            });
        })();
    </script>
@endsection