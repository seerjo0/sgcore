@extends('core::admin.layout')

@section('title', __('updater.page_title'))

@section('content')
    <div class="mb-4">
        <h2 class="text-xl font-semibold">{{ __('updater.page_title') }}</h2>
        <p class="text-sm text-slate-500">{{ __('nav.updates') }} · {{ $repo }}</p>
    </div>

    @if (($maintenance['down'] ?? false))
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">{{ __('updater.maintenance_title') }}</p>
            <p class="mt-1">{{ __('updater.maintenance_note', ['since' => $maintenance['since'] !== '' ? $maintenance['since'] : '—']) }}</p>
            @if ($maintenance['stale'])
                <p class="mt-1">{{ __('updater.maintenance_stale') }}</p>
            @endif
            <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.manutencao.sair') }}" class="mt-3">
                @csrf
                <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                    {{ __('updater.maintenance_release') }}
                </button>
            </form>
        </div>
    @endif

    @if (! $updaterEnabled)
        <div class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-600">
            {{ __('updater.disabled') }}
        </div>
    @else
        @if ($update)
            <div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ __('updater.available', ['version' => $update['tag']]) }}
            </div>
        @else
            <div class="mb-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                {{ __('updater.up_to_date') }}
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Version --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-slate-800">{{ __('updater.current') }}</h3>
                <p class="font-mono text-2xl font-semibold text-slate-900">{{ $current }}</p>
                @if ($update)
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('updater.available_version') }}:
                        <span class="font-mono font-semibold text-emerald-700">{{ $update['tag'] }}</span>
                    </p>
                @endif
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

            {{-- Automatic policy --}}
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-1 text-sm font-semibold text-slate-800">{{ __('updater.auto') }}</h3>
                <p class="mb-3 text-xs text-slate-500">{{ __('updater.auto_note') }}</p>

                <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.config') }}" class="space-y-3">
                    @csrf

                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="auto_check" value="1" @checked($autoCheck)
                               class="mt-0.5 rounded border-slate-300">
                        <span>
                            {{ __('updater.auto_check') }}
                            <span class="block text-xs text-slate-500">{{ __('updater.auto_check_note') }}</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-2 text-sm {{ $packageEnabled && $downloadEnabled ? 'text-slate-700' : 'text-slate-400' }}">
                        <input type="checkbox" name="auto_download" value="1" @checked($autoDownload) @disabled(! $packageEnabled || ! $downloadEnabled)
                               class="mt-0.5 rounded border-slate-300">
                        <span>
                            {{ __('updater.auto_download') }}
                            <span class="block text-xs text-slate-500">{{ __('updater.auto_download_note') }}</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-2 text-sm {{ $applyEnabled && $packageEnabled ? 'text-slate-700' : 'text-slate-400' }}">
                        <input type="checkbox" name="auto_install" value="1" @checked($autoInstall) @disabled(! $applyEnabled || ! $packageEnabled)
                               class="mt-0.5 rounded border-slate-300">
                        <span>
                            {{ __('updater.auto_install') }}
                            <span class="block text-xs text-slate-500">{{ __('updater.auto_install_note') }}</span>
                        </span>
                    </label>

                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        {{ __('updater.auto_save') }}
                    </button>
                </form>

                @if (is_array($autoLast))
                    <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-600">
                        {{ __('updater.auto_last', [
                            'from' => $autoLast['from'] ?? '?',
                            'to' => $autoLast['to'] ?? '?',
                            'status' => $autoLast['status'] ?? '?',
                            'when' => isset($autoLast['at']) ? \Illuminate\Support\Carbon::parse($autoLast['at'])->translatedFormat('d/m/Y H:i') : '?',
                        ]) }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Package: download now, install later --}}
        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-800">{{ __('updater.package') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('updater.package_note') }}</p>

            @if ($stored)
                <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="font-mono font-semibold text-slate-900">{{ $stored['version'] }}</span>
                        <span class="text-xs text-slate-500">{{ number_format($stored['bytes'] / 1048576, 1, ',', '.') }} MB</span>
                        @if ($stored['downloaded_at'] !== '')
                            <span class="text-xs text-slate-500">{{ __('updater.package_downloaded_at') }}
                                {{ \Illuminate\Support\Carbon::parse($stored['downloaded_at'])->translatedFormat('d/m/Y H:i') }}</span>
                        @endif
                        @if ($storedNewer)
                            <span class="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ __('updater.package_ready') }}</span>
                        @else
                            <span class="inline-block rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ __('updater.package_same_or_older') }}</span>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap items-end gap-3">
                        @if ($storedNewer && $applyEnabled && $packageEnabled)
                            <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.instalar') }}" class="space-y-2">
                                @csrf
                                <p class="text-xs text-slate-500">{{ __('updater.package_install_warning') }}</p>
                                <label class="flex items-center gap-2 text-xs text-slate-600">
                                    <input type="checkbox" name="confirm" value="1" required class="rounded border-slate-300">
                                    {{ __('updater.package_install_confirm') }}
                                </label>
                                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                    {{ __('updater.package_install', ['version' => $stored['version']]) }}
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.pacote.descartar') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                {{ __('updater.package_discard') }}
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <p class="mt-3 text-sm text-slate-500">{{ __('updater.package_none') }}</p>
            @endif

            @if (! $packageEnabled || ! $downloadEnabled)
                <p class="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    {{ __('updater.package_disabled') }}
                </p>
            @elseif ($update && $packageAsset)
                <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.baixar') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                        {{ __('updater.package_download', ['version' => $update['tag']]) }}
                        ({{ number_format(($packageAsset['size'] ?? 0) / 1048576, 1) }} MB)
                    </button>
                </form>
            @elseif ($update)
                <p class="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    {{ __('updater.package_no_asset') }}
                </p>
            @endif
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

                        <div class="border-t border-slate-100 pt-4 text-xs text-slate-500 md:border-l md:border-t-0 md:pt-0 md:pl-6">
                            <p class="font-medium text-slate-600">{{ __('updater.method_package') }}</p>
                            <p class="mt-1">{{ __('updater.package_note') }}</p>
                        </div>
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
