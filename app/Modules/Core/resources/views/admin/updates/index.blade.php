@extends('core::admin.layout')

@section('title', __('updater.page_title'))

@section('content')
    <div class="mb-4">
        <h2 class="text-xl font-semibold">{{ __('updater.page_title') }}</h2>
        <p class="text-sm text-slate-500">{{ __('nav.updates') }} · {{ $repo }}</p>
    </div>

    @if (! $updaterEnabled)
        <div class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-600">
            {{ __('updater.disabled') }}
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Version --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-slate-800">{{ __('updater.current') }}</h3>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ __('updater.current') }}</dt>
                        <dd class="font-mono font-semibold">{{ $current }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ __('updater.available') }}</dt>
                        <dd class="font-mono font-semibold">
                            @if ($update)
                                {{ $update['tag'] }}
                            @else
                                <span class="text-slate-400">{{ __('updater.up_to_date', ['version' => $current]) }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.verificar') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            {{ __('updater.check_again') }}
                        </button>
                    </form>
                    @if ($releaseUrl)
                        <a href="{{ $releaseUrl }}" target="_blank" rel="noopener"
                           class="rounded-lg border border-primary-600 px-3 py-1.5 text-sm font-medium text-primary-600 hover:bg-primary-50">
                            {{ __('updater.view_release') }}
                        </a>
                    @endif
                </div>
            </div>

            {{-- Automatic --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-slate-800">{{ __('updater.auto') }}</h3>
                <p class="text-sm">
                    @if ($autoEnabled && $applyEnabled)
                        <span class="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ __('updater.auto_enabled') }}</span>
                    @else
                        <span class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">{{ __('updater.auto_disabled') }}</span>
                    @endif
                </p>
                <p class="mt-2 text-xs text-slate-500">{{ __('updater.auto_note') }}</p>
                @if (is_array($autoLast))
                    <p class="mt-2 text-xs text-slate-600">
                        {{ __('updater.auto_last', [
                            'from' => $autoLast['from'] ?? '?',
                            'to' => $autoLast['to'] ?? '?',
                            'status' => $autoLast['status'] ?? '?',
                            'when' => isset($autoLast['at']) ? \Illuminate\Support\Carbon::parse($autoLast['at'])->translatedFormat('d/m/Y H:i') : '?',
                        ]) }}
                    </p>
                @else
                    <p class="mt-2 text-xs text-slate-400">—</p>
                @endif
            </div>
        </div>

        {{-- Manual --}}
        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-800">{{ __('updater.manual') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('updater.manual_note') }}</p>

            @if (! $applyEnabled)
                <p class="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    {{ __('updater.apply_disabled') }}
                </p>
            @else
                @if ($update)
                    <p class="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        {{ __('updater.apply_warning') }}
                    </p>

                    <div class="mt-4 grid gap-6 md:grid-cols-2">
                        {{-- Git one-click --}}
                        <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.aplicar') }}" class="space-y-2">
                            @csrf
                            <label class="block text-xs font-medium text-slate-600">{{ __('updater.method_git') }} → {{ $update['tag'] }}</label>
                            <p class="text-xs text-slate-400">{{ __('updater.apply_confirm_label', ['version' => $update['tag']]) }}</p>
                            <input name="confirm" required placeholder="{{ $update['tag'] }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                {{ __('updater.apply') }}
                            </button>
                        </form>

                        {{-- Package: download the release asset --}}
                        @if ($packageEnabled && $downloadEnabled && $packageAsset)
                            <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.baixar') }}" class="space-y-2 border-t border-slate-100 pt-4 md:border-l md:border-t-0 md:pt-0 md:pl-6">
                                @csrf
                                <label class="block text-xs font-medium text-slate-600">
                                    {{ __('updater.method_package') }} · {{ __('updater.package_download') }}
                                </label>
                                <p class="text-xs text-slate-400">{{ __('updater.apply_confirm_label', ['version' => $update['version'] ?? $update['tag']]) }}</p>
                                <input name="confirm" required placeholder="{{ $update['version'] ?? $update['tag'] }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
                                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                                    {{ __('updater.package_download') }} ({{ number_format(($packageAsset['size'] ?? 0) / 1048576, 1) }} MB)
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                {{-- Package upload (always available to apply an offline .zip) --}}
                @if ($packageEnabled)
                    <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.pacote') }}" enctype="multipart/form-data"
                         class="mt-6 space-y-2 border-t border-slate-100 pt-4">
                        @csrf
                        <label class="block text-xs font-medium text-slate-600">{{ __('updater.package_upload') }}</label>
                        <input type="file" name="package" accept=".zip,application/zip" required
                               class="block w-full text-sm text-slate-600">
                        <p class="text-xs text-slate-400">{{ __('updater.apply_confirm_label', ['version' => $update['version'] ?? $update['tag'] ?? $current]) }}</p>
                        <input name="confirm" required placeholder="{{ $update['version'] ?? $update['tag'] ?? __('updater.current') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                            {{ __('updater.package_upload') }}
                        </button>
                    </form>
                @endif
            @endif
        </div>
    @endif
@endsection
