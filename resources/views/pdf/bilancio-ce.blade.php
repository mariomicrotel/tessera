<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Conto Economico {{ $anno }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #222; line-height: 1.45; }

        .cover { background: #1e3a5f; color: #fff; padding: 26px 36px 20px; margin-bottom: 18px; }
        .cover .org   { font-size: 13pt; font-weight: bold; }
        .cover .title { font-size: 18pt; font-weight: bold; margin: 6px 0 3px; }
        .cover .sub   { font-size: 10pt; opacity: .85; }
        .cover .meta  { font-size: 7.5pt; opacity: .7; margin-top: 8px; }

        table.ce { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-bottom: 12px; }
        table.ce th { background: #e8edf5; padding: 4px 8px; border-bottom: 1px solid #b0bfd0; }
        table.ce th.r { text-align: right; }
        table.ce td { padding: 3px 8px; border-bottom: 1px solid #f0f0f0; }
        table.ce td.r { text-align: right; font-family: monospace; }
        table.ce tr.sezione-hdr td { background: #1e3a5f; color: #fff; font-weight: bold; padding: 4px 8px; }
        table.ce tr.mastro td { background: #f7f9fc; font-weight: bold; color: #1e3a5f; }
        table.ce tr.voce td { padding-left: 20px; color: #555; }
        table.ce tr.subtotale td { font-weight: bold; border-top: 1px solid #ccc; background: #f0f4fa; }
        table.ce tr.risultato td { font-weight: bold; border-top: 2px solid #1e3a5f;
                                    background: #e8edf5; color: #1e3a5f; font-size: 9pt; }
        table.ce tr.risultato-positivo td { color: #166534; background: #dcfce7; }
        table.ce tr.risultato-negativo  td { color: #991b1b; background: #fee2e2; }

        .footer { margin-top: 16px; font-size: 7pt; color: #999;
                  border-top: 1px solid #e5e7eb; padding-top: 5px; }
    </style>
</head>
<body>

<div class="cover">
    <div class="org">{{ $tenant->name }}</div>
    <div class="title">CONTO ECONOMICO</div>
    <div class="sub">Esercizio {{ $anno }} — comparato con {{ $ce['anno_prec'] }}</div>
    <div class="meta">D.Lgs. 127/1991 — IV Direttiva CEE &nbsp;·&nbsp; Generato il {{ now()->format('d/m/Y H:i') }}</div>
</div>

<table class="ce">
    <thead>
        <tr>
            <th style="text-align:left; width:55%">Voce</th>
            <th class="r">{{ $anno }}</th>
            <th class="r">{{ $ce['anno_prec'] }}</th>
            <th class="r">Var.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($ce['sezioni'] as $key => $sez)
        <tr class="sezione-hdr">
            <td colspan="4">{{ $sez['label'] }}</td>
        </tr>
        @foreach($sez['voci'] as $gruppo)
        <tr class="mastro">
            <td>{{ $gruppo['mastro'] }}</td>
            <td class="r"></td>
            <td class="r"></td>
            <td class="r"></td>
        </tr>
        @foreach($gruppo['voci'] as $voce)
        <tr class="voce">
            <td>{{ $voce['codice'] }} {{ $voce['descrizione'] }}</td>
            <td class="r">{{ number_format($voce['saldo'], 2, ',', '.') }}</td>
            <td class="r">{{ number_format($voce['saldo_prec'], 2, ',', '.') }}</td>
            <td class="r">
                @if($voce['saldo_prec'] != 0)
                    {{ round((($voce['saldo'] - $voce['saldo_prec']) / abs($voce['saldo_prec'])) * 100, 1) }}%
                @else —
                @endif
            </td>
        </tr>
        @endforeach
        @endforeach
        <tr class="subtotale">
            <td>Totale {{ $sez['label'] }}</td>
            <td class="r">{{ number_format($sez['totale'], 2, ',', '.') }}</td>
            <td class="r">{{ number_format($sez['totale_prec'], 2, ',', '.') }}</td>
            <td class="r">
                @if($sez['totale_prec'] != 0)
                    {{ round((($sez['totale'] - $sez['totale_prec']) / abs($sez['totale_prec'])) * 100, 1) }}%
                @else —
                @endif
            </td>
        </tr>
        @if($key === 'B')
        <tr class="risultato">
            <td>RISULTATO OPERATIVO (A − B)</td>
            <td class="r">{{ number_format($ce['risultato_operativo'], 2, ',', '.') }}</td>
            <td class="r">{{ number_format($ce['risultato_operativo_prec'], 2, ',', '.') }}</td>
            <td class="r"></td>
        </tr>
        @endif
        @endforeach

        <!-- Risultato ante imposte -->
        <tr class="risultato">
            <td>RISULTATO ANTE IMPOSTE</td>
            <td class="r">{{ number_format($ce['risultato_ante_imposte'], 2, ',', '.') }}</td>
            <td class="r">—</td>
            <td class="r"></td>
        </tr>

        <!-- Risultato esercizio -->
        <tr class="{{ $ce['risultato_esercizio'] >= 0 ? 'risultato-positivo' : 'risultato-negativo' }} risultato">
            <td>{{ $ce['risultato_esercizio'] >= 0 ? 'AVANZO DI GESTIONE' : 'DISAVANZO DI GESTIONE' }}</td>
            <td class="r">{{ number_format($ce['risultato_esercizio'], 2, ',', '.') }}</td>
            <td class="r">{{ number_format($ce['risultato_esercizio_prec'], 2, ',', '.') }}</td>
            <td class="r"></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    Conto Economico — {{ $tenant->name }} — Esercizio {{ $anno }} — Elaborato il {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
