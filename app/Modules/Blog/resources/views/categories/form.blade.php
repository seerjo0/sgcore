@extends('core::admin.layout')

@section('title', $category ? __('blog.edit_category') : __('blog.new_category'))

@section('content')
    <div class="mb-4">
        <h2 class="text-xl font-semibold">{{ $category ? __('blog.edit_category') : __('blog.new_category') }}</h2>
        <a href="{{ route('admin.categorias.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('blog.back_to_categories') }}</a>
    </div>

    <form method="POST" action="{{ $category ? route('admin.categorias.update', $category) : route('admin.categorias.store') }}"
          class="max-w-xl space-y-5">
        @csrf
        @if ($category)
            @method('PUT')
        @endif

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4">
                <label for="name" class="mb-1 block text-sm font-medium text-slate-700">{{ __('blog.category_name') }}</label>
                <input type="text" id="name" name="name" required maxlength="100"
                       value="{{ old('name', $category?->name) }}"
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="slug" class="mb-1 block text-sm font-medium text-slate-700">{{ __('pages.slug_label') }}</label>
                <input type="text" id="slug" name="slug" maxlength="100"
                       value="{{ old('slug', $category?->slug) }}"
                       class="w-full rounded border-slate-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <p class="mt-1 text-xs text-slate-400">{{ __('blog.category_slug_help') }}</p>
                @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                {{ $category ? __('common.save_changes') : __('blog.create') }}
            </button>
            <a href="{{ route('admin.categorias.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
