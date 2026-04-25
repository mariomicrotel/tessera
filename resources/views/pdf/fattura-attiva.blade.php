<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
  .header { border-bottom: 2px solid #3b82f6; padding-bottom: 10px; margin-bottom: 16px; }
  .header h1 { font-size: 20px; margin: 0; color: #1d4ed8; }
  .meta { display: flex; justify-content: space-between; margin-bottom: 12px; }
  .meta div { width: 48%; }
  .meta label { font-weight: bold; font-size: 10px; color: #666; display: block; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th { background: #1d4ed8; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
  td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
  .tr-alt { background: #f8fafc; }
  .totali { margin-top: 12px; text-align: right; }
  .totali table { width: 260px; margin-left: auto; }
  .totali td { border: none; padding: 3px 6px; }
  .totali .grand { font-weight: bold; font-size: 13px; border-top: 2px solid #3b82f6; }
  .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: bold; }
  .badge-emessa { background: #dbeafe; color: #1d4ed8; }
</style>
</head>
<body>

<div class="header">
  <h1>{{ $tenant->name }}</h1>
  <div>{{ $tenant->codice_fiscale ?? '' }}{{ $tenant->partita_iva ? ' · P.IVA '.$tenant->partita_iva : '' }}</div>
</div>

<div style="display:flex; justify-content:space-between; margin-bottom:16px;">
  <div>
    <div style="font-size:14px; font-weight:bold; color:#1d4ed8;">{{ $fattura->tipo_documento === 'TD04' ? 'NOTA DI CREDITO' : 'FATTURA' }}</div>
    <div style="font-size:18px; font-weight:bold;">{{ $fattura->numero_fattura }}</div>
    <div>Data: {{ $fattura->data_fattura?->format('d/m/Y') }}</div>
    @if($fattura->data_scadenza)
    <div>Scadenza: {{ $fattura->data_scadenza->format('d/m/Y') }}</div>
    @endif
  </div>
  <div style="text-align:right;">
    <div style="font-weight:bold;">Cliente</div>
    <div>{{ $fattura->cliente_id ? 'Cliente #'.$fattura->cliente_id : 'N/D' }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th style="width:40%">Descrizione</th>
      <th style="width:8%; text-align:right">Qta</th>
      <th style="width:12%; text-align:right">Prezzo</th>
      <th style="width:8%; text-align:right">Sconto</th>
      <th style="width:12%; text-align:right">Imponibile</th>
      <th style="width:8%; text-align:right">IVA%</th>
      <th style="width:12%; text-align:right">Totale</th>
    </tr>
  </thead>
  <tbody>
    @foreach($fattura->righe as $i => $riga)
    <tr class="{{ $i % 2 === 1 ? 'tr-alt' : '' }}">
      <td>{{ $riga->descrizione }}</td>
      <td style="text-align:right">{{ number_format((float)$riga->quantita, 2, ',', '.') }}</td>
      <td style="text-align:right">€ {{ number_format((float)$riga->prezzo_unitario, 2, ',', '.') }}</td>
      <td style="text-align:right">{{ $riga->sconto_percentuale > 0 ? number_format((float)$riga->sconto_percentuale,1).'%' : '-' }}</td>
      <td style="text-align:right">€ {{ number_format((float)$riga->imponibile, 2, ',', '.') }}</td>
      <td style="text-align:right">{{ $riga->codiceIva?->percentuale ?? 0 }}%</td>
      <td style="text-align:right">€ {{ number_format((float)$riga->totale, 2, ',', '.') }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="totali">
  <table>
    <tr><td>Imponibile</td><td style="text-align:right">€ {{ number_format((float)$fattura->imponibile_totale, 2, ',', '.') }}</td></tr>
    <tr><td>IVA</td><td style="text-align:right">€ {{ number_format((float)$fattura->iva_totale, 2, ',', '.') }}</td></tr>
    <tr class="grand"><td><strong>TOTALE</strong></td><td style="text-align:right"><strong>€ {{ number_format((float)$fattura->totale_documento, 2, ',', '.') }}</strong></td></tr>
  </table>
</div>

@if($fattura->note)
<div style="margin-top:16px; padding:8px; background:#f1f5f9; border-radius:4px; font-size:10px;">
  <strong>Note:</strong> {{ $fattura->note }}
</div>
@endif

</body>
</html>
