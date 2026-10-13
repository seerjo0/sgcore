<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($seoTitle ?? '') !== '' ? $seoTitle.' · '.$siteName : $siteName }}</title>
    @if (($seoDescription ?? '') !== '')
        <meta name="description" content="{{ $seoDescription }}">
    @endif
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ ($seoTitle ?? '') !== '' ? $seoTitle : $siteName }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if (($ogImage ?? '') !== '')
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; color: #1e293b; background: #f8fafc; line-height: 1.6; }
        a { color: #4f46e5; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 0 20px; }
        header.site { background: #fff; border-bottom: 1px solid #e2e8f0; }
        header.site .bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 0; }
        header.site .brand { font-weight: 700; font-size: 18px; color: #0f172a; }
        header.site nav a { color: #475569; font-size: 14px; }
        main { padding: 32px 0 56px; }
        h1.page-title { font-size: 26px; margin: 0 0 24px; }
        .post-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 22px; margin-bottom: 16px; }
        .post-card h2 { font-size: 19px; margin: 0 0 6px; }
        .post-card h2 a { color: #0f172a; }
        .meta { color: #64748b; font-size: 13px; margin-bottom: 8px; }
        .excerpt { color: #334155; margin: 0; }
        .filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
        .chip { border: 1px solid #cbd5e1; border-radius: 999px; padding: 4px 12px; font-size: 13px; color: #475569; background: #fff; }
        .chip[aria-current="page"] { background: #4f46e5; border-color: #4f46e5; color: #fff; }
        article.post h1 { font-size: 30px; margin: 0 0 8px; }
        article.post .cover { width: 100%; height: auto; border-radius: 12px; margin: 16px 0; }
        article.post .content { font-size: 17px; color: #0f172a; }
        article.post .content img { max-width: 100%; height: auto; }
        .empty { text-align: center; color: #64748b; padding: 48px 0; }
        .pager { display: flex; justify-content: space-between; gap: 12px; margin-top: 24px; font-size: 14px; }
        footer.site { border-top: 1px solid #e2e8f0; background: #fff; padding: 20px 0; color: #64748b; font-size: 13px; }
    </style>
</head>
<body>
<header class="site">
    <div class="wrap bar">
        <a class="brand" href="{{ route('site.home') }}">{{ $siteName }}</a>
        <nav><a href="{{ route('blog.index') }}">{{ __('blog.title') }}</a></nav>
    </div>
</header>

<main>
    <div class="wrap">
        @yield('content')
    </div>
</main>

<footer class="site">
    <div class="wrap">© {{ now()->year }} {{ $siteName }}</div>
</footer>
</body>
</html>
