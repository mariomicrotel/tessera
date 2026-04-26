<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Relazione di Missione {{ $relazione->anno }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #222; line-height: 1.55; }

        /* Cover-style header */
        .cover { background: #1e3a5f; color: #fff; padding: 30px 40px 25px; margin-bottom: 20px; }
        .cover .org-name { font-size: 15pt; font-weight: bold; letter-spacing: 0.5px; }
        .cover .doc-title { font-size: 20pt; font-weight: bold; margin: 8px 0 4px; letter-spacing: 1px; }
        .cover .anno     { font-size: 14pt; opacity: .85; }
        .cover .meta     { font-size: 8pt; opacity: .75; margin-top: 10px; }

        .stato-badge { display: inline-block; padding: 2px 10px; border-radius: 3px;
                       font-size: 8.5pt; font-weight: bold; margin-left: 8px; }
        .stato-bozza      { background: #fef9c3; color: #92400e; }
        .stato-definitiva { background: #dbeafe; color: #1e40af; }
        .stato-approvata  { background: #dcfce7; color: #166534; }

        /* Info box */
        .info-box { border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 14px;
                    margin-bottom: 16px; background: #f8fafc; font-size: 8.5pt; }
        .info-box table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 3px 8px; vertical-align: top; width: 25%; }
        .info-box .lbl { color: #6b7280; font-size: 7.5pt; text-transform: uppercase; }
        .info-box .val { font-weight: bold; }

        /* Variabili dati -->
        .var-grid { display: table; width: 100%; border: 1px solid #e2e8f0;
                    border-radius: 4px; padding: 8px 12px; margin-bottom: 16px;
                    background: #f0f9ff; }
        .var-row  { display: table-row; }
        .var-lbl  { display: table-cell; font-size: 8pt; color: #555; padding: 2px 4px; width: 55%; }
        .var-val  { display: table-cell; font-size: 8pt; font-weight: bold; text-align: right;
                    padding: 2px 4px; font-family: monospace; }

        /* Sezioni */
        .section { margin-bottom: 18px; page-break-inside: avoid; }
        .section-title { background: #1e3a5f; color: #fff; padding: 6px 12px;
                         font-size: 10.5pt; font-weight: bold; margin-bottom: 6px; }
        .section-body  { padding: 0 4px; font-size: 9.5pt; white-space: pre-wrap; }

        .signature-area { margin-top: 36px; display: table; width: 100%; }
        .sig-cell { display: table-cell; width: 50%; text-align: center; padding: 0 20px; }
        .sig-line { border-top: 1px solid #555; margin: 30px 20px 4px; }
        .sig-label { font-size: 8pt; color: #555; }

        .footer { margin-top: 20px; font-size: 7pt; color: #999;
                  border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>

    <!-- Cover header -->
    <div class="cover">
        <div class="org-name">{{ $variabili['nome_ente'] ?? ($tenant->name ?? '') }}</div>
        <div class="doc-title">
            RELAZIONE DI MISSIONE
            <span class="stato-badge stato-{{ $relazione->stato }}">
                {{ \App\Models\RelazioneMissione::STATI[$relazione->stato] ?? $relazione->stato }}
            </span>
        </div>
        <div class="anno">Esercizio {{ $relazione->anno }}</div>
        <div class="meta">
            Art. 13 D.Lgs. 117/2017 (Codice del Terzo Settore)
            @if($variabili['codice_fiscale_ente'] ?? false)
                &nbsp;·&nbsp; C.F. {{ $variabili['codice_fiscale_ente'] }}
            @endif
            @if($variabili['indirizzo_ente'] ?? false)
                &nbsp;·&nbsp; {{ $variabili['indirizzo_ente'] }}
            @endif
        </div>
    </div>

    <!-- Dati assemblea -->
    @if($relazione->data_approvazione || $relazione->organo_approvante)
    <div class="info-box">
        <table>
            <tr>
                <td>
                    <div class="lbl">Approvata da</div>
                    <div class="val">{{ $relazione->organo_approvante ?? '—' }}</div>
                </td>
                <td>
                    <div class="lbl">Data approvazione</div>
                    <div class="val">{{ $relazione->data_approvazione ? $relazione->data_approvazione->format('d/m/Y') : '—' }}</div>
                </td>
                <td>
                    <div class="lbl">Luogo</div>
                    <div class="val">{{ $relazione->luogo_approvazione ?? '—' }}</div>
                </td>
                <td>
                    <div class="lbl">Compilato il</div>
                    <div class="val">{{ $relazione->updated_at?->format('d/m/Y') ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>
    @endif

    <!-- Riquadro dati automatici -->
    <div class="var-grid">
        <div style="font-size:8pt; font-weight:bold; color:#1e3a5f; padding: 0 4px 6px;">
            Dati di sintesi — Anno {{ $relazione->anno }}
        </div>
        @foreach([
            ['Totale soci attivi', number_format($variabili['totale_soci'] ?? 0, 0, ',', '.')],
            ['Nuovi soci', number_format($variabili['nuovi_soci'] ?? 0, 0, ',', '.')],
            ['Soci cessati', number_format($variabili['soci_cessati'] ?? 0, 0, ',', '.')],
            ['Totale entrate', '€ '.number_format($variabili['totale_entrate'] ?? 0, 2, ',', '.')],
            ['Totale uscite', '€ '.number_format($variabili['totale_uscite'] ?? 0, 2, ',', '.')],
            ['Risultato esercizio', '€ '.number_format($variabili['risultato_esercizio'] ?? 0, 2, ',', '.')],
            ['Quote associative', '€ '.number_format($variabili['quote_associative'] ?? 0, 2, ',', '.')],
            ['Donazioni ricevute', '€ '.number_format($variabili['donazioni_ricevute'] ?? 0, 2, ',', '.')],
        ] as $row)
        <div class="var-row">
            <div class="var-lbl">{{ $row[0] }}</div>
            <div class="var-val">{{ $row[1] }}</div>
        </div>
        @endforeach
    </div>

    <!-- Sezioni della relazione -->
    @foreach($sezioniInterpolate as $sezione)
        @if(!empty(trim($sezione['testo'] ?? '')))
        <div class="section">
            <div class="section-title">{{ $sezione['titolo'] }}</div>
            <div class="section-body">{{ $sezione['testo'] }}</div>
        </div>
        @endif
    @endforeach

    <!-- Area firme -->
    <div class="signature-area">
        <div class="sig-cell">
            <div class="sig-line"></div>
            <div class="sig-label">Il Presidente</div>
        </div>
        <div class="sig-cell">
            <div class="sig-line"></div>
            <div class="sig-label">Il Segretario / Tesoriere</div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        Documento generato il {{ now()->format('d/m/Y H:i') }} — Tessera (Network GTC) —
        Art. 13 D.Lgs. 117/2017. La Relazione di Missione è parte integrante del bilancio dell'ente.
    </div>

</body>
</html>
