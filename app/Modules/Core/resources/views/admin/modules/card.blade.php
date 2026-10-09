@php
    $isBundled = $module->isBundled();
@endphp
<div class="flex flex-col overflow-hidden rounded-lg border {{ $isActive ? 'border-primary-400 ring-1 ring-primary-300' : 'border-slate-200' }} bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
        <div class="flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded bg-slate-100 text-sm font-bold text-slate-500">
                {{ strtoupper(substr($module->slug, 0, 2)) }}
            </span>
            <div>
                <h3 class="text-sm font-semibold text-slate-800">{{ $module->name() }}</h3>
                <p class="text-xs text-slate-400">
                    <code>{{ $module->slug }}</code>
                    @if ($module->version() !== '')
                        · v{{ $module->version() }}
                    @endif
                </p>
            </div>
        </div>
        <div class="flex flex-col items-end gap-1">
            <span @class([
                'rounded px-1.5 py-0.5 text-xs font-semibold',
                'bg-primary-100 text-primary-700' => $isActive,
                'bg-slate-100 text-slate-600' => ! $isActive,
            ])>
                {{ $isActive ? __('modules.active') : __('modules.inactive') }}
            </span>
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600">
                {{ $isBundled ? __('modules.bundled') : __('modules.uploaded') }}
            </span>
        </div>
    </div>

    <div class="flex flex-1 flex-col gap-2 p-4">
        @if ($module->description() !== '')
            <p class="text-xs text-slate-500">{{ $module->description() }}</p>
        @endif

        @if ($module->author() !== '')
            <p class="text-xs text-slate-400">{{ __('modules.by', ['author' => $module->author()]) }}</p>
        @endif

        @if ($module->requires !== [])
            <p class="text-xs text-slate-400">
                {{ __('modules.requires', ['modules' => implode(', ', $module->requires)]) }}
            </p>
        @endif

        @if ($isProtected)
            <p class="text-xs text-slate-400">{{ __('modules.protected') }}</p>
        @endif

        @if (! $module->valid && $module->error !== null)
            <p class="rounded border border-red-200 bg-red-50 px-2 py-1.5 text-xs text-red-700">
                {{ $module->error }}
            </p>
        @endif

        <div class="mt-auto flex flex-wrap gap-2 pt-2">
            @if ($isBundled)
                <span class="inline-block rounded-lg bg-slate-100 px-4 py-2 text-center text-sm font-semibold text-slate-400">
                    {{ __('modules.bundled_locked') }}
                </span>
            @elseif ($isActive && $module->valid && ! $isProtected)
                <form method="POST" action="{{ route('admin.modulos.desativar', $module->slug) }}">
                    @csrf
                    <button type="submit"
                            class="rounded-lg border border-primary-600 px-4 py-2 text-sm font-semibold text-primary-600 hover:bg-primary-50">
                        {{ __('modules.deactivate') }}
                    </button>
                </form>
            @elseif (! $isActive && $module->valid)
                <form method="POST" action="{{ route('admin.modulos.ativar', $module->slug) }}">
                    @csrf
                    <button type="submit"
                            class="rounded-lg border border-primary-600 px-4 py-2 text-sm font-semibold text-primary-600 hover:bg-primary-50">
                        {{ __('modules.activate') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.modulos.destroy', $module->slug) }}"
                      onsubmit="return confirm('{{ __('modules.delete_confirm', ['name' => $module->name()]) }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="rounded-lg border border-red-600 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                        {{ __('modules.delete') }}
                    </button>
                </form>
            @else
                <span class="inline-block rounded-lg bg-slate-100 px-4 py-2 text-center text-sm font-semibold text-slate-400">
                    {{ __('modules.unavailable') }}
                </span>
            @endif
        </div>
    </div>
</div>