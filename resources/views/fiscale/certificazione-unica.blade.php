<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Certificazione Unica {{ $anno }} — {{ $sostituto['nome'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8pt; color: #1e293b; background: #fff; }
        .page { padding: 10mm; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .header { border: 2px solid #1e40af; padding: 4mm; margin-bottom: 4mm; }
        .header-title { font-size: 11pt; font-weight: bold; color: #1e40af; text-align: center; margin-bottom: 1mm; }
        .header-sub { font-size: 7.5pt; text-align: center; color: #475569; }
        .section { border: 1px solid #cbd5e1; margin-bottom: 3mm; }
        .section-title { background: #1e40af; color: #fff; font-size: 7.5pt; font-weight: bold;
                         padding: 1.5mm 3mm; letter-spacing: 0.3px; text-transform: uppercase; }
        .section-body { padding: 2mm 3mm; }
        .row { width: 100%; margin-bottom: 1.5mm; }
        .row::after { content: ''; display: table; clear: both; }
        .field { float: left; padding-right: 3mm; }
        .field-label { font-size: 5.5pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.3px; }
        .field-value { font-size: 8.5pt; font-weight: bold; color: #1e293b; border-bottom: 1px solid #e2e8f0; min-width: 20mm; }
        .field-value.euro::before { content: '€ '; color: #475569; font-size: 7pt; }
        .w-full { width: 100%; }
        .w-half { width: 50%; }
        .w-third { width: 33.3%; }
        .w-quarter { width: 25%; }
        .w-two-thirds { width: 66.7%; }
        .highlight { background: #fef9c3; }
        .footer { margin-top: 5mm; border-top: 1px dashed #cbd5e1; padding-top: 3mm; font-size: 6pt; color: #94a3b8; }
        .bold { font-weight: bold; }
        .text-right { text-align: right; }
        .note-box { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 2mm 3mm;
                    font-size: 6.5pt; color: #475569; margin-top: 3mm; }
    </style>
</head>
<body>

@foreach($percipienti as $p)
<div class="page">

    <div class="header">
        <div class="header-title">CERTIFICAZIONE UNICA {{ $anno }}</div>
        <div class="header-sub">
            Certificazione dei redditi — Lavoro autonomo, provvigioni e redditi diversi<br>
            Art. 4 D.P.R. 22 luglio 1998, n. 322
        </div>
    </div>

    {{-- SEZIONE A: Sostituto d'imposta --}}
    <div class="section">
        <div class="section-title">A — Dati del sostituto d'imposta (dichiarante)</div>
        <div class="section-body">
            <div class="row">
                <div class="field w-two-thirds">
                    <div class="field-label">Denominazione / Ragione sociale</div>
                    <div class="field-value">{{ $sostituto['nome'] }}</div>
                </div>
                <div class="field w-third">
                    <div class="field-label">Codice fiscale</div>
                    <div class="field-value">{{ $sostituto['codice_fiscale'] }}</div>
                </div>
            </div>
            <div class="row">
                <div class="field w-full">
                    <div class="field-label">Sede legale / Domicilio fiscale</div>
                    <div class="field-value">{{ $sostituto['indirizzo'] }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- SEZIONE B: Percipiente --}}
    <div class="section">
        <div class="section-title">B — Dati del percipiente</div>
        <div class="section-body">
            <div class="row">
                <div class="field w-two-thirds">
                    <div class="field-label">Cognome e Nome / Denominazione</div>
                    <div class="field-value">{{ $p['nome_percipiente'] }}</div>
                </div>
                <div class="field w-third">
                    <div class="field-label">Codice fiscale</div>
                    <div class="field-value">{{ $p['codice_fiscale'] }}</div>
                </div>
            </div>
            @if($p['partita_iva'])
            <div class="row">
                <div class="field w-third">
                    <div class="field-label">Partita IVA</div>
                    <div class="field-value">{{ $p['partita_iva'] }}</div>
                </div>
                <div class="field w-two-thirds">
                    <div class="field-label">Indirizzo</div>
                    <div class="field-value">{{ $p['indirizzo'] ?? '—' }}</div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- SEZIONE C: Redditi lavoro autonomo (Quadro LAV) --}}
    <div class="section">
        <div class="section-title">C — Redditi di lavoro autonomo, provvigioni e redditi diversi</div>
        <div class="section-body">
            <div class="row">
                <div class="field w-quarter">
                    <div class="field-label">Causale</div>
                    <div class="field-value">{{ $p['codice_causale'] }}</div>
                </div>
                <div class="field w-quarter">
                    <div class="field-label">Anno di riferimento</div>
                    <div class="field-value">{{ $anno }}</div>
                </div>
                <div class="field w-quarter">
                    <div class="field-label">Tipo rapporto</div>
                    <div class="field-value">{{ $p['tipo_rapporto_label'] }}</div>
                </div>
            </div>

            <div class="row" style="margin-top:2mm;">
                <div class="field w-quarter">
                    <div class="field-label" style="font-size:5pt;">Punto 4 — Ammontare lordo corrisposto</div>
                    <div class="field-value euro highlight">{{ number_format($p['compenso_lordo'], 2, ',', '.') }}</div>
                </div>
                <div class="field w-quarter">
                    <div class="field-label" style="font-size:5pt;">Punto 7 — Base imponibile ritenuta</div>
                    <div class="field-value euro">{{ number_format($p['base_imponibile_ritenuta'], 2, ',', '.') }}</div>
                </div>
                <div class="field w-quarter">
                    <div class="field-label" style="font-size:5pt;">Punto 8 — Aliquota (%)</div>
                    <div class="field-value">{{ number_format($p['aliquota_ritenuta'], 0) }}%</div>
                </div>
                <div class="field w-quarter">
                    <div class="field-label" style="font-size:5pt;">Punto 11 — Ritenute d'acconto operate</div>
                    <div class="field-value euro highlight">{{ number_format($p['totale_ritenuta'], 2, ',', '.') }}</div>
                </div>
            </div>

            @if($p['rimborsi_spese'] > 0)
            <div class="row" style="margin-top:1.5mm;">
                <div class="field w-quarter">
                    <div class="field-label" style="font-size:5pt;">Rimborsi spese esclusi da rit.</div>
                    <div class="field-value euro">{{ number_format($p['rimborsi_spese'], 2, ',', '.') }}</div>
                </div>
                <div class="field w-half">
                    <div class="field-label" style="font-size:5pt;">Netto erogato</div>
                    <div class="field-value euro bold">{{ number_format($p['netto_erogato'], 2, ',', '.') }}</div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- SEZIONE D: dettaglio prestazioni nell'anno --}}
    @if(count($p['prestazioni']) > 1)
    <div class="section">
        <div class="section-title">D — Dettaglio prestazioni nell'anno</div>
        <div class="section-body">
            <table style="width:100%; border-collapse:collapse; font-size:7pt;">
                <thead>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <th style="text-align:left; padding:1mm 2mm; color:#64748b; font-weight:normal; font-size:5.5pt;">Data</th>
                        <th style="text-align:left; padding:1mm 2mm; color:#64748b; font-weight:normal; font-size:5.5pt;">Descrizione</th>
                        <th style="text-align:right; padding:1mm 2mm; color:#64748b; font-weight:normal; font-size:5.5pt;">Lordo €</th>
                        <th style="text-align:right; padding:1mm 2mm; color:#64748b; font-weight:normal; font-size:5.5pt;">Ritenuta €</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($p['prestazioni'] as $pr)
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:1mm 2mm;">{{ $pr['data'] }}</td>
                        <td style="padding:1mm 2mm;">{{ Str::limit($pr['causale'], 60) }}</td>
                        <td style="text-align:right; padding:1mm 2mm;">{{ number_format($pr['lordo'], 2, ',', '.') }}</td>
                        <td style="text-align:right; padding:1mm 2mm;">{{ number_format($pr['ritenuta'], 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="note-box">
        <strong>NOTA PER IL PERCIPIENTE:</strong> La presente Certificazione è rilasciata ai sensi dell'art. 4 D.P.R. 322/1998.
        Deve essere conservata ed esibita all'Amministrazione Finanziaria su richiesta.
        La ritenuta indicata è stata operata nella misura prevista dalla normativa vigente al momento dell'erogazione.
        Il sostituto d'imposta ha provveduto o provvederà al versamento delle ritenute operate tramite Modello F24.
    </div>

    <div class="footer">
        <div class="row">
            <div class="field w-half">
                Luogo e data: {{ $sostituto['luogo'] ?? '' }}, {{ now()->format('d/m/Y') }}
            </div>
            <div class="field w-half text-right">
                Il Sostituto d'imposta: ___________________________<br>
                (timbro e firma)
            </div>
        </div>
    </div>

</div>
@endforeach

</body>
</html>
