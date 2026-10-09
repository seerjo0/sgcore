<!DOCTYPE html>
<html lang="{{ $lang ?? (str_replace('_', '-', app()->getLocale())) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('installer.title')) — sgcore</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-zinc-950 p-4 font-sans text-zinc-100">
    <div class="w-full max-w-xl">
        <div class="mb-6 text-center">
            <div class="text-2xl font-bold tracking-tight text-white">sgcore</div>
            <div class="mt-1 text-sm text-zinc-400">@yield('subtitle', __('installer.assistant_subtitle'))</div>
        </div>

        <div class="mb-6 flex items-center justify-center gap-2 text-xs">
            <span class="text-zinc-500">{{ __('installer.language') }}:</span>
            <a href="{{ url()->current() }}?lang=pt"
               class="rounded-full px-3 py-1 {{ ($lang ?? 'pt') === 'pt' ? 'bg-emerald-600 font-semibold text-white' : 'bg-zinc-900 text-zinc-300 hover:bg-zinc-800' }}">Português</a>
            <a href="{{ url()->current() }}?lang=en"
               class="rounded-full px-3 py-1 {{ ($lang ?? 'pt') === 'en' ? 'bg-emerald-600 font-semibold text-white' : 'bg-zinc-900 text-zinc-300 hover:bg-zinc-800' }}">English</a>
        </div>

        @isset($step)
            <ol class="mb-4 flex justify-center gap-2 text-xs">
                @foreach ([__('installer.steps_requirements'), __('installer.steps_database'), __('installer.steps_site')] as $index => $label)
                    @php ($number = $index + 1)
                    <li class="flex items-center gap-1.5 rounded-full px-3 py-1 {{ $step === $number ? 'bg-emerald-600 text-white' : ($step > $number ? 'bg-zinc-800 text-zinc-300' : 'bg-zinc-900 text-zinc-500') }}">
                        <span class="font-semibold">{{ $number }}</span> {{ $label }}
                    </li>
                @endforeach
            </ol>
        @endisset

        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            @yield('content')
        </div>

        <p class="mt-4 text-center text-xs text-zinc-600">sgcore — CMS modular</p>
    </div>
</body>
</html>