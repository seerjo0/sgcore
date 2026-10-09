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
            <p class="text-sm text-slate-500">{{ __('dashboard.your_role') }}</p>
            <p class="mt-1 text-3xl font-semibold">{{ auth()->user()->role->label() }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ __('dashboard.status') }}</p>
            <p class="mt-1 text-3xl font-semibold text-emerald-600">{{ __('dashboard.installed') }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="font-semibold">{{ __('dashboard.upcoming') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('dashboard.upcoming_note') }}</p>
        <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <li class="rounded bg-slate-50 px-3 py-2">{{ __('dashboard.slots') }}</li>
            <li class="rounded bg-slate-50 px-3 py-2">{{ __('dashboard.seo_settings') }}</li>
        </ul>
    </div>
@endsection
