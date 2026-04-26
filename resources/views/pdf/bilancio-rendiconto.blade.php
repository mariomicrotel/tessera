<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Rendiconto Gestionale {{ $anno }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #222; line-height: 1.45; }

        .cover { background: #1e3a5f; color: #fff; padding: 26px 36px 20px; margin-bottom: 18px; }
        .cover .org   { font-size: 13pt; font-weight: bold; }
        .cover .title { font-size: 18pt; font-weight: bold; margin: 6px 0 3px; }
        .cover .sub   { font-size: 10pt; opacity: .85; }
        .cover .meta  { font-size: 7.5pt; opacity: .7; margin-top: 8px; }

        .area-header { background: #1e3a5f; color: #fff; padding: 5px 10px;
                       font-size: 9.5pt; font-weight: bold; margin-bottom: 0; margin-top: 14px; }

        table.rend { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-bottom: 4px; }
        table.rend th { background: #e8edf5; padding: 4px 8px; border-bottom: 1px solid #b0bfd0; }
        table.rend th.r { text-align: right; }
        table.rend td { padding: 3px 8px; border-bottom: 1px solid #f0f0f0; }
        table.rend td.r { text-align: right; font-family: monospace; }
        table.rend tr.voce-e td { color: #166534; padding-left: 16px; }
        table.rend tr.voce-u td { color: #991b1b; padding-left: 16px; }
        table.rend tr.subtotale td { font-weight: bold; border-top: 1px solid #ccc; background: #f0f4fa; }
        table.rend tr.risultato-pos td { font-weight: bold; border-top: 2px solid #166534; background: #dcfce7; color: #166534; }
        table.rend tr.risultato-neg td { font-weight: bold; border-top: 2px solid #991b1b; background: #fee2e2; color: #991b1b; }

        table.riepilogo { width: 100%; border-collapse: collapse; font-size: 9pt;
                          margin-top: 18px; background: #f0f4fa; }
        table.riepilogo td { padding: 5px 10px; border-bottom: 1px solid #ddd; }
        table.riepilogo td.r { text-align: right; font-family: monospace; font-weight: bold; }
        table.riepilogo tr.totale-finale td { font-weight: bold; background: #1e3a5f; color: #fff; }

        .footer { margin-top: 16px; font-size: 7pt; color: #999;
                  border-top: 1px solid #e5e7eb; padding-top: 5px; }
    </style>
</head>
<body>

<div class="cover">
    <div class="org">{{ $tenant->name }}</div>
    <div class="title">RENDICONTO GESTIONALE ETS</div>
    <div class="sub">Esercizio {{ $anno }} — per area di attività</div>
    <div class="meta">Art. 13 D.Lgs. 117/2017 &nbsp;·&nbsp; Generato il {{ now()->format('d/m/Y H:i') }}</div>
</div>

@foreach($rendiconto['aree'] as $areaKey => $area)
@if($area['tot_entrate'] > 0 || $area['tot_uscite'] > 0)
<div class="area-header">{{ $area['label'] }}</div>
<table class="rend">
    <thead>
        <tr>
            <th style="text-align:left">Voce</th>
            <th class="r">{{ $anno }}</th>
            <th class="r">{{ $rendiconto['anno_prec'] }}</th>
        </tr>
    </thead>
    <tbody>
        @if($area['entrate']->count() > 0)
        <tr>
            <td colspan="3" style="font-weight:bold; color:#166534; padding: 3px 8px 2px;">Entrate</td>
        </tr>
        @foreach($area['entrate'] as $v)
        <tr class="voce-e">
            <td>{{ $v->codice }} {{ $v->descrizione }}</td>
            <td class="r">{{ number_format($v->saldo, 2, ',', '.') }}</td>
            <td class="r">—</td>
        </tr>
        @endforeach
        <tr class="subtotale">
            <td>Totale entrate</td>
            <td class="r">{{ number_format($area['tot_entrate'], 2, ',', '.') }}</td>
            <td class="r">{{ number_format($area['tot_entrate_prec'], 2, ',', '.') }}</td>
        </tr>
        @endif

        @if($area['uscite']->count() > 0)
        <tr>
            <td colspan="3" style="font-weight:bold; color:#991b1b; padding: 5px 8px 2px;">Uscite</td>
        </tr>
        @foreach($area['uscite'] as $v)
        <tr class="voce-u">
            <td>{{ $v->codice }} {{ $v->descrizione }}</td>
            <td class="r">{{ number_format($v->saldo, 2, ',', '.') }}</td>
            <td class="r">—</td>
        </tr>
        @endforeach
        <tr class="subtotale">
            <td>Totale uscite</td>
            <td class="r">{{ number_format($area['tot_uscite'], 2, ',', '.') }}</td>
            <td class="r">{{ number_format($area['tot_uscite_prec'], 2, ',', '.') }}</td>
        </tr>
        @endif
    </tbody>
    <tfoot>
        <tr class="{{ $area['risultato'] >= 0 ? 'risultato-pos' : 'risultato-neg' }}">
            <td>Risultato area</td>
            <td class="r">{{ number_format($area['risultato'], 2, ',', '.') }}</td>
            <td class="r">{{ number_format($area['risultato_prec'], 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endif
@endforeach

<!-- Riepilogo generale -->
<table class="riepilogo">
    <tr>
        <td>Totale entrate complessive</td>
        <td class="r">€ {{ number_format($rendiconto['tot_entrate'], 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Totale uscite complessive</td>
        <td class="r">€ {{ number_format($rendiconto['tot_uscite'], 2, ',', '.') }}</td>
    </tr>
    <tr class="totale-finale">
        <td>{{ $rendiconto['risultato_netto'] >= 0 ? 'AVANZO NETTO DI GESTIONE' : 'DISAVANZO NETTO DI GESTIONE' }}</td>
        <td class="r">€ {{ number_format($rendiconto['risultato_netto'], 2, ',', '.') }}</td>
    </tr>
</table>

<div class="footer">
    Rendiconto Gestionale ETS — {{ $tenant->name }} — Esercizio {{ $anno }} — Elaborato il {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
