@php
    $isError = $status >= 500;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} · {{ $title }} — {{ config('app.name', 'sgcore') }}</title>
    <style>
        :root{--ink:#1e293b;--muted:#64748b;--line:#e2e8f0;--bg:#f8fafc;--card:#fff;--accent:#4f46e5;--code:#0f172a;--codeink:#e2e8f0;--err:#dc2626;--cur:#7f1d1d;--curbg:#fef2f2}
        @media (prefers-color-scheme:dark){:root{--ink:#e2e8f0;--muted:#94a3b8;--line:#1e293b;--bg:#0b1120;--card:#0f172a;--code:#020617;--curbg:#1c1010}}
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.6 "Segoe UI",-apple-system,BlinkMacSystemFont,"Helvetica Neue",Arial,sans-serif;display:flex;min-height:100vh;align-items:flex-start;justify-content:center;padding:6vh 16px}
        .wrap{width:100%;max-width:760px}
        .brand{font-weight:800;letter-spacing:-.02em;font-size:18px;color:var(--accent);margin-bottom:18px}
        .code{font-size:72px;font-weight:800;line-height:1;letter-spacing:-.04em}
        .title{font-size:22px;font-weight:700;margin:6px 0 4px}
        .msg{color:var(--muted);margin:0}
        .bar{height:5px;width:72px;border-radius:999px;background:var(--accent);margin:14px 0 18px}
        .bar.err{background:var(--err)}
        .actions{margin-top:22px;display:flex;gap:10px;flex-wrap:wrap}
        a.btn{display:inline-flex;align-items:center;gap:8px;text-decoration:none;font-weight:600;font-size:14px;padding:10px 16px;border-radius:10px;background:var(--accent);color:#fff}
        a.btn.ghost{background:transparent;color:var(--ink);border:1px solid var(--line)}
        a.btn:hover{filter:brightness(1.06)}
        .tech{margin-top:26px;border:1px solid var(--line);border-radius:14px;background:var(--card);overflow:hidden}
        .tech>h2{margin:0;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:12px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center}
        .badge{font-size:11px;font-weight:700;color:#fff;background:var(--err);padding:2px 8px;border-radius:999px}
        .exc{padding:14px 16px;border-bottom:1px solid var(--line)}
        .exc .cls{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;color:var(--accent);word-break:break-all}
        .exc .emsg{font-size:15px;font-weight:600;margin-top:4px;word-break:break-word}
        .exc .where{font-family:ui-monospace,Menlo,monospace;font-size:12px;color:var(--muted);margin-top:6px;word-break:break-all}
        .sec{padding:0 16px}
        .sec h3{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:14px 0 6px}
        .snippet{margin:0;padding:0;list-style:none;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;background:var(--code);color:var(--codeink);border-radius:10px;overflow:auto}
        .snippet li{display:flex;gap:12px;padding:1px 12px;white-space:pre}
        .snippet li.hl{background:rgba(148,163,184,.12)}
        .snippet li.at{background:var(--curbg);color:var(--cur)}
        .snippet li .n{color:var(--muted);min-width:2.5ch;text-align:right;user-select:none}
        .trace{margin:0;padding:0;list-style:none;font-family:ui-monospace,Menlo,monospace;font-size:12px;color:var(--muted)}
        .trace li{padding:3px 0;border-bottom:1px dashed var(--line);word-break:break-all}
        .trace .call{color:var(--ink)}
        dl{margin:0;display:grid;grid-template-columns:auto 1fr;gap:4px 14px;font-size:13px;font-family:ui-monospace,Menlo,monospace}
        dt{color:var(--muted)}dd{margin:0;word-break:break-all}
        details{margin:12px 16px 16px;border:1px solid var(--line);border-radius:10px;padding:8px 12px;background:var(--bg)}
        summary{cursor:pointer;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
        .foot{margin-top:26px;color:var(--muted);font-size:12px;text-align:center}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">{{ config('app.name', 'sgcore') }}</div>
    <div class="code">{{ $status }}</div>
    <div class="bar {{ $isError ? 'err' : '' }}"></div>
    <div class="title">{{ $title }}</div>
    <p class="msg">{{ $message }}</p>

    <div class="actions">
        <a class="btn" href="{{ url('/') }}">{{ __('errors.back_home') }}</a>
        <a class="btn ghost" href="javascript:history.back()">{{ __('errors.back') }}</a>
    </div>

    @if ($debug && $exception)
        <div class="tech">
            <h2><span>{{ __('errors.technical') }}</span> <span class="badge">{{ $exception['class'] ? \Illuminate\Support\Str::afterLast($exception['class'], '\\') : '' }}</span></h2>

            <div class="exc">
                <div class="cls">{{ $exception['class'] }}</div>
                <div class="emsg">{{ $exception['message'] !== '' ? $exception['message'] : __('errors.no_message') }}</div>
                <div class="where">{{ $exception['file'] }} : {{ $exception['line'] }}</div>
                @if ($exception['previous'])
                    <div class="where">← {{ $exception['previous']['class'] }}: {{ $exception['previous']['message'] }}</div>
                @endif
            </div>

            @if ($exception['snippet'])
                <div class="sec">
                    <h3>{{ __('errors.snippet') }}</h3>
                    <ol class="snippet">
                        @foreach ($exception['snippet']['rows'] as $row)
                            <li class="{{ $row['is_at'] ? 'at' : ($row['highlight'] ? 'hl' : '') }}">
                                <span class="n">{{ $row['n'] }}</span><span>{{ $row['code'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if (! empty($exception['trace']))
                <div class="sec">
                    <h3>{{ __('errors.trace') }}</h3>
                    <ul class="trace">
                        @foreach ($exception['trace'] as $i => $frame)
                            <li>
                                #{{ $i }} <span class="call">{{ $frame['call'] !== '' ? $frame['call'] : '{main}' }}</span>
                                @if ($frame['file'] !== '[internal]') <span> — {{ $frame['file'] }}:{{ $frame['line'] }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($request)
                <details open>
                    <summary>{{ __('errors.request') }}</summary>
                    <dl style="padding:10px 4px 4px">
                        <dt>{{ __('errors.method') }}</dt><dd>{{ $request['method'] }}</dd>
                        <dt>{{ __('errors.uri') }}</dt><dd>{{ $request['uri'] }}</dd>
                        <dt>{{ __('errors.when') }}</dt><dd>{{ $request['time'] }}</dd>
                        <dt>{{ __('errors.ip') }}</dt><dd>{{ $request['ip'] }}</dd>
                        <dt>{{ __('errors.user') }}</dt><dd>{{ $request['user'] }}</dd>
                    </dl>
                </details>
                <details>
                    <summary>{{ __('errors.headers') }}</summary>
                    <dl style="padding:10px 4px 4px">
                        @foreach ($request['headers'] as $name => $values)
                            <dt>{{ $name }}</dt><dd>{{ implode(', ', (array) $values) }}</dd>
                        @endforeach
                    </dl>
                </details>
            @endif
        </div>
        <p class="foot">{{ __('errors.debug_note') }}</p>
    @endif
</div>
</body>
</html>
