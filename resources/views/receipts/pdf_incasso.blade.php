<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Ricevuta {{ $receipt->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; padding: 105px 20px 20px 20px; line-height: 1.35; }
        .letterhead-fixed { position: fixed; top: 0; left: 0; right: 0; height: 68px; z-index: 1; }
        .receipt-number-bar { text-align: right; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #ccc; font-size: 11px; }
        .receipt-number-bar strong { font-size: 12px; }
        .receipt-body { margin-top: 8px; }
        .receipt-body table { border-collapse: collapse; width: 100%; margin: 0.6em 0; font-size: inherit; }
        .receipt-body th, .receipt-body td { padding: 4px 8px; vertical-align: top; }
        .receipt-body th { font-weight: bold; text-align: left; }
        .footer { margin-top: 32px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="letterhead-fixed">@include('pdf.letterhead')</div>

    <div class="receipt-number-bar">
        <strong>Ricevuta n. {{ $receipt->number }}</strong>
    </div>

    <div class="receipt-body">
        @if(!empty($receipt_html))
            {!! $receipt_html !!}
        @else
            <p>Contenuto ricevuta non disponibile.</p>
        @endif
    </div>
</body>
</html>
