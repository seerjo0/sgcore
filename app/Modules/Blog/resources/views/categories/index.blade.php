@extends('core::admin.layout')

@section('title', __('blog.categories'))

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">{{ __('blog.categories') }}</h2>
            <p class="text-sm text-slate-500">{{ __('blog.categories_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.posts.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('blog.back_to_posts') }}</a>
            <a href="{{ route('admin.categorias.create') }}"
               class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                {{ __('blog.new_category') }}
            </a>
        </div>
    </div>

    @if ($categories->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            {{ __('blog.categories_empty') }}
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('blog.category') }}</th>
                        <th class="px-4 py-3">{{ __('pages.slug') }}</th>
                        <th class="px-4 py-3">{{ __('blog.post_count') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($categories as $category)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $category->slug }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $category->posts_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.categorias.edit', $category) }}"
                                   class="font-medium text-primary-600 hover:text-primary-500">{{ __('common.edit') }}</a>
                                <form method="POST" action="{{ route('admin.categorias.destroy', $category) }}"
                                      class="ml-3 inline" onsubmit="return confirm('{{ __('blog.delete_confirm', ['title' => $category->name]) }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-500">{{ __('common.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
