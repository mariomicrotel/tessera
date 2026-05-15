<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Libro Giornale {{ $from }} — {{ $to }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8pt; color: #222; line-height: 1.4; }

        .cover { background: #1e3a5f; color: #fff; padding: 20px 28px 16px; margin-bottom: 14px; }
        .cover .org   { font-size: 11pt; font-weight: bold; }
        .cover .title { font-size: 16pt; font-weight: bold; margin: 4px 0 2px; }
        .cover .sub   { font-size: 9pt; opacity: .85; }
        .cover .meta  { font-size: 7pt; opacity: .65; margin-top: 6px; }

        .kpi-bar { display: flex; gap: 12px; margin-bottom: 12px; }
        .kpi { flex: 1; border: 1px solid #ddd; padding: 6px 10px; border-radius: 4px; }
        .kpi .label { font-size: 7pt; color: #666; text-transform: uppercase; }
        .kpi .value { font-size: 11pt; font-weight: bold; margin-top: 2px; }
        .kpi.blue .value { color: #1e40af; }
        .kpi.orange .value { color: #c2410c; }

        .movimento { margin-bottom: 10px; page-break-inside: avoid; }
        .mov-header { background: #f1f5f9; border-left: 3px solid #1e3a5f; padding: 4px 8px;
                      display: flex; justify-content: space-between; font-size: 7.5pt; }
        .mov-header .num  { font-weight: bold; color: #1e3a5f; }
        .mov-header .data { color: #555; }
        .mov-header .desc { flex: 1; margin: 0 8px; }
        .mov-header .totali { text-align: right; color: #555; }

        table.righe { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        table.righe th { background: #e2e8f0; padding: 3px 6px; text-align: left; color: #444; }
        table.righe th.num { text-align: right; }
        table.righe td { padding: 3px 6px; border-bottom: 1px solid #f0f0f0; }
        table.righe td.num { text-align: right; font-variant-numeric: tabular-nums; }
        table.righe tr:last-child td { border-bottom: none; }

        .footer-totali { margin-top: 16px; border-top: 2px solid #1e3a5f; padding-top: 8px;
                         display: flex; justify-content: flex-end; gap: 24px; font-size: 8pt; }
        .footer-totali .tot-label { color: #555; }
        .footer-totali .tot-val   { font-weight: bold; font-size: 9pt; }
        .footer-totali .tot-dare  { color: #1e40af; }
        .footer-totali .tot-avere { color: #c2410c; }

        .page-footer { position: fixed; bottom: 10px; left: 0; right: 0; text-align: center;
                       font-size: 6.5pt; color: #999; border-top: 1px solid #eee; padding-top: 4px; }
    </style>
</head>
<body>

<div class="cover">
    <div class="org">{{ $nomeOrganizzazione }}</div>
    <div class="title">Libro Giornale</div>
    <div class="sub">Periodo: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
    <div class="meta">Generato il {{ now()->format('d/m/Y H:i') }} — Solo movimenti definitivi</div>
</div>

<div class="kpi-bar">
    <div class="kpi">
        <div class="label">Movimenti</div>
        <div class="value">{{ $numeroMovimenti }}</div>
    </div>
    <div class="kpi blue">
        <div class="label">Totale Dare</div>
        <div class="value">{{ number_format($totaleDare, 2, ',', '.') }} €</div>
    </div>
    <div class="kpi orange">
        <div class="label">Totale Avere</div>
        <div class="value">{{ number_format($totaleAvere, 2, ',', '.') }} €</div>
    </div>
</div>

@foreach($movimenti as $m)
<div class="movimento">
    <div class="mov-header">
        <span class="num">N° {{ $m['numero'] }}</span>
        <span class="data">{{ $m['data_registrazione'] ? \Carbon\Carbon::parse($m['data_registrazione'])->format('d/m/Y') : '—' }}</span>
        <span class="desc">{{ $m['descrizione'] }}@if($m['causale']) — <em>{{ $m['causale'] }}</em>@endif</span>
        <span class="totali">D {{ number_format($m['totale_dare'], 2, ',', '.') }} / A {{ number_format($m['totale_avere'], 2, ',', '.') }}</span>
    </div>
    <table class="righe">
        <thead>
            <tr>
                <th style="width:28%">Conto</th>
                <th>Descrizione</th>
                <th class="num" style="width:14%">Dare (€)</th>
                <th class="num" style="width:14%">Avere (€)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($m['righe'] as $r)
            <tr>
                <td>{{ $r['conto'] ?? '—' }}</td>
                <td>{{ $r['descrizione'] ?? '' }}</td>
                <td class="num">{{ $r['importo_dare']  > 0 ? number_format($r['importo_dare'],  2, ',', '.') : '' }}</td>
                <td class="num">{{ $r['importo_avere'] > 0 ? number_format($r['importo_avere'], 2, ',', '.') : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

<div class="footer-totali">
    <div>
        <div class="tot-label">Totale Dare</div>
        <div class="tot-val tot-dare">{{ number_format($totaleDare, 2, ',', '.') }} €</div>
    </div>
    <div>
        <div class="tot-label">Totale Avere</div>
        <div class="tot-val tot-avere">{{ number_format($totaleAvere, 2, ',', '.') }} €</div>
    </div>
</div>

<div class="page-footer">
    {{ $nomeOrganizzazione }} — Libro Giornale {{ $from }} / {{ $to }} — pagina <span class="pagenum"></span>
</div>

</body>
</html>
