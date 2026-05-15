<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Tessere soci — {{ $nomeOrganizzazione }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8pt;
            color: #1e293b;
            background: #fff;
        }

        /* Griglia: 2 colonne × 4 righe su A4 portrait */
        .grid {
            width: 100%;
        }
        .row {
            width: 100%;
            margin-bottom: 6mm;
        }
        .row::after { content: ''; display: table; clear: both; }

        /* Singola tessera — misure credito: 85.6 × 54 mm */
        .card {
            float: left;
            width: 88mm;
            height: 54mm;
            margin-right: 6mm;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
        }
        .card:last-child { margin-right: 0; }

        /* Striscia superiore colorata */
        .card-header {
            background: {{ $colore ?? '#1e40af' }};
            color: #fff;
            padding: 3mm 4mm 2.5mm;
            height: 14mm;
        }
        .card-header .org-name {
            font-size: 7.5pt;
            font-weight: bold;
            line-height: 1.2;
            max-height: 9mm;
            overflow: hidden;
        }
        .card-header .card-label {
            font-size: 5.5pt;
            opacity: .75;
            margin-top: 1mm;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* Corpo tessera */
        .card-body {
            padding: 2.5mm 4mm;
            height: 31mm;
        }
        .member-name {
            font-size: 10pt;
            font-weight: bold;
            color: #1e293b;
            line-height: 1.2;
            margin-bottom: 1.5mm;
            white-space: nowrap;
            overflow: hidden;
        }
        .member-type {
            font-size: 7pt;
            color: #64748b;
            margin-bottom: 2mm;
        }

        .info-row {
            width: 100%;
        }
        .info-row::after { content: ''; display: table; clear: both; }
        .info-box {
            float: left;
            width: 50%;
        }
        .info-label {
            font-size: 5.5pt;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .info-value {
            font-size: 9pt;
            font-weight: bold;
            color: #1e40af;
        }

        /* Fondo tessera */
        .card-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 1.5mm 4mm;
            height: 9mm;
        }
        .footer-row::after { content: ''; display: table; clear: both; }
        .footer-left {
            float: left;
        }
        .footer-right {
            float: right;
            text-align: right;
        }
        .footer-label {
            font-size: 5.5pt;
            color: #94a3b8;
            text-transform: uppercase;
        }
        .footer-value {
            font-size: 7.5pt;
            font-weight: bold;
            color: #475569;
        }

        /* Linea di taglio tra le righe */
        .cut-line {
            border-top: 1px dashed #cbd5e1;
            margin: 1mm 0 5mm 0;
        }
        .cut-label {
            text-align: center;
            font-size: 5pt;
            color: #cbd5e1;
            margin-top: -3.5mm;
            margin-bottom: 3mm;
        }
    </style>
</head>
<body>

<div class="grid">
@php
    $chunks = array_chunk($members, 2);
@endphp

@foreach($chunks as $rowIndex => $pair)

    @if($rowIndex > 0)
    <div class="cut-line"></div>
    <div class="cut-label">✂ ─────────────────────────────────────────────────────── ✂</div>
    @endif

    <div class="row">
        @foreach($pair as $m)
        <div class="card">
            <!-- Header -->
            <div class="card-header">
                <div class="org-name">{{ $nomeOrganizzazione }}</div>
                <div class="card-label">Tessera associativa</div>
            </div>

            <!-- Body -->
            <div class="card-body">
                <div class="member-name">
                    @if($m['ragione_sociale'])
                        {{ $m['ragione_sociale'] }}
                    @else
                        {{ $m['cognome'] }} {{ $m['nome'] }}
                    @endif
                </div>
                <div class="member-type">{{ $m['tipo'] ?? 'Socio' }}</div>

                <div class="info-row">
                    <div class="info-box">
                        <div class="info-label">N° Tessera</div>
                        <div class="info-value">{{ str_pad($m['numero_tessera'] ?? '—', 4, '0', STR_PAD_LEFT) }}</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Valida fino al</div>
                        <div class="info-value">31/12/{{ $anno }}</div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="card-footer">
                <div class="footer-row">
                    <div class="footer-left">
                        <div class="footer-label">Data iscrizione</div>
                        <div class="footer-value">{{ $m['data_iscrizione'] ? \Carbon\Carbon::parse($m['data_iscrizione'])->format('d/m/Y') : '—' }}</div>
                    </div>
                    <div class="footer-right">
                        <div class="footer-label">Codice fiscale</div>
                        <div class="footer-value">{{ $m['codice_fiscale'] ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
@endforeach
</div>

</body>
</html>
