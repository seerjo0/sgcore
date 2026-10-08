@extends('core::admin.layout')

@section('title', 'Painel')

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-semibold">Bem-vindo, {{ auth()->user()->name }}!</h2>
        <p class="mt-1 text-sm text-slate-500">Visão geral do seu site.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Usuários cadastrados</p>
            <p class="mt-1 text-3xl font-semibold">{{ $userCount }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Seu papel</p>
            <p class="mt-1 text-3xl font-semibold">{{ auth()->user()->role->label() }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Situação</p>
            <p class="mt-1 text-3xl font-semibold text-emerald-600">Instalado</p>
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="font-semibold">Próximas funcionalidades</h3>
        <p class="mt-1 text-sm text-slate-500">Estas seções serão liberadas nas próximas fases do desenvolvimento:</p>
        <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <li class="rounded bg-slate-50 px-3 py-2">Slots de conteúdo</li>
            <li class="rounded bg-slate-50 px-3 py-2">Configurações do site e SEO</li>
        </ul>
    </div>
@endsection
