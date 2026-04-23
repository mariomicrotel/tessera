<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Documento – {{ $document->titolo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; padding: 105px 24px 24px 24px; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .meta { font-size: 11px; color: #333; margin-top: 8px; }
        .content { margin-top: 20px; }
        .content p { margin: 0 0 8px 0; }
        .content ul, .content ol { margin: 8px 0; padding-left: 24px; }
        .content h1, .content h2, .content h3 { margin: 12px 0 6px 0; font-size: 13px; }
        .footer { margin-top: 28px; font-size: 9px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="letterhead-fixed">@include('pdf.letterhead', $letterhead ?? [])</div>
    <div class="header">
        <div class="title">{{ $document->titolo }}</div>
        @if($document->data)
            <div class="meta">{{ $document->data->format('d/m/Y') }}</div>
        @endif
    </div>

    <div class="content">
        @if(!empty($document->contenuto))
            {!! $document->contenuto !!}
        @else
            <p><em>Nessun contenuto.</em></p>
        @endif
    </div>

    <div class="footer">
        Documento generato il {{ now()->format('d/m/Y H:i') }}.
    </div>
    @include('pdf.footer-pagination')
</body>
</html>
