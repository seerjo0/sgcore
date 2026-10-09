@extends('core::admin.layout')

@section('title', __('nav.pages'))

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">{{ __('nav.pages') }}</h2>
            <p class="text-sm text-slate-500">{{ __('pages.index_subtitle') }}</p>
        </div>
        <a href="{{ route('admin.paginas.create') }}"
           class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
            {{ __('pages.new') }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.paginas.index') }}" class="mb-4 flex gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('pages.search_placeholder') }}"
               class="w-72 rounded border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
        <button type="submit" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">{{ __('pages.search') }}</button>
    </form>

    @if ($pages->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            {{ __('pages.empty') }}
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('pages.title') }}</th>
                        <th class="px-4 py-3">{{ __('pages.slug') }}</th>
                        <th class="px-4 py-3">{{ __('pages.status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($pages as $page)
                        <tr>
                            <td class="px-4 py-3 font-medium">
                                {{ $page->title }}
                                @if ($page->is_home)
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-700">{{ __('pages.home_badge') }}</span>
                                @endif
                                @if ($page->builder_published)
                                    <span class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-semibold text-indigo-700">{{ __('pagebuilder.built_badge') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">/{{ $page->slug }}</td>
                            <td class="px-4 py-3">
                                @if ($page->isPublished())
                                    <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('pages.published') }}</span>
                                @else
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-500">{{ __('pages.draft') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.paginas.builder.index', $page) }}"
                                   class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('pagebuilder.open_editor') }}</a>
                                <a href="{{ route('admin.paginas.edit', $page) }}"
                                   class="ml-3 font-medium text-primary-600 hover:text-primary-500">{{ __('common.edit') }}</a>
                                <form method="POST" action="{{ route('admin.paginas.destroy', $page) }}"
                                      class="ml-3 inline" onsubmit="return confirm('{{ __('pages.delete_confirm', ['title' => $page->title]) }}')">
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

        <div class="mt-4">
            {{ $pages->links() }}
        </div>
    @endif
@endsection
