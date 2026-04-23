<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installazione Infotel Sistemi – {{ $title ?? 'Gestionale' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <h1 class="text-2xl font-semibold text-gray-800 text-center mb-6">Installazione Infotel Sistemi</h1>
        @if ($errors->any())
            <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
        <p class="mt-6 text-center text-xs text-gray-500">v{{ config('app.version', '0.0.0') }}</p>
    </div>
</body>
</html>
