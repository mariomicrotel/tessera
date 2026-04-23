<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Anteprima carta intestata</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; padding: 105px 24px 24px 24px; line-height: 1.4; }
        .preview-note { margin-top: 24px; font-size: 10px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="letterhead-fixed">@include('pdf.letterhead', $letterheadData)</div>

    <p class="preview-note">Anteprima carta intestata – Questa intestazione verrà usata negli header dei PDF.</p>
</body>
</html>
