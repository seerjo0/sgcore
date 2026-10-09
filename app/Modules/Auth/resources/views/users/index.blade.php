@extends('core::admin.layout')

@section('title', __('nav.users'))

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold">{{ __('nav.users') }}</h2>
            <p class="text-sm text-slate-500">{{ __('users.index_subtitle') }}</p>
        </div>
        <a href="{{ route('admin.usuarios.create') }}"
           class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
            {{ __('users.new') }}
        </a>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('users.name') }}</th>
                    <th class="px-4 py-3">{{ __('users.email') }}</th>
                    <th class="px-4 py-3">{{ __('users.role') }}</th>
                    <th class="px-4 py-3">{{ __('users.status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    @php($isSelf = $user->is(auth()->user()))
                    <tr class="{{ $isSelf ? 'bg-primary-50/40' : '' }}">
                        <td class="px-4 py-3 font-medium">
                            {{ $user->name }}
                            @if ($isSelf)
                                <span class="ml-1 rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-primary-700">{{ __('users.you') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->email }}</td>
                        <td class="px-4 py-3">{{ $user->role->label() }}</td>
                        <td class="px-4 py-3">
                            @if ($user->active)
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('common.active') }}</span>
                            @else
                                <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.usuarios.edit', $user) }}"
                               class="font-medium text-primary-600 hover:text-primary-500">{{ __('common.edit') }}</a>

                            @unless ($isSelf)
                                <form method="POST" action="{{ route('admin.usuarios.destroy', $user) }}"
                                      class="ml-3 inline" onsubmit="return confirm('{{ __('users.delete_confirm', ['name' => $user->name]) }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-500">{{ __('common.delete') }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('users.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection
