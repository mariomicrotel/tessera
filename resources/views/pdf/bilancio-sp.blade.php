<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Stato Patrimoniale {{ $anno }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #222; line-height: 1.45; }

        .cover { background: #1e3a5f; color: #fff; padding: 26px 36px 20px; margin-bottom: 18px; }
        .cover .org { font-size: 13pt; font-weight: bold; }
        .cover .title { font-size: 18pt; font-weight: bold; margin: 6px 0 3px; }
        .cover .sub   { font-size: 10pt; opacity: .85; }
        .cover .meta  { font-size: 7.5pt; opacity: .7; margin-top: 8px; }

        .section { margin-bottom: 16px; page-break-inside: avoid; }
        .section-header { background: #1e3a5f; color: #fff; padding: 5px 10px;
                          font-size: 9.5pt; font-weight: bold; margin-bottom: 0; }

        table.bilancio { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        table.bilancio th { background: #e8edf5; padding: 4px 8px; text-align: left;
                            font-weight: bold; border-bottom: 1px solid #b0bfd0; }
        table.bilancio th.r { text-align: right; }
        table.bilancio td { padding: 3px 8px; border-bottom: 1px solid #f0f0f0; }
        table.bilancio td.r { text-align: right; font-family: monospace; }
        table.bilancio tr.mastro td { font-weight: bold; background: #f7f9fc; color: #1e3a5f; }
        table.bilancio tr.voce td { padding-left: 20px; color: #444; }
        table.bilancio tr.totale td { font-weight: bold; border-top: 2px solid #1e3a5f;
                                      border-bottom: none; background: #f0f4fa; }

        .footer { margin-top: 16px; font-size: 7pt; color: #999;
                  border-top: 1px solid #e5e7eb; padding-top: 5px; }

        .two-col { display: table; width: 100%; border-collapse: collapse; }
        .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 8px; }
        .col:last-child { padding-right: 0; padding-left: 8px; }
    </style>
</head>
<body>

<div class="cover">
    <div class="org">{{ $tenant->name }}</div>
    <div class="title">STATO PATRIMONIALE</div>
    <div class="sub">Esercizio {{ $anno }} — comparato con {{ $sp['anno_prec'] }}</div>
    <div class="meta">D.Lgs. 127/1991 — IV Direttiva CEE &nbsp;·&nbsp; Generato il {{ now()->format('d/m/Y H:i') }}</div>
</div>

<div class="two-col">
    <!-- ATTIVO -->
    <div class="col">
        <div class="section-header">ATTIVO</div>
        <table class="bilancio">
            <thead>
                <tr>
                    <th>Voce</th>
                    <th class="r">{{ $anno }}</th>
                    <th class="r">{{ $sp['anno_prec'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sp['attivo'] as $gruppo)
                <tr class="mastro">
                    <td>{{ $gruppo['mastro'] }} — {{ $gruppo['label'] }}</td>
                    <td class="r">{{ number_format($gruppo['saldo'], 2, ',', '.') }}</td>
                    <td class="r">{{ number_format($gruppo['saldo_prec'], 2, ',', '.') }}</td>
                </tr>
                @foreach($gruppo['voci'] as $voce)
                <tr class="voce">
                    <td>{{ $voce['codice'] }} {{ $voce['descrizione'] }}</td>
                    <td class="r">{{ number_format($voce['saldo'], 2, ',', '.') }}</td>
                    <td class="r">{{ number_format($voce['saldo_prec'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr class="totale">
                    <td>TOTALE ATTIVO</td>
                    <td class="r">{{ number_format($sp['totale_attivo'], 2, ',', '.') }}</td>
                    <td class="r">—</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- PASSIVO + PN -->
    <div class="col">
        <div class="section-header">PASSIVO + PATRIMONIO NETTO</div>
        <table class="bilancio">
            <thead>
                <tr>
                    <th>Voce</th>
                    <th class="r">{{ $anno }}</th>
                    <th class="r">{{ $sp['anno_prec'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sp['passivo'] as $gruppo)
                <tr class="mastro">
                    <td>{{ $gruppo['mastro'] }} — {{ $gruppo['label'] }}</td>
                    <td class="r">{{ number_format($gruppo['saldo'], 2, ',', '.') }}</td>
                    <td class="r">{{ number_format($gruppo['saldo_prec'], 2, ',', '.') }}</td>
                </tr>
                @foreach($gruppo['voci'] as $voce)
                <tr class="voce">
                    <td>{{ $voce['codice'] }} {{ $voce['descrizione'] }}</td>
                    <td class="r">{{ number_format($voce['saldo'], 2, ',', '.') }}</td>
                    <td class="r">{{ number_format($voce['saldo_prec'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr class="totale">
                    <td>TOTALE PASSIVO + PN</td>
                    <td class="r">{{ number_format($sp['totale_passivo'], 2, ',', '.') }}</td>
                    <td class="r">—</td>
                </tr>
                @if($sp['differenza'] != 0)
                <tr class="totale" style="color: #dc2626;">
                    <td>⚠ Differenza Attivo/Passivo</td>
                    <td class="r">{{ number_format($sp['differenza'], 2, ',', '.') }}</td>
                    <td class="r"></td>
                </tr>
                @endif
            </tfoot>
        </table>
    </div>
</div>

<div class="footer">
    Stato Patrimoniale — {{ $tenant->name }} — Esercizio {{ $anno }} — Elaborato il {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
