@extends('installer::layout', ['step' => 3])

@section('title', 'Site e administrador')
@section('subtitle', 'Últimos dados para concluir')

@section('content')
    <h1 class="mb-1 text-lg font-semibold text-white">Site e administrador</h1>
    <p class="mb-4 text-sm text-zinc-400">Estas informações criam o site e o primeiro usuário com acesso total.</p>

    @if ($failure)
        <p class="mb-4 rounded-lg border border-red-900 bg-red-950/50 px-3 py-2 text-sm text-red-300">
            {{ $failure }}
        </p>
    @endif

    <form method="POST" action="{{ route('installer.site.store') }}" class="space-y-4">
        <div>
            <label for="site_name" class="mb-1 block text-sm font-medium text-zinc-300">Nome do site</label>
            <input type="text" id="site_name" name="site_name" value="{{ old('site_name', $values['site_name'] ?? '') }}" required maxlength="120"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
            @if ($errors->has('site_name'))
                <p class="mt-1 text-xs text-red-400">{{ $errors->first('site_name') }}</p>
            @endif
        </div>

        <div class="border-t border-zinc-800 pt-4">
            <p class="mb-3 text-sm font-medium text-zinc-300">Usuário administrador</p>

            <div class="space-y-4">
                <div>
                    <label for="admin_name" class="mb-1 block text-sm font-medium text-zinc-300">Nome</label>
                    <input type="text" id="admin_name" name="admin_name" value="{{ old('admin_name', $values['admin_name'] ?? '') }}" required maxlength="120"
                           class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                    @if ($errors->has('admin_name'))
                        <p class="mt-1 text-xs text-red-400">{{ $errors->first('admin_name') }}</p>
                    @endif
                </div>

                <div>
                    <label for="admin_email" class="mb-1 block text-sm font-medium text-zinc-300">E-mail</label>
                    <input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email', $values['admin_email'] ?? '') }}" required maxlength="190"
                           class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                    @if ($errors->has('admin_email'))
                        <p class="mt-1 text-xs text-red-400">{{ $errors->first('admin_email') }}</p>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="admin_password" class="mb-1 block text-sm font-medium text-zinc-300">Senha</label>
                        <input type="password" id="admin_password" name="admin_password" required minlength="8"
                               class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                        @if ($errors->has('admin_password'))
                            <p class="mt-1 text-xs text-red-400">{{ $errors->first('admin_password') }}</p>
                        @endif
                    </div>
                    <div>
                        <label for="admin_password_confirmation" class="mb-1 block text-sm font-medium text-zinc-300">Confirmar senha</label>
                        <input type="password" id="admin_password_confirmation" name="admin_password_confirmation" required minlength="8"
                               class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-zinc-800 pt-4">
            <p class="mb-3 text-sm font-medium text-zinc-300">Conteúdo</p>

            <input type="hidden" name="sample_content" value="0">
            <label class="flex items-start gap-3 rounded-lg border border-zinc-800 bg-zinc-950/50 px-3 py-3">
                <input type="checkbox" name="sample_content" value="1" class="mt-0.5"
                       @checked(($values['sample_content'] ?? old('sample_content')) === '1')>
                <span class="text-sm text-zinc-300">
                    <span class="font-medium text-white">Criar conteúdo de exemplo</span><br>
                    <span class="text-zinc-500">Páginas “Sobre” e “Contato” e um menu principal já com links.</span>
                </span>
            </label>
            @if ($errors->has('sample_content'))
                <p class="mt-1 text-xs text-red-400">{{ $errors->first('sample_content') }}</p>
            @endif
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('installer.database') }}" class="text-sm text-zinc-400 hover:text-zinc-200">← Voltar</a>
            <button type="submit"
                    class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500">
                Instalar →
            </button>
        </div>
    </form>
@endsection
