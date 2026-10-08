@extends('core::admin.layout')

@section('title', 'Editar usuário')

@section('content')
    <h2 class="mb-4 text-xl font-semibold">Editar {{ $user->name }}</h2>

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.usuarios.update', $user) }}">
            @csrf
            @method('PUT')
            @include('auth::users.form', ['user' => $user, 'isSelf' => $isSelf])
        </form>
    </div>
@endsection
