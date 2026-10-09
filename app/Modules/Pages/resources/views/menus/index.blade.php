@extends('core::admin.layout')

@section('title', __('nav.menus'))

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold">{{ __('nav.menus') }}</h2>
            <p class="text-sm text-slate-500">{{ __('menus.index_subtitle') }}</p>
        </div>
        <a href="{{ route('admin.menus.create') }}"
           class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
            {{ __('menus.new') }}
        </a>
    </div>

    @if ($menus->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
            {{ __('menus.empty') }}
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('menus.title') }}</th>
                        <th class="px-4 py-3">{{ __('menus.location') }}</th>
                        <th class="px-4 py-3">{{ __('menus.items') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($menus as $menu)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $menu->title }}</td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ __("menus.location_{$menu->location}") }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $menu->items_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.menus.edit', $menu) }}"
                                   class="font-medium text-primary-600 hover:text-primary-500">{{ __('common.edit') }}</a>
                                <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}"
                                      class="ml-3 inline" onsubmit="return confirm('{{ __('menus.delete_confirm', ['title' => $menu->title]) }}')">
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
            {{ $menus->links() }}
        </div>
    @endif
@endsection
