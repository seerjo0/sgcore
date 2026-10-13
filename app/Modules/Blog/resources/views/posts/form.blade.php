@extends('core::admin.layout')

@section('title', $post ? __('blog.edit_title') : __('blog.new_post'))

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">
            {{ $post ? __('blog.edit_quoted', ['title' => $post->title]) : __('blog.new_post') }}
        </h2>
        <a href="{{ route('admin.posts.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('pages.back_to_list') }}</a>
    </div>

    <form method="POST" action="{{ $post ? route('admin.posts.update', $post) : route('admin.posts.store') }}">
        @csrf
        @if ($post)
            @method('PUT')
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4">
                        <label for="title" class="mb-1 block text-sm font-medium text-slate-700">{{ __('pages.title') }}</label>
                        <input type="text" id="title" name="title" required maxlength="255"
                               value="{{ old('title', $post?->title) }}"
                               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="slug" class="mb-1 block text-sm font-medium text-slate-700">{{ __('pages.slug_label') }}</label>
                        <div class="flex items-center rounded border border-slate-300 bg-slate-50 focus-within:border-primary-500">
                            <span class="pl-3 text-sm text-slate-400">/blog/</span>
                            <input type="text" id="slug" name="slug" maxlength="255"
                                   value="{{ old('slug', $post?->slug) }}"
                                   placeholder="{{ __('pages.slug_placeholder') }}"
                                   class="w-full rounded-r border-0 bg-transparent px-1 py-2 text-sm shadow-none focus:ring-0">
                        </div>
                        <p class="mt-1 text-xs text-slate-400">{{ __('blog.slug_help') }}</p>
                        @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <label for="excerpt" class="mb-1 block text-sm font-medium text-slate-700">{{ __('blog.excerpt') }}</label>
                    <textarea id="excerpt" name="excerpt" rows="2" maxlength="500"
                              class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('excerpt', $post?->excerpt) }}</textarea>
                    <p class="mt-1 text-xs text-slate-400">{{ __('blog.excerpt_help') }}</p>
                    @error('excerpt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <label for="body" class="mb-1 block text-sm font-medium text-slate-700">{{ __('pages.content') }}</label>
                    <textarea id="body" name="content[body]" rows="14"
                              class="w-full rounded border-slate-300 font-mono text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('content.body', $post?->body()) }}</textarea>
                    <p class="mt-1 text-xs text-slate-400">{{ __('pages.content_tags') }} {{ __('pages.content_note') }}</p>
                    @error('content.body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="space-y-5">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold">{{ __('pages.publish') }}</h3>

                    <div class="mb-4">
                        <span class="mb-2 block text-sm font-medium text-slate-700">{{ __('pages.status') }}</span>
                        <label class="mr-4 inline-flex items-center gap-2 text-sm">
                            <input type="radio" name="status" value="draft"
                                   @checked(old('status', $post?->status ?? 'draft') === 'draft')>
                            {{ __('pages.draft') }}
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" name="status" value="published"
                                   @checked(old('status', $post?->status) === 'published')>
                            {{ __('pages.published') }}
                        </label>
                        @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="published_at" class="mb-1 block text-sm font-medium text-slate-700">{{ __('pages.publish_at') }}</label>
                        <input type="datetime-local" id="published_at" name="published_at"
                               value="{{ old('published_at', $post?->published_at?->format('Y-m-d\TH:i')) }}"
                               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        @error('published_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="category_id" class="mb-1 block text-sm font-medium text-slate-700">{{ __('blog.category') }}</label>
                        <select id="category_id" name="category_id"
                                class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">{{ __('blog.no_category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $post?->category_id) === $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">
                            <a href="{{ route('admin.categorias.create') }}" class="text-primary-600 hover:text-primary-500">{{ __('blog.new_category') }}</a>
                        </p>
                        @error('category_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold">{{ __('blog.cover') }}</h3>
                    <div class="flex items-center gap-3">
                        <img id="cover-preview"
                             src="{{ ($post?->cover_image && $post->cover) ? $post->cover->url() : '' }}"
                             alt="" class="h-16 w-16 rounded border border-slate-200 object-cover {{ $post?->cover_image ? '' : 'hidden' }}">
                        <input type="hidden" id="cover_image" name="cover_image" value="{{ old('cover_image', $post?->cover_image) }}">
                        <button type="button" onclick="pickMedia('#cover_image', '#cover-preview')"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">{{ __('settings.choose_library') }}</button>
                        <button type="button" onclick="clearMedia('#cover_image', '#cover-preview')"
                                class="text-sm text-slate-400 hover:text-red-600">{{ __('common.remove') }}</button>
                    </div>
                    @error('cover_image') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-4 font-semibold">{{ __('pages.seo') }}</h3>
                    <div class="mb-4">
                        <label for="seo_title" class="mb-1 block text-sm font-medium text-slate-700">{{ __('pages.seo_title') }}</label>
                        <input type="text" id="seo_title" name="seo_title" maxlength="255"
                               value="{{ old('seo_title', $post?->seo_title) }}"
                               placeholder="{{ old('title', $post?->title) }}"
                               class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        @error('seo_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="mb-4">
                        <label for="seo_description" class="mb-1 block text-sm font-medium text-slate-700">{{ __('pages.seo_description') }}</label>
                        <textarea id="seo_description" name="seo_description" rows="3" maxlength="300"
                                  class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('seo_description', $post?->seo_description) }}</textarea>
                        @error('seo_description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('pages.og_image') }}</span>
                        <div class="flex items-center gap-3">
                            <img id="og-preview"
                                 src="{{ ($post?->seo_og_image && $post->ogImage) ? $post->ogImage->url() : '' }}"
                                 alt="" class="h-16 w-16 rounded border border-slate-200 object-cover {{ $post?->seo_og_image ? '' : 'hidden' }}">
                            <input type="hidden" id="seo_og_image" name="seo_og_image" value="{{ old('seo_og_image', $post?->seo_og_image) }}">
                            <button type="button" onclick="pickMedia('#seo_og_image', '#og-preview')"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">{{ __('settings.choose_library') }}</button>
                            <button type="button" onclick="clearMedia('#seo_og_image', '#og-preview')"
                                    class="text-sm text-slate-400 hover:text-red-600">{{ __('common.remove') }}</button>
                        </div>
                        @error('seo_og_image') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex gap-3">
                        <button type="submit"
                                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                            {{ $post ? __('common.save_changes') : __('blog.create') }}
                        </button>
                        <a href="{{ route('admin.posts.index') }}"
                           class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">{{ __('common.cancel') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @include('media::picker', [
        'mode' => 'single',
        'targetField' => '#cover_image',
        'targetPreview' => '#cover-preview',
        'title' => __('blog.choose_image'),
    ])

    <script>
        function pickMedia(target, preview) {
            const modal = document.getElementById('media-picker');
            modal.dataset.target = target;
            modal.dataset.preview = preview || '';
            openMediaPicker();
        }

        function clearMedia(target, preview) {
            document.querySelector(target).value = '';
            const el = document.querySelector(preview);
            el.src = '';
            el.classList.add('hidden');
        }
    </script>
@endsection
