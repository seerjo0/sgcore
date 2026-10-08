@php($isSelf = $isSelf ?? false)

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Nome</label>
        <input type="text" id="name" name="name" required maxlength="255"
               value="{{ old('name', $user?->name) }}"
               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
        <input type="email" id="email" name="email" required maxlength="255"
               value="{{ old('email', $user?->email) }}"
               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    @unless ($isSelf)
        <div>
            <label for="role" class="mb-1 block text-sm font-medium text-slate-700">Papel</label>
            <select id="role" name="role" required
                    class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">Selecione…</option>
                @foreach (\App\Modules\Auth\Enums\Role::cases() as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user?->role?->value) === $role->value)>
                        {{ $role->label() }}
                    </option>
                @endforeach
            </select>
            @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-400">Administradores gerenciam tudo; editores cuidam do conteúdo.</p>
        </div>

        <div class="flex items-end pb-2">
            <input type="hidden" name="active" value="0">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="active" value="1"
                       @checked(old('active', $user?->active ?? true))
                       class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                Conta ativa (pode entrar no painel)
            </label>
        </div>
        @error('active') <p class="col-span-full -mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
    @else
        <div class="sm:col-span-2 rounded border border-primary-100 bg-primary-50 px-3 py-2 text-xs text-primary-700">
            Seu próprio papel e situação não podem ser alterados aqui.
        </div>
    @endunless

    <div class="sm:col-span-2 border-t border-slate-100 pt-4">
        <label for="password" class="mb-1 block text-sm font-medium text-slate-700">
            Senha {{ $user ? '(deixe em branco para manter)' : '' }}
        </label>
        <input type="password" id="password" name="password" {{ $user ? '' : 'required' }} autocomplete="new-password"
               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

        <label for="password_confirmation" class="mb-1 mt-3 block text-sm font-medium text-slate-700">Confirmar senha</label>
        <input type="password" id="password_confirmation" name="password_confirmation" {{ $user ? '' : 'required' }} autocomplete="new-password"
               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
        {{ $user ? 'Salvar alterações' : 'Criar usuário' }}
    </button>
    <a href="{{ route('admin.usuarios.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
</div>
