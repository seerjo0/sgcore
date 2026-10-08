@extends('core::admin.layout')

@section('title', $page ? 'Editar página' : 'Nova página')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">{{ $page ? 'Editar “'.$page->title.'”' : 'Nova página' }}</h2>
        <a href="{{ route('admin.paginas.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Voltar para a lista</a>
    </div>

    <form method="POST" action="{{ $page ? route('admin.paginas.update', $page) : route('admin.paginas.store') }}">
        @csrf
        @if ($page)
            @method('PUT')
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4">
                        <label for="title" class="mb-1 block text-sm font-medium text-slate-700">Título</label>
                        <input type="text" id="title" name="title" required maxlength="255"
                               value="{{ old('title', $page?->title) }}"
                               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="slug" class="mb-1 block text-sm font-medium text-slate-700">Slug (URL)</label>
                        <div class="flex items-center rounded border border-slate-300 bg-slate-50 focus-within:border-primary-500">
                            <span class="pl-3 text-sm text-slate-400">/</span>
                            <input type="text" id="slug" name="slug" maxlength="255"
                                   value="{{ old('slug', $page?->slug) }}"
                                   placeholder="gerado automaticamente a partir do título"
                                   class="w-full rounded-r border-0 bg-transparent px-1 py-2 text-sm shadow-none focus:ring-0">
                        </div>
                        <p class="mt-1 text-xs text-slate-400">Deixe vazio para gerar do título. Slugs repetidos ganham sufixo numérico.</p>
                        @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <label for="body" class="mb-1 block text-sm font-medium text-slate-700">Conteúdo</label>
                    <textarea id="body" name="content[body]" rows="14"
                              class="w-full rounded border-slate-300 font-mono text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('content.body', $page?->body()) }}</textarea>
                    <p class="mt-1 text-xs text-slate-400">
                        Tags permitidas: p, br, strong, em, a, img, ul, ol, li, h2–h4, blockquote, hr, code, pre.
                        Scripts e estilos são removidos ao salvar.
                    </p>
                    @error('content.body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold">SEO</h3>

                    <div class="mb-4">
                        <label for="seo_title" class="mb-1 block text-sm font-medium text-slate-700">Título para buscadores</label>
                        <input type="text" id="seo_title" name="seo_title" maxlength="255"
                               value="{{ old('seo_title', $page?->seo_title) }}"
                               placeholder="{{ old('title', $page?->title) }}"
                               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        @error('seo_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="seo_description" class="mb-1 block text-sm font-medium text-slate-700">Descrição</label>
                        <textarea id="seo_description" name="seo_description" rows="3" maxlength="300"
                                  class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('seo_description', $page?->seo_description) }}</textarea>
                        <p class="mt-1 text-xs text-slate-400">Até 300 caracteres — aparece nos resultados do Google.</p>
                        @error('seo_description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <span class="mb-1 block text-sm font-medium text-slate-700">Imagem para redes sociais (Open Graph)</span>
                        <div class="flex items-center gap-3">
                            <img id="og-preview"
                                 src="{{ ($page?->seo_og_image && $page->ogImage) ? $page->ogImage->url() : '' }}"
                                 alt="" class="h-16 w-16 rounded border border-slate-200 object-cover {{ $page?->seo_og_image ? '' : 'hidden' }}">
                            <input type="hidden" id="seo_og_image" name="seo_og_image"
                                   value="{{ old('seo_og_image', $page?->seo_og_image) }}">
                            <button type="button" onclick="openMediaPicker()"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">
                                Escolher da biblioteca
                            </button>
                            <button type="button" onclick="clearOgImage()"
                                    class="text-sm text-slate-400 hover:text-red-600">Remover</button>
                        </div>
                        @error('seo_og_image') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold">Publicação</h3>

                    <div class="mb-4">
                        <span class="mb-2 block text-sm font-medium text-slate-700">Status</span>
                        <label class="mr-4 inline-flex items-center gap-2 text-sm">
                            <input type="radio" name="status" value="draft"
                                   @checked(old('status', $page?->status ?? 'draft') === 'draft')>
                            Rascunho
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" name="status" value="published"
                                   @checked(old('status', $page?->status) === 'published')>
                            Publicado
                        </label>
                        @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="published_at" class="mb-1 block text-sm font-medium text-slate-700">Publicar em</label>
                        <input type="datetime-local" id="published_at" name="published_at"
                               value="{{ old('published_at', $page?->published_at?->format('Y-m-d\TH:i')) }}"
                               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <p class="mt-1 text-xs text-slate-400">Opcional — ao publicar sem data, usa a hora atual.</p>
                        @error('published_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <input type="hidden" name="is_home" value="0">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_home" value="1"
                               @checked(old('is_home', $page?->is_home))>
                        Definir como página inicial
                    </label>
                    <p class="mt-1 text-xs text-slate-400">Só pode existir uma página inicial.</p>
                    @error('is_home') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex gap-3">
                        <button type="submit"
                                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                            {{ $page ? 'Salvar alterações' : 'Criar página' }}
                        </button>
                        <a href="{{ route('admin.paginas.index') }}"
                           class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @include('media::picker', [
        'mode' => 'single',
        'targetField' => '#seo_og_image',
        'targetPreview' => '#og-preview',
        'title' => 'Escolher imagem para redes sociais',
    ])

    <script>
        function clearOgImage() {
            document.getElementById('seo_og_image').value = '';
            var preview = document.getElementById('og-preview');
            preview.src = '';
            preview.classList.add('hidden');
        }
    </script>
@endsection
