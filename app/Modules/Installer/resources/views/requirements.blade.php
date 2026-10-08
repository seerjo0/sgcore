@extends('installer::layout', ['step' => 1])

@section('title', 'Requisitos')
@section('subtitle', 'Verificação do servidor')

@section('content')
    <h1 class="mb-4 text-lg font-semibold text-white">Requisitos do servidor</h1>

    <ul class="space-y-2 text-sm">
        @foreach ($checks as $check)
            <li class="flex items-start justify-between gap-3 rounded-lg border border-zinc-800 bg-zinc-950 px-3 py-2">
                <div>
                    <span class="{{ $check['ok'] ? 'text-zinc-200' : 'text-zinc-400' }}">{{ $check['label'] }}</span>
                    @if ($check['detail'])
                        <div class="mt-0.5 text-xs text-zinc-500">{{ $check['detail'] }}</div>
                    @endif
                </div>
                <span class="shrink-0 text-sm font-semibold {{ $check['ok'] ? 'text-emerald-400' : 'text-red-400' }}">
                    {{ $check['ok'] ? 'OK' : 'Falhou' }}
                </span>
            </li>
        @endforeach
    </ul>

    <div class="mt-6">
        @if ($ready)
            <a href="{{ route('installer.database') }}"
               class="inline-flex w-full justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500">
                Continuar →
            </a>
        @else
            <p class="rounded-lg border border-red-900 bg-red-950/50 px-3 py-2 text-sm text-red-300">
                Corrija os itens acima e atualize esta página.
            </p>
        @endif
    </div>
@endsection
