@extends('install.layout')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <p class="text-gray-600 text-sm mb-4">Configura l'invio email (SMTP) per notifiche e recupero password. Puoi saltare questo step e usare il driver "log" (email salvate solo nei log).</p>
    <form method="POST" action="{{ route('install.smtp') }}" class="space-y-4">
        @csrf
        <div>
            <label for="mail_mailer" class="block text-sm font-medium text-gray-700 mb-1">Driver</label>
            <select name="mail_mailer" id="mail_mailer" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="smtp" {{ old('mail_mailer', 'smtp') === 'smtp' ? 'selected' : '' }}>SMTP</option>
                <option value="log" {{ old('mail_mailer') === 'log' ? 'selected' : '' }}>Log (nessun invio reale)</option>
            </select>
        </div>
        <div id="smtp-fields">
            <div>
                <label for="mail_host" class="block text-sm font-medium text-gray-700 mb-1">Host SMTP</label>
                <input type="text" name="mail_host" id="mail_host" value="{{ old('mail_host', '127.0.0.1') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="smtp.example.com">
            </div>
            <div>
                <label for="mail_port" class="block text-sm font-medium text-gray-700 mb-1">Porta</label>
                <input type="text" name="mail_port" id="mail_port" value="{{ old('mail_port', '587') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="587">
            </div>
            <div>
                <label for="mail_scheme" class="block text-sm font-medium text-gray-700 mb-1">Crittografia</label>
                <select name="mail_scheme" id="mail_scheme" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="" {{ old('mail_scheme', 'tls') === '' ? 'selected' : '' }}>Nessuna</option>
                    <option value="tls" {{ old('mail_scheme', 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ old('mail_scheme') === 'ssl' ? 'selected' : '' }}>SSL</option>
                </select>
            </div>
            <div>
                <label for="mail_username" class="block text-sm font-medium text-gray-700 mb-1">Utente</label>
                <input type="text" name="mail_username" id="mail_username" value="{{ old('mail_username') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="(opzionale)">
            </div>
            <div>
                <label for="mail_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="mail_password" id="mail_password" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="(opzionale)">
            </div>
            <div>
                <label for="mail_from_address" class="block text-sm font-medium text-gray-700 mb-1">Indirizzo mittente</label>
                <input type="text" name="mail_from_address" id="mail_from_address" value="{{ old('mail_from_address') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="noreply@example.com">
            </div>
            <div>
                <label for="mail_from_name" class="block text-sm font-medium text-gray-700 mb-1">Nome mittente</label>
                <input type="text" name="mail_from_name" id="mail_from_name" value="{{ old('mail_from_name', config('app.name')) }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ config('app.name') }}">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="skip_smtp_test" id="skip_smtp_test" value="1" {{ old('skip_smtp_test') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="skip_smtp_test" class="text-sm text-gray-600">Salva senza testare (utile se le credenziali vanno verificate dopo)</label>
            </div>
        </div>
        <div class="pt-2 flex gap-2">
            <a href="{{ route('install.database.form') }}" class="flex-1 py-2.5 px-4 rounded-lg font-medium text-center text-gray-700 bg-gray-100 hover:bg-gray-200">Indietro</a>
            <a href="{{ route('install.skip-smtp') }}" class="flex-1 py-2.5 px-4 rounded-lg font-medium text-center text-gray-600 border border-gray-300 hover:bg-gray-50">Salta</a>
            <button type="submit" class="flex-1 py-2.5 px-4 rounded-lg font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Avanti
            </button>
        </div>
    </form>
</div>
<script>
function toggleSmtpFields() {
    var mailer = document.getElementById('mail_mailer').value;
    var smtp = document.getElementById('smtp-fields');
    smtp.classList.toggle('hidden', mailer !== 'smtp');
}
document.getElementById('mail_mailer').addEventListener('change', toggleSmtpFields);
toggleSmtpFields();
</script>
@endsection
