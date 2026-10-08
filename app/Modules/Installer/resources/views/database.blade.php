@extends('installer::layout', ['step' => 2])

@section('title', 'Banco de dados')
@section('subtitle', 'Conexão com o MySQL/MariaDB')

@section('content')
    <h1 class="mb-1 text-lg font-semibold text-white">Banco de dados</h1>
    <p class="mb-4 text-sm text-zinc-400">Informe os dados de acesso. O banco será criado caso não exista.</p>

    @if ($failure)
        <p class="mb-4 rounded-lg border border-red-900 bg-red-950/50 px-3 py-2 text-sm text-red-300">
            {{ $failure }}
        </p>
    @endif

    <form method="POST" action="{{ route('installer.database.store') }}" class="space-y-4">
        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
                <label for="host" class="mb-1 block text-sm font-medium text-zinc-300">Host</label>
                <input type="text" id="host" name="host" value="{{ old('host', $values['host'] ?? '127.0.0.1') }}" required
                       class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                @if ($errors->has('host'))
                    <p class="mt-1 text-xs text-red-400">{{ $errors->first('host') }}</p>
                @endif
            </div>
            <div>
                <label for="port" class="mb-1 block text-sm font-medium text-zinc-300">Porta</label>
                <input type="number" id="port" name="port" value="{{ old('port', $values['port'] ?? 3306) }}" required
                       class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                @if ($errors->has('port'))
                    <p class="mt-1 text-xs text-red-400">{{ $errors->first('port') }}</p>
                @endif
            </div>
        </div>

        <div>
            <label for="database" class="mb-1 block text-sm font-medium text-zinc-300">Banco de dados</label>
            <input type="text" id="database" name="database" value="{{ old('database', $values['database'] ?? 'sgcore') }}" required
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
            @if ($errors->has('database'))
                <p class="mt-1 text-xs text-red-400">{{ $errors->first('database') }}</p>
            @endif
        </div>

        <div>
            <label for="username" class="mb-1 block text-sm font-medium text-zinc-300">Usuário</label>
            <input type="text" id="username" name="username" value="{{ old('username', $values['username'] ?? 'root') }}" required
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
            @if ($errors->has('username'))
                <p class="mt-1 text-xs text-red-400">{{ $errors->first('username') }}</p>
            @endif
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-zinc-300">Senha</label>
            <input type="password" id="password" name="password" value=""
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none"
                   placeholder="Deixe em branco se não houver">
            @if ($errors->has('password'))
                <p class="mt-1 text-xs text-red-400">{{ $errors->first('password') }}</p>
            @endif
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('installer.requirements') }}" class="text-sm text-zinc-400 hover:text-zinc-200">← Voltar</a>
            <button type="submit"
                    class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500">
                Testar e continuar →
            </button>
        </div>
    </form>
@endsection
