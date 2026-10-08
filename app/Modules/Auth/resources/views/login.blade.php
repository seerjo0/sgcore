@php($adminPrefs = app(\App\Modules\Core\Services\AdminPreferences::class))
<!DOCTYPE html>
<html lang="pt-BR" data-admin-color="{{ $adminPrefs->color() }}" data-admin-mode="{{ $adminPrefs->mode() }}"
      @class(['dark' => $adminPrefs->isDark()])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · {{ app(\App\Modules\Core\Services\Settings::class)->get('site_name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-surface flex min-h-screen items-center justify-center bg-slate-900 px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold text-white">SG Core</h1>
            <p class="text-sm text-slate-300">Painel de controle</p>
        </div>

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4 rounded-xl bg-white p-8 shadow-xl">
            @csrf

            @error('email')
                <div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                    {{ $message }}
                </div>
            @enderror

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-slate-700">E-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Senha</label>
                <input type="password" id="password" name="password" required
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                Manter conectado
            </label>

            <button type="submit"
                    class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">
                Entrar
            </button>
        </form>
    </div>
</body>
</html>
