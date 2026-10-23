<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="15">
    <title>{{ __('errors.maintenance_title') }} — {{ config('app.name', 'sgcore') }}</title>
    <style>
        :root{--ink:#1e293b;--muted:#64748b;--line:#e2e8f0;--bg:#f8fafc;--card:#fff;--accent:#4f46e5;--accent-soft:#eef2ff;--track:#e2e8f0;--dot:#4f46e5}
        @media (prefers-color-scheme:dark){:root{--ink:#e2e8f0;--muted:#94a3b8;--line:#1e293b;--bg:#0b1120;--card:#0f172a;--accent:#818cf8;--accent-soft:#1e1b4b;--track:#1e293b;--dot:#818cf8}}
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
             background:radial-gradient(1200px 600px at 20% -10%,var(--accent-soft),transparent 60%),var(--bg);
             color:var(--ink);font:16px/1.6 "Segoe UI",-apple-system,BlinkMacSystemFont,"Helvetica Neue",Arial,sans-serif}
        .wrap{width:100%;max-width:560px}
        .brand{font-weight:800;letter-spacing:-.02em;font-size:18px;color:var(--accent);margin-bottom:22px}
        .card{border:1px solid var(--line);border-radius:20px;background:var(--card);padding:32px;
              box-shadow:0 20px 60px -30px rgba(15,23,42,.35)}
        .badge{display:inline-flex;align-items:center;gap:8px;border-radius:999px;background:var(--accent-soft);
               color:var(--accent);font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:5px 11px}
        .badge .pulse{width:8px;height:8px;border-radius:50%;background:var(--dot);animation:pulse 1.4s ease-in-out infinite}
        @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(.75)}}
        h1{font-size:26px;line-height:1.2;letter-spacing:-.02em;margin:16px 0 8px}
        p{margin:0;color:var(--muted)}
        .bar{position:relative;height:6px;border-radius:999px;background:var(--track);overflow:hidden;margin:26px 0 10px}
        .bar::after{content:"";position:absolute;inset:0;width:38%;border-radius:999px;background:var(--accent);
                    animation:slide 1.8s ease-in-out infinite}
        @keyframes slide{0%{transform:translateX(-100%)}100%{transform:translateX(280%)}}
        .hint{display:flex;justify-content:space-between;gap:12px;font-size:12px;color:var(--muted);font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
        .actions{margin-top:26px;display:flex;flex-wrap:wrap;gap:10px}
        a.btn{display:inline-flex;align-items:center;gap:8px;text-decoration:none;font-weight:600;font-size:14px;
              padding:10px 16px;border-radius:10px;background:var(--accent);color:#fff}
        a.btn.ghost{background:transparent;color:var(--ink);border:1px solid var(--line)}
        a.btn:hover{filter:brightness(1.06)}
        .foot{margin-top:18px;font-size:12px;color:var(--muted);text-align:center}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">{{ config('app.name', 'sgcore') }}</div>

    <div class="card">
        <span class="badge"><span class="pulse"></span> {{ __('errors.maintenance_badge') }}</span>

        <h1>{{ __('errors.maintenance_title') }}</h1>
        <p>{{ __('errors.maintenance_text') }}</p>

        <div class="bar" role="progressbar" aria-label="{{ __('errors.maintenance_title') }}"></div>
        <div class="hint">
            <span>{{ __('errors.maintenance_eta') }}</span>
            <span>{{ __('errors.maintenance_code') }}</span>
        </div>

        <div class="actions">
            <a class="btn" href="javascript:location.reload()">{{ __('errors.maintenance_retry') }}</a>
            <a class="btn ghost" href="{{ url('/admin/configuracoes/atualizacoes') }}">{{ __('errors.maintenance_admin') }}</a>
        </div>
    </div>

    <p class="foot">{{ __('errors.maintenance_foot') }}</p>
</div>
</body>
</html>
