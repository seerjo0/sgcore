@php($adminPrefs = app(\App\Modules\Core\Services\AdminPreferences::class))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-admin-color="{{ $adminPrefs->color() }}" data-admin-mode="{{ $adminPrefs->mode() }}"
      @class(['dark' => $adminPrefs->isDark()])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('nav.dashboard')) · {{ app(\App\Modules\Core\Services\Settings::class)->get('site_name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen">

    <div id="admin-sidebar-overlay" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden" aria-hidden="true"></div>

    <aside id="admin-sidebar" data-open="false"
           class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col overflow-y-auto bg-slate-900 text-slate-200 lg:static lg:z-auto">
        <div class="border-b border-slate-700 px-5 py-4">
            <a href="{{ route('admin.dashboard') }}" class="block text-lg font-semibold text-white hover:text-white">
                SG Core
            </a>
            <p class="truncate text-xs text-slate-400">{{ app(\App\Modules\Core\Services\Settings::class)->get('site_name', config('app.name')) }}</p>
        </div>

        <nav class="flex-1 space-y-1 px-3 py-4 text-sm">
            <a href="{{ route('admin.dashboard') }}"
               class="block rounded px-3 py-2 {{ request()->routeIs('admin.dashboard') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                {{ __('nav.dashboard') }}
            </a>

            @can('manage-pages')
                <a href="{{ route('admin.paginas.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.paginas.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    {{ __('nav.pages') }}
                </a>
                <a href="{{ route('admin.builder.templates.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.builder.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    {{ __('nav.builder_templates') }}
                </a>
            @endcan

            @can('manage-blog')
                <a href="{{ route('admin.posts.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.posts.*', 'admin.categorias.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    {{ __('nav.blog') }}
                </a>
            @endcan

            @can('manage-media')
                <a href="{{ route('admin.midia.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.midia.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    {{ __('nav.media') }}
                </a>
                <a href="{{ route('admin.galerias.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.galerias.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    {{ __('nav.galleries') }}
                </a>
            @endcan

            @can('manage-menus')
                <a href="{{ route('admin.menus.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.menus.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    {{ __('nav.menus') }}
                </a>
            @endcan

            @can('manage-users')
                <a href="{{ route('admin.usuarios.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.usuarios.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    {{ __('nav.users') }}
                </a>
            @endcan

            @can('manage-appearance')
                <a href="{{ route('admin.aparencia.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.aparencia.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    {{ __('nav.appearance') }}
                </a>
            @endcan

            @can('manage-modules')
                <a href="{{ route('admin.modulos.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.modulos.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    {{ __('nav.modules') }}
                </a>
            @endcan

            @can('manage-settings')
                <a href="{{ route('admin.configuracoes.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.configuracoes.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    {{ __('nav.settings') }}
                </a>
            @endcan
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-700 p-3">
            @csrf
            <button type="submit"
                    class="w-full rounded bg-slate-800 px-3 py-2 text-sm text-slate-200 hover:bg-slate-700">
                {{ __('nav.logout', ['name' => auth()->user()->name]) }}
            </button>
        </form>
    </aside>

    <div class="admin-surface flex min-w-0 flex-1 flex-col">
        <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" id="admin-sidebar-toggle"
                        class="-ml-2 rounded p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                        aria-controls="admin-sidebar" aria-expanded="false"
                        aria-label="{{ __('nav.open_menu') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="block h-6 w-6" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h1 class="truncate text-base font-semibold">@yield('title', __('nav.dashboard'))</h1>
            </div>
            <span class="text-sm text-slate-500">{{ auth()->user()->email }}</span>
        </header>

        <main class="flex-1 p-6">
            @if (session('status'))
                <div class="mb-4 rounded border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                    {{ session('status') }}
                </div>
            @endif

            @can('manage-settings')
                @if ($update && session('update_dismissed') !== ($update['tag'] ?? null))
                    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span>
                                <strong>{{ __('updater.banner_title') }}</strong>
                                {{ __('updater.banner_body', ['latest' => $update['tag'], 'current' => $updateCurrent]) }}
                            </span>
                            <span class="flex flex-wrap items-center gap-3">
                                @if (! empty($update['url']))
                                    <a href="{{ $update['url'] }}" target="_blank" rel="noopener noreferrer"
                                       class="font-semibold text-amber-900 underline">{{ __('updater.view_release') }}</a>
                                @endif
                                <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.verificar') }}" class="inline">
                                    @csrf
                                    <button type="submit" class="font-semibold text-amber-900 underline">{{ __('updater.check_again') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.configuracoes.atualizacoes.descartar') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="tag" value="{{ $update['tag'] }}">
                                    <button type="submit" class="text-amber-700 hover:text-amber-900">{{ __('updater.dismiss') }}</button>
                                </form>
                            </span>
                        </div>
                    </div>
                @endif
            @endcan

            @if (session('success'))
                <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
