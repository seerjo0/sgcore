@php($isSelf = $isSelf ?? false)

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">{{ __('users.name') }}</label>
        <input type="text" id="name" name="name" required maxlength="255"
               value="{{ old('name', $user?->name) }}"
               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">{{ __('users.email') }}</label>
        <input type="email" id="email" name="email" required maxlength="255"
               value="{{ old('email', $user?->email) }}"
               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    @unless ($isSelf)
        <div>
            <label for="role" class="mb-1 block text-sm font-medium text-slate-700">{{ __('users.role') }}</label>
            <select id="role" name="role" required
                    class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">{{ __('users.role_placeholder') }}</option>
                @foreach (\App\Modules\Auth\Enums\Role::cases() as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user?->role?->value) === $role->value)>
                        {{ $role->label() }}
                    </option>
                @endforeach
            </select>
            @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-400">{{ __('users.role_help') }}</p>
        </div>

        <div class="flex items-end pb-2">
            <input type="hidden" name="active" value="0">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="active" value="1"
                       @checked(old('active', $user?->active ?? true))
                       class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                {{ __('users.active_label') }}
            </label>
        </div>
        @error('active') <p class="col-span-full -mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
    @else
        <div class="sm:col-span-2 rounded border border-primary-100 bg-primary-50 px-3 py-2 text-xs text-primary-700">
            {{ __('users.self_notice') }}
        </div>
    @endunless

    <div class="sm:col-span-2 border-t border-slate-100 pt-4">
        <label for="password" class="mb-1 block text-sm font-medium text-slate-700">
            {{ __('users.password') }}{{ $user ? ' '. __('users.password_keep') : '' }}
        </label>
        <input type="password" id="password" name="password" {{ $user ? '' : 'required' }} autocomplete="new-password"
               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

        <label for="password_confirmation" class="mb-1 mt-3 block text-sm font-medium text-slate-700">{{ __('users.password_confirmation') }}</label>
        <input type="password" id="password_confirmation" name="password_confirmation" {{ $user ? '' : 'required' }} autocomplete="new-password"
               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
        {{ $user ? __('common.save_changes') : __('users.new') }}
    </button>
    <a href="{{ route('admin.usuarios.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('common.cancel') }}</a>
</div>
