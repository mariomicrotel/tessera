<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registro Vendite {{ $from }} — {{ $to }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8pt; color: #222; line-height: 1.4; }

        .cover { background: #166534; color: #fff; padding: 20px 28px 16px; margin-bottom: 14px; }
        .cover .org   { font-size: 11pt; font-weight: bold; }
        .cover .title { font-size: 16pt; font-weight: bold; margin: 4px 0 2px; }
        .cover .sub   { font-size: 9pt; opacity: .85; }
        .cover .meta  { font-size: 7pt; opacity: .65; margin-top: 6px; }

        .kpi-bar { display: flex; gap: 10px; margin-bottom: 12px; }
        .kpi { flex: 1; border: 1px solid #ddd; padding: 5px 8px; border-radius: 4px; }
        .kpi .label { font-size: 7pt; color: #666; text-transform: uppercase; }
        .kpi .value { font-size: 10pt; font-weight: bold; margin-top: 1px; color: #166534; }

        table.fatture { width: 100%; border-collapse: collapse; font-size: 7.5pt; margin-bottom: 16px; }
        table.fatture thead th { background: #166534; color: #fff; padding: 4px 6px; text-align: left; }
        table.fatture thead th.num { text-align: right; }
        table.fatture tbody tr:nth-child(even) { background: #f0fdf4; }
        table.fatture tbody td { padding: 3px 6px; border-bottom: 1px solid #e5e7eb; }
        table.fatture tbody td.num { text-align: right; font-variant-numeric: tabular-nums; }
        table.fatture tfoot td { background: #dcfce7; font-weight: bold; padding: 4px 6px; border-top: 2px solid #166534; }
        table.fatture tfoot td.num { text-align: right; }

        .riepilogo { margin-top: 16px; page-break-inside: avoid; }
        .riepilogo h3 { font-size: 9pt; color: #166534; font-weight: bold; margin-bottom: 6px;
                        border-bottom: 1px solid #bbf7d0; padding-bottom: 3px; }
        table.riepilogo-aliquote { width: 50%; border-collapse: collapse; font-size: 7.5pt; }
        table.riepilogo-aliquote th { background: #e2e8f0; padding: 3px 6px; text-align: left; }
        table.riepilogo-aliquote th.num { text-align: right; }
        table.riepilogo-aliquote td { padding: 3px 6px; border-bottom: 1px solid #e5e7eb; }
        table.riepilogo-aliquote td.num { text-align: right; }

        .page-footer { position: fixed; bottom: 10px; left: 0; right: 0; text-align: center;
                       font-size: 6.5pt; color: #999; border-top: 1px solid #eee; padding-top: 4px; }
    </style>
</head>
<body>

<div class="cover">
    <div class="org">{{ $nomeOrganizzazione }}</div>
    <div class="title">Registro Vendite (IVA a debito)</div>
    <div class="sub">Periodo: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
    <div class="meta">Generato il {{ now()->format('d/m/Y H:i') }}</div>
</div>

<div class="kpi-bar">
    <div class="kpi">
        <div class="label">Fatture</div>
        <div class="value">{{ $numeroFatture }}</div>
    </div>
    <div class="kpi">
        <div class="label">Totale Imponibile</div>
        <div class="value">{{ number_format($totaleImponibile, 2, ',', '.') }} €</div>
    </div>
    <div class="kpi">
        <div class="label">Totale IVA</div>
        <div class="value">{{ number_format($totaleIva, 2, ',', '.') }} €</div>
    </div>
    <div class="kpi">
        <div class="label">Totale Documenti</div>
        <div class="value">{{ number_format($totaleDocumenti, 2, ',', '.') }} €</div>
    </div>
</div>

<table class="fatture">
    <thead>
        <tr>
            <th>N° Fattura</th>
            <th>Data</th>
            <th>Tipo</th>
            <th class="num">Imponibile (€)</th>
            <th class="num">IVA (€)</th>
            <th class="num">Totale (€)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($fatture as $f)
        <tr>
            <td><strong>{{ $f['numero_fattura'] }}</strong></td>
            <td>{{ $f['data_fattura'] ? \Carbon\Carbon::parse($f['data_fattura'])->format('d/m/Y') : '—' }}</td>
            <td>{{ $f['tipo_documento'] ?? 'TD01' }}</td>
            <td class="num">{{ number_format($f['imponibile_totale'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($f['iva_totale'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($f['totale_documento'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3"><strong>TOTALE</strong></td>
            <td class="num">{{ number_format($totaleImponibile, 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totaleIva, 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totaleDocumenti, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

@if(count($perAliquota) > 0)
<div class="riepilogo">
    <h3>Riepilogo per aliquota IVA</h3>
    <table class="riepilogo-aliquote">
        <thead>
            <tr>
                <th>Codice</th>
                <th>Descrizione</th>
                <th class="num">%</th>
                <th class="num">Imponibile (€)</th>
                <th class="num">IVA (€)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($perAliquota as $a)
            <tr>
                <td>{{ $a['codice'] }}</td>
                <td>{{ $a['descrizione'] ?? '' }}</td>
                <td class="num">{{ $a['percentuale'] }}%</td>
                <td class="num">{{ number_format($a['imponibile'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($a['iva'], 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="page-footer">
    {{ $nomeOrganizzazione }} — Registro Vendite {{ $from }} / {{ $to }} — pagina <span class="pagenum"></span>
</div>

</body>
</html>
