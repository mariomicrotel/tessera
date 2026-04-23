@extends('install.layout')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <p class="text-gray-600 text-sm mb-4">Crea l'utente amministratore con cui accedere al gestionale.</p>
    <form method="POST" action="{{ route('install.complete') }}" class="space-y-4">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required autofocus>
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" id="password" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required minlength="8">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Conferma password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required minlength="8">
        </div>
        <div class="pt-2 flex gap-2">
            <a href="{{ route('install.back') }}" class="flex-1 py-2.5 px-4 rounded-lg font-medium text-center text-gray-700 bg-gray-100 hover:bg-gray-200">Indietro</a>
            <button type="submit" class="flex-1 py-2.5 px-4 rounded-lg font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Completa installazione
            </button>
        </div>
    </form>
</div>
@endsection
