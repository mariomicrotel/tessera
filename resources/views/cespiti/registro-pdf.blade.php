<!DOCTYPE html>
<html lang="it">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registro Cespiti {{ $esercizio }}</title>
    <style>
        /* ── Reset e base ── */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.5px;
            color: #1a1a1a;
            line-height: 1.35;
        }

        /* ── Carta intestata fissa ── */
        @page { margin-top: 85px; margin-bottom: 40px; margin-left: 20px; margin-right: 20px; }
        .letterhead-wrapper { position: fixed; top: -75px; left: 0; right: 0; height: 72px; }
        .footer-wrapper      { position: fixed; bottom: -30px; left: 0; right: 0; height: 28px; border-top: 1px solid #ccc; padding-top: 4px; }
        .footer-wrapper p    { font-size: 7.5px; color: #666; text-align: center; }

        /* ── Intestazione documento ── */
        .doc-title {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #1e3a5f;
            padding-bottom: 6px;
        }
        .doc-title h1 { font-size: 13px; font-weight: bold; color: #1e3a5f; letter-spacing: 0.5px; }
        .doc-title p  { font-size: 8px; color: #555; margin-top: 2px; }

        /* ── Badge filtri attivi ── */
        .filtri { font-size: 7.5px; color: #666; margin-bottom: 8px; }
        .filtri span { background: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; padding: 1px 5px; margin-right: 4px; }

        /* ── Tabella principale ── */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            background-color: #1e3a5f;
            color: #ffffff;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 4px 3px;
            border: 1px solid #1e3a5f;
        }
        thead th.right  { text-align: right; }
        thead th.center { text-align: center; }
        thead th.left   { text-align: left; }

        tbody tr td {
            padding: 3px 3px;
            border: 1px solid #e0e0e0;
            vertical-align: top;
            font-size: 8px;
        }
        tbody tr:nth-child(even) td { background-color: #f9f9fb; }
        tbody tr:nth-child(odd)  td { background-color: #ffffff; }
        .num   { text-align: right; font-family: DejaVu Sans Mono, Courier New, monospace; }
        .center { text-align: center; }

        /* ── Riga categoria (subtotale) ── */
        tr.cat-header td {
            background-color: #e8eef5 !important;
            font-weight: bold;
            font-size: 8px;
            color: #1e3a5f;
            padding: 4px 3px;
            border-top: 2px solid #1e3a5f;
        }
        tr.cat-subtotal td {
            background-color: #d6e4f0 !important;
            font-weight: bold;
            font-size: 8px;
            border-top: 1px solid #1e3a5f;
        }

        /* ── Totale generale ── */
        tr.totale-generale td {
            background-color: #1e3a5f !important;
            color: #ffffff !important;
            font-weight: bold;
            font-size: 9px;
            padding: 5px 3px;
            border: 2px solid #1e3a5f;
        }

        /* ── Cespiti dismessi ── */
        tr.dismesso td { color: #888; font-style: italic; }

        /* ── Stato badge ── */
        .stato-badge {
            display: inline-block;
            border-radius: 3px;
            padding: 1px 4px;
            font-size: 7px;
            font-weight: bold;
        }
        .stato-in_uso   { background: #d1fae5; color: #065f46; }
        .stato-dismesso { background: #f3f4f6; color: #6b7280; }
        .stato-venduto  { background: #dbeafe; color: #1e40af; }

        /* ── Legenda ── */
        .legenda {
            margin-top: 12px;
            border-top: 1px solid #ccc;
            padding-top: 6px;
            font-size: 7.5px;
            color: #555;
        }
        .legenda strong { color: #1e3a5f; }

        /* ── Nota legale ── */
        .nota-legale {
            margin-top: 8px;
            font-size: 7px;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ── Carta intestata fissa ── --}}
    <div class="letterhead-wrapper">
        @include('pdf.letterhead', $letterhead)
        <hr style="border: none; border-top: 1px solid #ccc; margin-top: 4px;" />
    </div>

    {{-- ── Footer paginazione ── --}}
    <div class="footer-wrapper">
        <p>
            Registro Cespiti — Esercizio {{ $esercizio }}
            &nbsp;|&nbsp;
            Elaborato il {{ now()->format('d/m/Y') }}
            &nbsp;|&nbsp;
            Pag. <span class="pagenum"></span>
        </p>
    </div>

    {{-- ── Titolo documento ── --}}
    <div class="doc-title">
        <h1>REGISTRO CESPITI — ESERCIZIO {{ $esercizio }}</h1>
        <p>DM 31/12/1988 — Coefficienti ministeriali di ammortamento</p>
    </div>

    {{-- ── Filtri attivi ── --}}
    @if($filtriAttivi)
    <div class="filtri">
        Filtri: @foreach($filtriAttivi as $f) <span>{{ $f }}</span> @endforeach
    </div>
    @endif

    {{-- ── Tabella cespiti ── --}}
    <table>
        <thead>
            <tr>
                <th class="left"   style="width:5%">Codice</th>
                <th class="left"   style="width:20%">Descrizione</th>
                <th class="left"   style="width:8%">Data acquisto</th>
                <th class="right"  style="width:9%">Costo storico</th>
                <th class="right"  style="width:9%">F.do inizio {{ $esercizio }}</th>
                <th class="right"  style="width:9%">Aliquota</th>
                <th class="right"  style="width:9%">Quota {{ $esercizio }}</th>
                <th class="right"  style="width:9%">F.do fine {{ $esercizio }}</th>
                <th class="right"  style="width:9%">VNC fine {{ $esercizio }}</th>
                <th class="center" style="width:8%">Stato</th>
                <th class="right"  style="width:5%">Ded. %</th>
            </tr>
        </thead>
        <tbody>

        @php
            $totCostoStorico = 0;
            $totFondoInizio  = 0;
            $totQuota        = 0;
            $totFondoFine    = 0;
            $totVncFine      = 0;
        @endphp

        @forelse($gruppi as $categoriaLabel => $cespiti)
            {{-- Riga intestazione categoria --}}
            <tr class="cat-header">
                <td colspan="11">{{ $categoriaLabel }}</td>
            </tr>

            @php
                $catCostoStorico = 0;
                $catFondoInizio  = 0;
                $catQuota        = 0;
                $catFondoFine    = 0;
                $catVncFine      = 0;
            @endphp

            @foreach($cespiti as $row)
            @php
                $catCostoStorico += $row['costo_storico'];
                $catFondoInizio  += $row['fondo_inizio'];
                $catQuota        += $row['quota'];
                $catFondoFine    += $row['fondo_fine'];
                $catVncFine      += $row['vnc_fine'];
            @endphp
            <tr class="{{ $row['stato'] !== 'in_uso' ? 'dismesso' : '' }}">
                <td>{{ $row['codice'] ?? '—' }}</td>
                <td>
                    {{ $row['nome'] }}
                    @if($row['matricola'])
                        <br/><span style="font-size:7px; color:#888;">{{ $row['matricola'] }}</span>
                    @endif
                </td>
                <td class="center">{{ $row['data_acquisto'] ? \Carbon\Carbon::parse($row['data_acquisto'])->format('d/m/Y') : '—' }}</td>
                <td class="num">{{ number_format($row['costo_storico'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($row['fondo_inizio'], 2, ',', '.') }}</td>
                <td class="num center">{{ $row['aliquota'] ? $row['aliquota'] . '%' : '—' }}</td>
                <td class="num">
                    @if($row['quota'] > 0)
                        {{ number_format($row['quota'], 2, ',', '.') }}
                        @if($row['quota_stato'])
                            <br/><span style="font-size:6.5px; color:#999;">{{ $row['quota_stato'] }}</span>
                        @endif
                    @else
                        —
                    @endif
                </td>
                <td class="num">{{ number_format($row['fondo_fine'], 2, ',', '.') }}</td>
                <td class="num" style="{{ $row['vnc_fine'] <= 0 ? 'color:#aaa;' : '' }}">
                    {{ number_format($row['vnc_fine'], 2, ',', '.') }}
                </td>
                <td class="center">
                    <span class="stato-badge stato-{{ $row['stato'] }}">
                        {{ ['in_uso' => 'In uso', 'dismesso' => 'Dismesso', 'venduto' => 'Venduto'][$row['stato']] ?? $row['stato'] }}
                    </span>
                </td>
                <td class="num center">{{ $row['deducibilita'] }}%</td>
            </tr>
            @endforeach

            {{-- Subtotale categoria --}}
            @php
                $totCostoStorico += $catCostoStorico;
                $totFondoInizio  += $catFondoInizio;
                $totQuota        += $catQuota;
                $totFondoFine    += $catFondoFine;
                $totVncFine      += $catVncFine;
            @endphp
            <tr class="cat-subtotal">
                <td colspan="3" style="text-align:right; padding-right:4px;">Subtotale categoria</td>
                <td class="num">{{ number_format($catCostoStorico, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($catFondoInizio, 2, ',', '.') }}</td>
                <td></td>
                <td class="num">{{ number_format($catQuota, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($catFondoFine, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($catVncFine, 2, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>

        @empty
            <tr>
                <td colspan="11" style="text-align:center; padding:16px; color:#999;">
                    Nessun cespite trovato per i criteri selezionati.
                </td>
            </tr>
        @endforelse

        </tbody>

        {{-- ── Totale generale ── --}}
        @if($gruppi->isNotEmpty())
        <tfoot>
            <tr class="totale-generale">
                <td colspan="3" style="text-align:right; padding-right:4px;">TOTALE GENERALE</td>
                <td class="num">{{ number_format($totCostoStorico, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totFondoInizio, 2, ',', '.') }}</td>
                <td></td>
                <td class="num">{{ number_format($totQuota, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totFondoFine, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totVncFine, 2, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- ── Riepilogo KPI ── --}}
    @if($gruppi->isNotEmpty())
    <table style="margin-top:12px; width:60%; float:right;">
        <tr>
            <td style="padding:3px 6px; font-size:8px; border:1px solid #ddd; background:#f9f9fb; width:60%;">Numero cespiti inclusi</td>
            <td style="padding:3px 6px; font-size:8px; border:1px solid #ddd; text-align:right; font-weight:bold;">{{ $totaleCespiti }}</td>
        </tr>
        <tr>
            <td style="padding:3px 6px; font-size:8px; border:1px solid #ddd; background:#f9f9fb;">Costo storico totale</td>
            <td style="padding:3px 6px; font-size:8px; border:1px solid #ddd; text-align:right; font-weight:bold;">€ {{ number_format($totCostoStorico, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding:3px 6px; font-size:8px; border:1px solid #ddd; background:#f9f9fb;">Fondo ammortamento (fine {{ $esercizio }})</td>
            <td style="padding:3px 6px; font-size:8px; border:1px solid #ddd; text-align:right; font-weight:bold;">€ {{ number_format($totFondoFine, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding:3px 6px; font-size:8px; border:1px solid #ddd; background:#1e3a5f; color:#fff;">Valore netto contabile (fine {{ $esercizio }})</td>
            <td style="padding:3px 6px; font-size:8px; border:1px solid #1e3a5f; text-align:right; font-weight:bold; background:#1e3a5f; color:#fff;">€ {{ number_format($totVncFine, 2, ',', '.') }}</td>
        </tr>
    </table>
    <div style="clear:both;"></div>
    @endif

    {{-- ── Legenda ── --}}
    <div class="legenda">
        <strong>Legenda colonne:</strong>
        Costo storico = valore di acquisto originale &nbsp;|&nbsp;
        F.do inizio = fondo ammortamento cumulato ad inizio esercizio &nbsp;|&nbsp;
        Quota = ammortamento dell'esercizio &nbsp;|&nbsp;
        F.do fine = fondo cumulato a fine esercizio &nbsp;|&nbsp;
        VNC = Valore Netto Contabile (costo storico − fondo fine) &nbsp;|&nbsp;
        Ded.% = percentuale deducibilità fiscale ai sensi del TUIR
        <br/>
        <strong>Nota:</strong>
        I coefficienti sono quelli del DM 31/12/1988. Il primo anno il coefficiente è ridotto al 50%.
        Quote indicate con <em>Bozza</em> non sono ancora state registrate in prima nota.
    </div>

    <div class="nota-legale">
        Documento generato automaticamente da Tessera · Network GTC &nbsp;·&nbsp; Uso interno
    </div>

</body>
</html>
