@extends('core::admin.layout')

@section('title', __('nav.dashboard'))

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-semibold">{{ __('dashboard.welcome', ['name' => auth()->user()->name]) }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('dashboard.overview') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('dashboard.users_registered') }}</p>
            <p class="mt-1 text-3xl font-semibold">{{ $userCount }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('dashboard.pages_total') }}</p>
            <p class="mt-1 text-3xl font-semibold">{{ $pageCount }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('dashboard.media_total') }}</p>
            <p class="mt-1 text-3xl font-semibold">{{ $mediaCount }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('dashboard.menus_total') }}</p>
            <p class="mt-1 text-3xl font-semibold">{{ $menuCount }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('dashboard.your_role') }}</p>
            <p class="mt-1 text-3xl font-semibold">{{ auth()->user()->role->label() }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('dashboard.status') }}</p>
            <p class="mt-1 text-3xl font-semibold text-emerald-600">{{ __('dashboard.installed') }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="font-semibold">{{ __('dashboard.quick_access') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('dashboard.quick_access_note') }}</p>
        <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @can('manage-pages')
                <li>
                    <a href="{{ route('admin.paginas.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('nav.pages') }}
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.builder.templates.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('nav.builder_templates') }}
                    </a>
                </li>
            @endcan

            @can('manage-blog')
                <li>
                    <a href="{{ route('admin.posts.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('nav.blog') }}
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.categorias.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('blog.categories') }}
                    </a>
                </li>
            @endcan

            @can('manage-media')
                <li>
                    <a href="{{ route('admin.midia.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('nav.media') }}
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.galerias.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('nav.galleries') }}
                    </a>
                </li>
            @endcan

            @can('manage-menus')
                <li>
                    <a href="{{ route('admin.menus.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('nav.menus') }}
                    </a>
                </li>
            @endcan

            @can('manage-appearance')
                <li>
                    <a href="{{ route('admin.aparencia.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('nav.appearance') }}
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.aparencia.conteudo') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('dashboard.slots') }}
                    </a>
                </li>
            @endcan

            @can('manage-settings')
                <li>
                    <a href="{{ route('admin.configuracoes.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('dashboard.seo_settings') }}
                    </a>
                </li>
            @endcan

            @can('manage-users')
                <li>
                    <a href="{{ route('admin.usuarios.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('nav.users') }}
                    </a>
                </li>
            @endcan

            @can('manage-modules')
                <li>
                    <a href="{{ route('admin.modulos.index') }}" class="block rounded bg-slate-50 px-3 py-2 hover:bg-slate-100">
                        {{ __('nav.modules') }}
                    </a>
                </li>
            @endcan
        </ul>
    </div>
@endsection
