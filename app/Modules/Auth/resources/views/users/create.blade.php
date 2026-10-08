@extends('core::admin.layout')

@section('title', 'Novo usuário')

@section('content')
    <h2 class="mb-4 text-xl font-semibold">Novo usuário</h2>

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.usuarios.store') }}">
            @csrf
            @include('auth::users.form', ['user' => null, 'isSelf' => false])
        </form>
    </div>
@endsection
