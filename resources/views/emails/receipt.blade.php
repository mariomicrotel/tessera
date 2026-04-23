<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ricevuta {{ $receipt->number }}</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <p>Buongiorno,</p>
    <p>In allegato trovi la ricevuta n. <strong>{{ $receipt->number }}</strong> emessa il {{ $receipt->issued_at?->format('d/m/Y') }}.</p>
    <p>Saluti,<br>{{ $appName }}</p>
</body>
</html>
