@php($adminPrefs = app(\App\Modules\Core\Services\AdminPreferences::class))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-admin-color="{{ $adminPrefs->color() }}" data-admin-mode="{{ $adminPrefs->mode() }}"
      @class(['dark' => $adminPrefs->isDark()])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('login.reset_title') }} · {{ app(\App\Modules\Core\Services\Settings::class)->get('site_name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-surface flex min-h-screen items-center justify-center bg-slate-900 px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold text-white">SG Core</h1>
            <p class="text-sm text-slate-300">{{ __('login.reset_title') }}</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4 rounded-xl bg-white p-8 shadow-xl">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            @error('email')
                <div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                    {{ is_array($message) ? implode(' ', $message) : $message }}
                </div>
            @enderror

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-slate-700">{{ __('login.email') }}</label>
                <input type="email" id="email" name="email" value="{{ $email }}" required readonly
                       class="w-full rounded border-slate-200 bg-slate-50 text-slate-500 shadow-sm">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">{{ __('login.new_password') }}</label>
                <input type="password" id="password" name="password" required autofocus minlength="8"
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-slate-700">{{ __('login.confirm_password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8"
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">
                {{ __('login.reset_submit') }}
            </button>
        </form>
    </div>
</body>
</html>
