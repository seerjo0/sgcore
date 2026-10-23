@extends('core::admin.layout')

@section('title', __('updater.progress_title'))

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 class="text-xl font-semibold">{{ __('updater.progress_title') }}</h2>
            <p class="text-sm text-slate-500">
                {{ __('updater.progress_subtitle', ['from' => $from, 'version' => $version]) }}
            </p>
        </div>
        <a href="{{ $backUrl }}" class="text-sm text-slate-500 hover:text-slate-700">
            {{ __('updater.progress_back') }}
        </a>
    </div>

    @if ($maintenance['down'])
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">{{ __('updater.maintenance_title') }}</p>
            <p class="mt-1">{{ __('updater.maintenance_note', ['since' => $maintenance['since'] !== '' ? $maintenance['since'] : '—']) }}</p>
            <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.manutencao.sair') }}" class="mt-3">
                @csrf
                <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                    {{ __('updater.maintenance_release') }}
                </button>
            </form>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-950 shadow-xl">
        <div class="flex items-center justify-between gap-3 border-b border-slate-800 bg-slate-900 px-4 py-2.5">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                <span class="ml-2 font-mono text-xs text-slate-400">sgcore update</span>
            </div>
            <span class="rounded-full bg-slate-800 px-2 py-0.5 font-mono text-xs text-slate-300">
                {{ $from }} → {{ $version }}
            </span>
        </div>

        <div class="px-4 pb-4 pt-3">
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-800">
                <div id="update-bar" class="h-full w-0 rounded-full bg-emerald-500 transition-all duration-300" style="width: 0%"></div>
            </div>

            <div class="mt-2 flex items-center justify-between gap-3 font-mono text-xs text-slate-400">
                <span id="update-stage" class="truncate">{{ __('updater.progress_waiting') }}</span>
                <span id="update-percent" class="shrink-0">0%</span>
            </div>

            <pre id="update-log" class="mt-3 h-72 overflow-y-auto whitespace-pre-wrap break-words font-mono text-xs leading-6 text-slate-300" aria-live="polite" aria-atomic="false"></pre>
        </div>
    </div>

    <div id="update-summary" class="mt-4 hidden rounded-lg border px-4 py-3 text-sm"></div>

    <p class="mt-3 text-xs text-slate-500">{{ __('updater.progress_note') }}</p>

    @php
        $updateConfig = [
            'url' => $streamUrl,
            'csrf' => csrf_token(),
            'mode' => $mode,
            'from' => $from,
            'version' => $version,
            'busy' => $maintenance['busy'],
            'running' => __('updater.progress_running'),
            'starting' => __('updater.progress_starting'),
            'back' => __('updater.progress_back_short'),
        ];
    @endphp

    <script>
        (function () {
            const cfg = @json($updateConfig, JSON_UNESCAPED_SLASHES);

            const log = document.getElementById('update-log');
            const bar = document.getElementById('update-bar');
            const stage = document.getElementById('update-stage');
            const percent = document.getElementById('update-percent');
            const summary = document.getElementById('update-summary');

            const colors = {
                info: 'text-slate-300',
                warn: 'text-amber-300',
                ok: 'text-emerald-400',
                error: 'text-red-400',
            };

            function line(text, type) {
                const row = document.createElement('div');
                row.className = 'whitespace-pre-wrap ' + (colors[type] || colors.info);
                row.textContent = '[' + new Date().toLocaleTimeString() + '] ' + text;
                log.appendChild(row);
                log.scrollTop = log.scrollHeight;
            }

            function progress(value) {
                const pct = Math.max(0, Math.min(100, Number(value) || 0));
                bar.style.width = pct + '%';
                percent.textContent = pct + '%';
            }

            function finish(event) {
                summary.classList.remove('hidden');
                summary.className = 'mt-4 rounded-lg border px-4 py-3 text-sm ' + (event.ok
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                    : 'border-red-300 bg-red-50 text-red-800');

                summary.textContent = event.message;

                const link = document.createElement('a');
                link.href = event.redirect;
                link.className = 'ml-2 font-semibold underline';
                link.textContent = cfg.back;
                summary.appendChild(link);

                if (event.ok) {
                    setTimeout(function () { window.location.href = event.redirect; }, 2500);
                }
            }

            function handle(event) {
                if (event.message) {
                    line(event.message, event.type);
                }

                if (typeof event.percent === 'number') {
                    progress(event.percent);
                }

                if (event.message) {
                    stage.textContent = event.message;
                }

                if (event.done) {
                    finish(event);
                }
            }

            async function start() {
                if (cfg.busy) {
                    line(cfg.running, 'warn');
                    setTimeout(function () { window.location.reload(); }, 5000);
                    return;
                }

                line('$ sgcore update --from ' + cfg.from + ' --to ' + cfg.version, 'info');
                line(cfg.starting, 'info');

                let response;

                try {
                    response = await fetch(cfg.url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': cfg.csrf,
                            'Accept': 'application/x-ndjson',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        },
                        body: 'modo=' + encodeURIComponent(cfg.mode),
                    });
                } catch (error) {
                    line(String(error), 'error');
                    return;
                }

                if (! response.ok || ! response.body) {
                    line('HTTP ' + response.status, 'error');
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    let chunk;

                    try {
                        chunk = await reader.read();
                    } catch (error) {
                        line(String(error), 'error');
                        return;
                    }

                    if (chunk.done) {
                        break;
                    }

                    buffer += decoder.decode(chunk.value, {stream: true});

                    let index;

                    while ((index = buffer.indexOf('\n')) >= 0) {
                        const raw = buffer.slice(0, index).trim();
                        buffer = buffer.slice(index + 1);

                        if (raw === '') {
                            continue;
                        }

                        try {
                            handle(JSON.parse(raw));
                        } catch (error) {
                            line(raw, 'info');
                        }
                    }
                }
            }

            start();
        })();
    </script>
@endsection
