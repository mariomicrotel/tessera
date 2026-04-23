@extends('install.layout')

@php
    $p = $prefill ?? null;
    $conn = old('db_connection', $p ? ($p['connection'] ?? 'mysql') : 'mysql');
    $host = old('db_host', $p ? ($p['host'] ?? '127.0.0.1') : '127.0.0.1');
    $port = old('db_port', $p ? ($p['port'] ?? '3306') : '3306');
    $dbName = old('db_database', $p ? ($p['database'] ?? '') : '');
    $username = old('db_username', $p ? ($p['username'] ?? '') : '');
    $sqlitePath = ($p && ($p['connection'] ?? '') === 'sqlite') ? ($p['database'] ?? '') : '';
@endphp
@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <p class="text-gray-600 text-sm mb-4">Configura la connessione al database. Puoi usare MySQL/MariaDB o SQLite.</p>
    <form method="POST" action="{{ route('install.database') }}" class="space-y-4">
        @csrf
        <div>
            <label for="db_connection" class="block text-sm font-medium text-gray-700 mb-1">Tipo database</label>
            <select name="db_connection" id="db_connection" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                <option value="mysql" {{ $conn === 'mysql' ? 'selected' : '' }}>MySQL / MariaDB</option>
                <option value="sqlite" {{ $conn === 'sqlite' ? 'selected' : '' }}>SQLite</option>
            </select>
        </div>
        <div id="mysql-fields">
            <div>
                <label for="db_host" class="block text-sm font-medium text-gray-700 mb-1">Host</label>
                <input type="text" name="db_host" id="db_host" value="{{ $host }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="127.0.0.1">
            </div>
            <div>
                <label for="db_port" class="block text-sm font-medium text-gray-700 mb-1">Porta</label>
                <input type="text" name="db_port" id="db_port" value="{{ $port }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="3306">
            </div>
            <div>
                <label for="db_database" class="block text-sm font-medium text-gray-700 mb-1">Nome database</label>
                <input type="text" name="db_database" id="db_database" value="{{ $dbName }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="ets_db" required>
            </div>
            <div>
                <label for="db_username" class="block text-sm font-medium text-gray-700 mb-1">Utente</label>
                <input type="text" name="db_username" id="db_username" value="{{ $username }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="root">
            </div>
            <div>
                <label for="db_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="db_password" id="db_password" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="(opzionale)">
            </div>
        </div>
        <div id="sqlite-fields" class="hidden">
            <div>
                <label for="db_database_sqlite" class="block text-sm font-medium text-gray-700 mb-1">Percorso file database (lascia vuoto per predefinito)</label>
                <input type="text" name="db_database_sqlite" id="db_database_sqlite" value="{{ old('db_database_sqlite', $sqlitePath) }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ database_path('database.sqlite') }}">
            </div>
        </div>
        <div class="pt-2">
            <button type="submit" class="w-full py-2.5 px-4 rounded-lg font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Avanti
            </button>
        </div>
    </form>
</div>
<script>
function toggleDbFields() {
    var conn = document.getElementById('db_connection').value;
    var mysql = document.getElementById('mysql-fields');
    var sqlite = document.getElementById('sqlite-fields');
    var mysqlDb = document.getElementById('db_database');
    var sqliteDb = document.getElementById('db_database_sqlite');
    if (conn === 'sqlite') {
        mysql.classList.add('hidden');
        sqlite.classList.remove('hidden');
        mysqlDb.disabled = true;
        mysqlDb.removeAttribute('required');
        sqliteDb.disabled = false;
    } else {
        mysql.classList.remove('hidden');
        sqlite.classList.add('hidden');
        mysqlDb.disabled = false;
        mysqlDb.setAttribute('required', 'required');
        sqliteDb.disabled = true;
    }
}
document.getElementById('db_connection').addEventListener('change', toggleDbFields);
toggleDbFields();
</script>
@endsection
