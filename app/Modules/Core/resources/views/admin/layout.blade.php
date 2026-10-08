@php($adminPrefs = app(\App\Modules\Core\Services\AdminPreferences::class))
<!DOCTYPE html>
<html lang="pt-BR" data-admin-color="{{ $adminPrefs->color() }}" data-admin-mode="{{ $adminPrefs->mode() }}"
      @class(['dark' => $adminPrefs->isDark()])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel') · {{ app(\App\Modules\Core\Services\Settings::class)->get('site_name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen">

    <aside class="flex w-64 shrink-0 flex-col bg-slate-900 text-slate-200">
        <div class="border-b border-slate-700 px-5 py-4">
            <a href="{{ route('admin.dashboard') }}" class="block text-lg font-semibold text-white hover:text-white">
                SG Core
            </a>
            <p class="truncate text-xs text-slate-400">{{ app(\App\Modules\Core\Services\Settings::class)->get('site_name', config('app.name')) }}</p>
        </div>

        <nav class="flex-1 space-y-1 px-3 py-4 text-sm">
            <a href="{{ route('admin.dashboard') }}"
               class="block rounded px-3 py-2 {{ request()->routeIs('admin.dashboard') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                Painel
            </a>

            @can('manage-pages')
                <a href="{{ route('admin.paginas.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.paginas.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    Páginas
                </a>
            @endcan

            @can('manage-media')
                <a href="{{ route('admin.midia.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.midia.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    Mídia
                </a>
                <a href="{{ route('admin.galerias.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.galerias.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    Galerias
                </a>
            @endcan

            @can('manage-menus')
                <a href="{{ route('admin.menus.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.menus.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    Menus
                </a>
            @endcan

            @can('manage-users')
                <a href="{{ route('admin.usuarios.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.usuarios.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    Usuários
                </a>
            @endcan

            @can('manage-appearance')
                <a href="{{ route('admin.aparencia.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.aparencia.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    Aparência
                </a>
            @endcan

            @can('manage-settings')
                <a href="{{ route('admin.configuracoes.index') }}"
                   class="block rounded px-3 py-2 {{ request()->routeIs('admin.configuracoes.*') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                    Configurações
                </a>
            @endcan
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-700 p-3">
            @csrf
            <button type="submit"
                    class="w-full rounded bg-slate-800 px-3 py-2 text-sm text-slate-200 hover:bg-slate-700">
                Sair ({{ auth()->user()->name }})
            </button>
        </form>
    </aside>

    <div class="admin-surface flex min-w-0 flex-1 flex-col">
        <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3">
            <h1 class="text-base font-semibold">@yield('title', 'Painel')</h1>
            <span class="text-sm text-slate-500">{{ auth()->user()->email }}</span>
        </header>

        <main class="flex-1 p-6">
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
