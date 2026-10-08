@extends('core::admin.layout')

@section('title', 'Aparência')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Aparência</h2>
            <p class="text-sm text-slate-500">Temas do site. O tema ativo renderiza as páginas públicas.</p>
        </div>
        <a href="{{ route('admin.aparencia.conteudo') }}"
           class="rounded-lg border border-primary-600 px-4 py-2 text-sm font-semibold text-primary-600 hover:bg-primary-50">
            Editar conteúdo do tema
        </a>
    </div>

    <form method="POST" action="{{ route('admin.aparencia.store') }}" enctype="multipart/form-data"
          class="mb-6 flex flex-wrap items-end gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        @csrf
        <div class="min-w-64">
            <label for="file" class="block text-sm font-medium text-slate-700">Importar tema (.zip)</label>
            <input id="file" type="file" name="file" accept=".zip,application/zip" required
                   class="mt-1 block w-full text-sm text-slate-600">
            <p class="mt-1 text-xs text-slate-500">
                O ZIP deve conter um <code>theme.json</code> válido (na raiz ou em uma única pasta).
                Máximo {{ $maxUploadMb }} MB.
            </p>
        </div>
        <button type="submit"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
            Importar
        </button>
    </form>

    @if ($themes->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            Nenhum tema encontrado. Verifique <code>resources/themes</code> e <code>storage/app/themes</code>.
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($themes as $theme)
                @php
                    $isActive = $theme->slug === $active;
                    $screenshot = $theme->manifest['screenshot'] ?? 'screenshot.png';
                    $screenshotUrl = $theme->screenshotPath() !== null
                        ? route('theme.assets', ['theme' => $theme->slug, 'file' => ltrim($screenshot, '/')])
                        : null;
                @endphp
                <div class="flex flex-col overflow-hidden rounded-lg border {{ $isActive ? 'border-primary-400 ring-1 ring-primary-300' : 'border-slate-200' }} bg-white shadow-sm">
                    <div class="flex aspect-video items-center justify-center overflow-hidden bg-slate-100">
                        @if ($screenshotUrl !== null)
                            <img src="{{ $screenshotUrl }}" alt="Prévia do tema {{ $theme->name() }}"
                                 class="h-full w-full object-cover" loading="lazy">
                        @else
                            <span class="text-4xl font-bold text-slate-300">{{ strtoupper(substr($theme->slug, 0, 2)) }}</span>
                        @endif
                    </div>

                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-800">{{ $theme->name() }}</h3>
                            @if ($isActive)
                                <span class="rounded bg-primary-100 px-1.5 py-0.5 text-xs font-semibold text-primary-700">Ativo</span>
                            @endif
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600">
                                {{ $theme->builtin ? 'Embutido' : 'Importado' }}
                            </span>
                            @if ($theme->version() !== '')
                                <span class="text-xs text-slate-400">v{{ $theme->version() }}</span>
                            @endif
                        </div>

                        @if ($theme->description() !== '')
                            <p class="text-xs text-slate-500">{{ $theme->description() }}</p>
                        @endif

                        <p class="text-xs text-slate-400">
                            Slug: <code>{{ $theme->slug }}</code>
                            @if ($theme->slots() !== [])
                                · {{ count($theme->slots()) }} slot(s)
                            @endif
                        </p>

                        @if (! $theme->valid && $theme->error !== null)
                            <p class="rounded border border-red-200 bg-red-50 px-2 py-1.5 text-xs text-red-700">
                                {{ $theme->error }}
                            </p>
                        @endif

                        <div class="mt-auto pt-2">
                            @if ($isActive)
                                <span class="inline-block rounded-lg bg-slate-100 px-4 py-2 text-center text-sm font-semibold text-slate-400 w-full">
                                    Tema em uso
                                </span>
                            @elseif (! $theme->valid)
                                <span class="inline-block rounded-lg bg-slate-100 px-4 py-2 text-center text-sm font-semibold text-slate-400 w-full"
                                      title="Corrija o manifest para ativar este tema.">
                                    Indisponível
                                </span>
                            @else
                                <form method="POST" action="{{ route('admin.aparencia.ativar') }}">
                                    @csrf
                                    <input type="hidden" name="slug" value="{{ $theme->slug }}">
                                    <button type="submit"
                                            class="w-full rounded-lg border border-primary-600 px-4 py-2 text-sm font-semibold text-primary-600 hover:bg-primary-50">
                                        Ativar este tema
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
