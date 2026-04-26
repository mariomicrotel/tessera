<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modello F24 — {{ $modello->periodoLabel() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #111; }

        .page-header { background: #1e3a5f; color: #fff; padding: 10px 14px; margin-bottom: 12px; }
        .page-header h1 { font-size: 13pt; font-weight: bold; letter-spacing: 1px; }
        .page-header p  { font-size: 8pt; margin-top: 3px; opacity: 0.85; }

        .meta-grid { display: table; width: 100%; margin-bottom: 10px; }
        .meta-cell { display: table-cell; width: 25%; padding: 4px 8px; vertical-align: top; }
        .meta-label { font-size: 7pt; color: #666; text-transform: uppercase; }
        .meta-value { font-size: 9pt; font-weight: bold; }

        .stato-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 7.5pt; font-weight: bold; }
        .stato-bozza     { background: #fef9c3; color: #854d0e; }
        .stato-compilato { background: #dbeafe; color: #1e40af; }
        .stato-versato   { background: #dcfce7; color: #166534; }

        .section-title { background: #1e3a5f; color: #fff; padding: 4px 8px;
                         font-size: 8pt; font-weight: bold; text-transform: uppercase;
                         letter-spacing: 0.5px; margin-top: 8px; margin-bottom: 0; }

        table.righe { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.righe th { background: #e8edf3; font-size: 7.5pt; text-transform: uppercase;
                         padding: 3px 6px; border-bottom: 1px solid #bfc8d4; }
        table.righe td { padding: 3px 6px; border-bottom: 1px solid #e5e7eb; font-size: 8.5pt; }
        table.righe tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        .font-mono  { font-family: 'Courier New', monospace; }
        .font-bold  { font-weight: bold; }

        .totals-box { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px 12px;
                      margin-top: 10px; display: table; width: 100%; }
        .totals-row { display: table-row; }
        .totals-label { display: table-cell; font-size: 8pt; color: #555; padding: 2px 0; width: 60%; }
        .totals-value { display: table-cell; font-size: 9pt; font-weight: bold;
                        text-align: right; font-family: monospace; padding: 2px 0; }
        .saldo-row .totals-label { color: #1e3a5f; font-weight: bold; font-size: 9pt; }
        .saldo-row .totals-value { font-size: 11pt; color: #1e3a5f; }

        .footer-note { margin-top: 14px; font-size: 7pt; color: #888; border-top: 1px solid #e5e7eb; padding-top: 6px; }
        .empty-section { color: #aaa; font-size: 8pt; padding: 5px 8px; font-style: italic; }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="page-header">
        <h1>MODELLO F24 — DELEGA DI PAGAMENTO</h1>
        <p>
            Periodo: {{ $modello->periodoLabel() }} &nbsp;|&nbsp;
            Compilato il: {{ $modello->data_compilazione->format('d/m/Y') }}
            @if($tenant->denominazione ?? $tenant->name)
                &nbsp;|&nbsp; {{ $tenant->denominazione ?? $tenant->name }}
            @endif
        </p>
    </div>

    <!-- Dati testata -->
    <div class="meta-grid">
        <div class="meta-cell">
            <div class="meta-label">Anno</div>
            <div class="meta-value">{{ $modello->anno }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Mese</div>
            <div class="meta-value">{{ $modello->mese ? str_pad($modello->mese, 2, '0', STR_PAD_LEFT) : '—' }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Data versamento</div>
            <div class="meta-value">{{ $modello->data_versamento ? $modello->data_versamento->format('d/m/Y') : '—' }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Stato</div>
            <div class="meta-value">
                <span class="stato-badge stato-{{ $modello->stato }}">
                    {{ \App\Models\ModelloF24::STATI[$modello->stato] ?? $modello->stato }}
                </span>
            </div>
        </div>
    </div>

    @foreach($sezioni as $codSezione => $labelSezione)
        @php $righe = $modello->righePerSezione($codSezione); @endphp
        @if($righe->isNotEmpty())
            <!-- Sezione {{ $labelSezione }} -->
            <div class="section-title">Sezione {{ $labelSezione }}</div>
            <table class="righe">
                <thead>
                    <tr>
                        <th>Codice tributo</th>
                        <th>Descrizione</th>
                        <th>Rateaz.</th>
                        <th>Anno rif.</th>
                        <th class="text-right">Importo debiti (€)</th>
                        <th class="text-right">Importo crediti (€)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($righe as $riga)
                        <tr>
                            <td class="font-mono font-bold">{{ $riga->codice_tributo }}</td>
                            <td>{{ $riga->descrizione ?? '—' }}</td>
                            <td class="font-mono">{{ $riga->rateazione ?? '—' }}</td>
                            <td class="text-right">{{ $riga->anno_riferimento ?? '—' }}</td>
                            <td class="text-right font-mono">
                                {{ $riga->importo_debito > 0 ? number_format($riga->importo_debito, 2, ',', '.') : '—' }}
                            </td>
                            <td class="text-right font-mono">
                                {{ $riga->importo_credito > 0 ? number_format($riga->importo_credito, 2, ',', '.') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    @if($modello->righe->isEmpty())
        <p class="empty-section">Nessuna riga inserita.</p>
    @endif

    <!-- Totali -->
    <div class="totals-box">
        <div class="totals-row">
            <div class="totals-label">Totale debiti</div>
            <div class="totals-value">€ {{ number_format($modello->totale_debiti, 2, ',', '.') }}</div>
        </div>
        <div class="totals-row">
            <div class="totals-label">Totale crediti</div>
            <div class="totals-value">€ {{ number_format($modello->totale_crediti, 2, ',', '.') }}</div>
        </div>
        <div class="totals-row saldo-row">
            <div class="totals-label">SALDO DA VERSARE</div>
            <div class="totals-value">€ {{ number_format(max(0, $modello->saldo), 2, ',', '.') }}</div>
        </div>
    </div>

    @if($modello->note)
        <div style="margin-top:8px; font-size:8pt; color:#555;">
            <strong>Note:</strong> {{ $modello->note }}
        </div>
    @endif

    <!-- Footer -->
    <div class="footer-note">
        Documento generato il {{ now()->format('d/m/Y H:i') }} —
        Modello F24 generato da Tessera (Network GTC). Non costituisce documento fiscale ufficiale.
    </div>

</body>
</html>
