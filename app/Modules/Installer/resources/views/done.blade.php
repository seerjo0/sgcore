@extends('installer::layout', ['lang' => $lang])

@section('title', __('installer.done_page_title'))
@section('subtitle', __('installer.done_subtitle'))

@section('content')
    <div class="text-center">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600/20 text-2xl text-emerald-400">✓</div>

        <h1 class="text-lg font-semibold text-white">{{ __('installer.done_installed', ['name' => $siteName]) }}</h1>
        <p class="mt-1 text-sm text-zinc-400">{{ __('installer.done_help') }}</p>

        <div class="mt-6 grid gap-3">
            <a href="{{ url('/') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500">
                {{ __('installer.view_site') }} →
            </a>
            <a href="{{ url('/'.admin_path()) }}"
               class="rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-zinc-200 transition hover:border-zinc-500">
                {{ __('installer.admin_panel') }} →
            </a>
        </div>
    </div>
@endsection