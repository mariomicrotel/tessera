@php
    $sezioni = $rendiconto['sezioni'] ?? [];
    $anno = $rendiconto['anno'] ?? '';
    $annoPrec = $rendiconto['anno_precedente'] ?? (($rendiconto['anno'] ?? 0) - 1);
    $totaleUsciteT = 0.0;
    $totaleUsciteT1 = 0.0;
    $totaleEntrateT = 0.0;
    $totaleEntrateT1 = 0.0;

    /** Formato voce: solo parte numerica come "n) descrizione" (es. "A.1" -> "1) Materie prime", "INV.9" -> "9) Imposte"). */
    $voceLabel = function ($ministerialCode, $descrizione) {
        $code = $ministerialCode ?? '';
        $desc = $descrizione ?? '';
        $num = $code;
        if (($pos = strrpos($code, '.')) !== false) {
            $num = substr($code, $pos + 1);
        }
        return $num !== '' ? $num . ') ' . $desc : $desc;
    };

    $areeLettere = ['A', 'B', 'C', 'D', 'E'];
    $blocchiAreeAE = [];
    $blocchiInvestimenti = [];
    $sezioniByArea = [];
    $sezioniMiste = [];
    foreach ($sezioni as $s) {
        $area = $s['area'] ?? null;
        $tipo = $s['tipo_sezione'] ?? '';
        if (in_array($area, $areeLettere, true)) {
            if (!isset($sezioniByArea[$area])) {
                $sezioniByArea[$area] = ['uscite' => null, 'entrate' => null];
            }
            if ($tipo === 'uscita') {
                $sezioniByArea[$area]['uscite'] = $s;
            } elseif ($tipo === 'entrata') {
                $sezioniByArea[$area]['entrate'] = $s;
            }
        } elseif ($tipo === 'misto') {
            $sezioniMiste[] = $s;
        }
    }
    foreach ($areeLettere as $area) {
        $uscite = $sezioniByArea[$area]['uscite'] ?? null;
        $entrate = $sezioniByArea[$area]['entrate'] ?? null;
        if ($uscite || $entrate) {
            $vociU = $uscite ? ($uscite['voci'] ?? []) : [];
            $vociE = $entrate ? ($entrate['voci'] ?? []) : [];
            $totU = $uscite ? (float)($uscite['totale_uscite'] ?? 0) : 0;
            $totU1 = $uscite ? array_sum(array_map(function ($v) { return (float)($v['importo_anno_precedente'] ?? 0); }, $vociU)) : 0;
            $totE = $entrate ? (float)($entrate['totale_entrate'] ?? 0) : 0;
            $totE1 = $entrate ? array_sum(array_map(function ($v) { return (float)($v['importo_anno_precedente'] ?? 0); }, $vociE)) : 0;
            $totaleUsciteT += $totU;
            $totaleUsciteT1 += $totU1;
            $totaleEntrateT += $totE;
            $totaleEntrateT1 += $totE1;
            $n = max(count($vociU), count($vociE), 1);
            $righeDettaglio = [];
            for ($i = 0; $i < $n; $i++) {
                $vu = $vociU[$i] ?? null;
                $ve = $vociE[$i] ?? null;
                $righeDettaglio[] = [
                    'desc_u' => $vu ? $voceLabel($vu['ministerial_code'] ?? $vu['codice_voce'], $vu['descrizione'] ?? '') : '',
                    't_u' => $vu ? (float)($vu['importo'] ?? 0) : null,
                    't1_u' => $vu ? (float)($vu['importo_anno_precedente'] ?? 0) : null,
                    'desc_e' => $ve ? $voceLabel($ve['ministerial_code'] ?? $ve['codice_voce'], $ve['descrizione'] ?? '') : '',
                    't_e' => $ve ? (float)($ve['importo'] ?? 0) : null,
                    't1_e' => $ve ? (float)($ve['importo_anno_precedente'] ?? 0) : null,
                ];
            }
            $avanzoT = round($totE - $totU, 2);
            $avanzoT1 = round($totE1 - $totU1, 2);
            $blocchiAreeAE[] = [
                'area' => $area,
                'titolo_uscite' => $uscite ? $uscite['sezione'] : '',
                'titolo_entrate' => $entrate ? $entrate['sezione'] : '',
                'righe' => $righeDettaglio,
                'tot_u' => round($totU, 2),
                'tot_u1' => round($totU1, 2),
                'tot_e' => round($totE, 2),
                'tot_e1' => round($totE1, 2),
                'avanzo_t' => $avanzoT,
                'avanzo_t1' => $avanzoT1,
                'mostra_avanzo' => in_array($area, ['A', 'B', 'C', 'D'], true),
            ];
        }
    }
    $totaleEntrateAE = round($totaleEntrateT, 2);
    $totaleUsciteAE = round($totaleUsciteT, 2);
    $totaleEntrateAE1 = round($totaleEntrateT1, 2);
    $totaleUsciteAE1 = round($totaleUsciteT1, 2);
    $risultatoAE = round($totaleEntrateAE - $totaleUsciteAE, 2);
    $risultatoAE1 = round($totaleEntrateAE1 - $totaleUsciteAE1, 2);

    $bloccoFigurativi = null;
    foreach ($sezioniMiste as $m) {
        $nome = $m['sezione'] ?? '';
        $eFigurativi = (strpos($nome, 'Costi e proventi figurativi') !== false);
        $voci = $m['voci'] ?? [];
        $vociU = array_values(array_filter($voci, function ($v) { return ($v['tipo'] ?? '') === 'uscita'; }));
        $vociE = array_values(array_filter($voci, function ($v) { return ($v['tipo'] ?? '') === 'entrata'; }));
        if (count($vociU) === 0 && count($vociE) === 0) {
            continue;
        }
        $totU = array_sum(array_column($vociU, 'importo'));
        $totU1 = array_sum(array_map(function ($v) { return (float)($v['importo_anno_precedente'] ?? 0); }, $vociU));
        $totE = array_sum(array_column($vociE, 'importo'));
        $totE1 = array_sum(array_map(function ($v) { return (float)($v['importo_anno_precedente'] ?? 0); }, $vociE));
        $totaleUsciteT += $totU;
        $totaleUsciteT1 += $totU1;
        $totaleEntrateT += $totE;
        $totaleEntrateT1 += $totE1;
        $n = max(count($vociU), count($vociE), 1);
        $righeDettaglio = [];
        for ($i = 0; $i < $n; $i++) {
            $vu = $vociU[$i] ?? null;
            $ve = $vociE[$i] ?? null;
            $righeDettaglio[] = [
                'desc_u' => $vu ? $voceLabel($vu['ministerial_code'] ?? $vu['codice_voce'], $vu['descrizione'] ?? '') : '',
                't_u' => $vu ? (float)($vu['importo'] ?? 0) : null,
                't1_u' => $vu ? (float)($vu['importo_anno_precedente'] ?? 0) : null,
                'desc_e' => $ve ? $voceLabel($ve['ministerial_code'] ?? $ve['codice_voce'], $ve['descrizione'] ?? '') : '',
                't_e' => $ve ? (float)($ve['importo'] ?? 0) : null,
                't1_e' => $ve ? (float)($ve['importo_anno_precedente'] ?? 0) : null,
            ];
        }
        $titoloU = $nome;
        $titoloE = $nome;
        if (!$eFigurativi && strpos($nome, 'Entrate/Uscite') !== false) {
            $titoloU = str_replace('Entrate/Uscite', 'Uscite', $nome);
            $titoloE = str_replace('Entrate/Uscite', 'Entrate', $nome);
        }
        $blocco = [
            'area' => $eFigurativi ? 'FIG' : 'INV',
            'titolo_uscite' => $titoloU,
            'titolo_entrate' => $titoloE,
            'righe' => $righeDettaglio,
            'tot_u' => round($totU, 2),
            'tot_u1' => round($totU1, 2),
            'tot_e' => round($totE, 2),
            'tot_e1' => round($totE1, 2),
            'avanzo_t' => null,
            'avanzo_t1' => null,
            'mostra_avanzo' => false,
        ];
        if ($eFigurativi) {
            $bloccoFigurativi = $blocco;
        } else {
            $blocchiInvestimenti[] = $blocco;
        }
    }

    $totaleUsciteT = round($totaleUsciteT, 2);
    $totaleUsciteT1 = round($totaleUsciteT1, 2);
    $totaleEntrateT = round($totaleEntrateT, 2);
    $totaleEntrateT1 = round($totaleEntrateT1, 2);
    $risultatoT = (float)($rendiconto['risultato_per_cassa'] ?? 0);
    $risultatoT1 = round($totaleEntrateT1 - $totaleUsciteT1, 2);

    $totaleEntrateINV = array_sum(array_column($blocchiInvestimenti, 'tot_e'));
    $totaleUsciteINV = array_sum(array_column($blocchiInvestimenti, 'tot_u'));
    $totaleEntrateINV1 = array_sum(array_column($blocchiInvestimenti, 'tot_e1'));
    $totaleUsciteINV1 = array_sum(array_column($blocchiInvestimenti, 'tot_u1'));
    $avanzoINV_t = round($totaleEntrateINV - $totaleUsciteINV, 2);
    $avanzoINV_t1 = round($totaleEntrateINV1 - $totaleUsciteINV1, 2);
    $imposteInvT = 0.0;
    $imposteInvT1 = 0.0;
    foreach ($sezioniMiste as $m) {
        foreach ($m['voci'] ?? [] as $v) {
            if (($v['codice_voce'] ?? '') === 'EXP_TAXES') {
                $imposteInvT += (float)($v['importo'] ?? 0);
                $imposteInvT1 += (float)($v['importo_anno_precedente'] ?? 0);
            }
        }
    }
    $imposteInvT = round($imposteInvT, 2);
    $imposteInvT1 = round($imposteInvT1, 2);

    $righeDopoE = [
        ['etichetta' => "Avanzo/disavanzo d'esercizio prima delle imposte", 'es_t' => $risultatoAE, 'es_t1' => $risultatoAE1],
        ['etichetta' => 'Imposte', 'es_t' => null, 'es_t1' => null],
        ['etichetta' => "Avanzo/disavanzo d'esercizio prima di investimenti e disinvestimenti patrimoniali, e finanziamenti", 'es_t' => $risultatoAE, 'es_t1' => $risultatoAE1],
    ];
    $righeDopoInv = [
        ['etichetta' => 'Imposte', 'es_t' => $imposteInvT, 'es_t1' => $imposteInvT1],
        ['etichetta' => "Avanzo/disavanzo da entrate e uscite per investimenti e disinvestimenti patrimoniali e finanziamenti", 'es_t' => $avanzoINV_t, 'es_t1' => $avanzoINV_t1],
    ];
    $righeSintesi = [
        ['etichetta' => "Avanzo/disavanzo d'esercizio prima di investimenti e disinvestimenti patrimoniali e finanziamenti", 'es_t' => $risultatoAE, 'es_t1' => $risultatoAE1],
        ['etichetta' => "Avanzo/disavanzo da entrate e uscite per investimenti e disinvestimenti patrimoniali e finanziamenti", 'es_t' => $avanzoINV_t, 'es_t1' => $avanzoINV_t1],
        ['etichetta' => 'Avanzo/disavanzo complessivo', 'es_t' => $risultatoT, 'es_t1' => $risultatoT1, 'grassetto' => true],
    ];
    $contiSaldi = $rendiconto['conti_saldi'] ?? [];
    $fmt = function ($n) { return $n !== null && $n !== '' ? number_format((float)$n, 2, ',', '.') : ''; };

    $blocchiAreeAD = array_values(array_filter($blocchiAreeAE, function ($b) { return in_array($b['area'], ['A', 'B', 'C', 'D'], true); }));
    $bloccoE = null;
    foreach ($blocchiAreeAE as $b) {
        if ($b['area'] === 'E') {
            $bloccoE = $b;
            break;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Rendiconto per cassa {{ $anno }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; padding: 105px 20px 20px 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 14px; font-weight: bold; }
        .subtitle { font-size: 10px; color: #333; margin-top: 4px; }
        .tabella-rendiconto { width: 100%; border-collapse: collapse; margin: 6px 0; table-layout: fixed; }
        .tabella-sezione { margin-bottom: 14px; }
        .tabella-rendiconto th, .tabella-rendiconto td { padding: 5px 6px; border: 1px solid #ddd; box-sizing: border-box; }
        .tabella-rendiconto th { background: #f5f5f5; font-weight: bold; text-align: left; }
        .tabella-rendiconto td.num { text-align: right; }
        .tabella-rendiconto tbody tr.row-even { background: #fff; }
        .tabella-rendiconto tbody tr.row-odd { background: #f5f5f5; }
        .tabella-rendiconto .totale-row { font-weight: bold; background: #e8e8e8; }
        .risultato { font-size: 12px; font-weight: bold; margin-top: 16px; padding: 10px; border: 1px solid #333; }
        .footer { margin-top: 24px; font-size: 8px; color: #666; }
    </style>
</head>
<body>
    <div class="letterhead-fixed">@include('pdf.letterhead', $letterhead ?? [])</div>
    <div class="header">
        <div class="title">Rendiconto economico per cassa – Anno {{ $anno }}</div>
        <div class="subtitle">Modello D – DM 5 marzo 2020 (criterio di cassa).</div>
    </div>

    @php
        $colgroup12 = '<colgroup><col span="4" style="width: 33.33%"><col style="width: 8.34%"><col style="width: 8.34%"><col span="4" style="width: 33.33%"><col style="width: 8.34%"><col style="width: 8.34%"></colgroup>';
        $theadRendiconto = '<thead><tr><th colspan="4">Uscite</th><th colspan="1" style="text-align: right;">' . $anno . '</th><th colspan="1" style="text-align: right;">' . $annoPrec . '</th><th colspan="4">Entrate</th><th colspan="1" style="text-align: right;">' . $anno . '</th><th colspan="1" style="text-align: right;">' . $annoPrec . '</th></tr></thead>';
        $headerShown = false;
    @endphp

    @foreach($blocchiAreeAD as $blocco)
    <table class="tabella-rendiconto tabella-sezione">
        {!! $colgroup12 !!}
        @if(!$headerShown){!! $theadRendiconto !!}@php $headerShown = true; @endphp @endif
        <tbody>
            @php $rowIndex = 0; @endphp
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"><strong>{{ $blocco['titolo_uscite'] ? $blocco['area'] . ') ' . $blocco['titolo_uscite'] : '' }}</strong></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
                <td colspan="4"><strong>{{ $blocco['titolo_entrate'] ? $blocco['area'] . ') ' . $blocco['titolo_entrate'] : '' }}</strong></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
            </tr>
            @foreach($blocco['righe'] as $r)
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4">{{ $r['desc_u'] ?: '' }}</td>
                <td colspan="1" class="num">{{ $r['t_u'] !== null ? $fmt($r['t_u']) : '' }}</td>
                <td colspan="1" class="num">{{ $r['t1_u'] !== null ? $fmt($r['t1_u']) : '' }}</td>
                <td colspan="4">{{ $r['desc_e'] ?: '' }}</td>
                <td colspan="1" class="num">{{ $r['t_e'] !== null ? $fmt($r['t_e']) : '' }}</td>
                <td colspan="1" class="num">{{ $r['t1_e'] !== null ? $fmt($r['t1_e']) : '' }}</td>
            </tr>
            @endforeach
            <tr class="totale-row row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4">Totale</td>
                <td colspan="1" class="num">{{ $fmt($blocco['tot_u']) }}</td>
                <td colspan="1" class="num">{{ $fmt($blocco['tot_u1']) }}</td>
                <td colspan="4">Totale</td>
                <td colspan="1" class="num">{{ $fmt($blocco['tot_e']) }}</td>
                <td colspan="1" class="num">{{ $fmt($blocco['tot_e1']) }}</td>
            </tr>
            @if($blocco['mostra_avanzo'])
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
                <td colspan="4"><strong>Avanzo/disavanzo attività {{ $blocco['area'] === 'A' ? 'di interesse generale' : ($blocco['area'] === 'B' ? 'diverse' : ($blocco['area'] === 'C' ? 'di raccolta fondi' : 'finanziarie e patrimoniali')) }}</strong></td>
                <td colspan="1" class="num">{{ $blocco['avanzo_t'] !== null ? $fmt($blocco['avanzo_t']) : '' }}</td>
                <td colspan="1" class="num">{{ $blocco['avanzo_t1'] !== null ? $fmt($blocco['avanzo_t1']) : '' }}</td>
            </tr>
            @endif
        </tbody>
    </table>
    @endforeach

    <table class="tabella-rendiconto tabella-sezione">
        {!! $colgroup12 !!}
        @if(!$headerShown){!! $theadRendiconto !!}@php $headerShown = true; @endphp @endif
        <tbody>
            @php $rowIndex = 0; @endphp
            @if($bloccoE)
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"><strong>{{ $bloccoE['titolo_uscite'] ? $bloccoE['area'] . ') ' . $bloccoE['titolo_uscite'] : '' }}</strong></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
                <td colspan="4"><strong>{{ $bloccoE['titolo_entrate'] ? $bloccoE['area'] . ') ' . $bloccoE['titolo_entrate'] : '' }}</strong></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
            </tr>
            @foreach($bloccoE['righe'] as $r)
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4">{{ $r['desc_u'] ?: '' }}</td>
                <td colspan="1" class="num">{{ $r['t_u'] !== null ? $fmt($r['t_u']) : '' }}</td>
                <td colspan="1" class="num">{{ $r['t1_u'] !== null ? $fmt($r['t1_u']) : '' }}</td>
                <td colspan="4">{{ $r['desc_e'] ?: '' }}</td>
                <td colspan="1" class="num">{{ $r['t_e'] !== null ? $fmt($r['t_e']) : '' }}</td>
                <td colspan="1" class="num">{{ $r['t1_e'] !== null ? $fmt($r['t1_e']) : '' }}</td>
            </tr>
            @endforeach
            <tr class="totale-row row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4">Totale</td>
                <td colspan="1" class="num">{{ $fmt($bloccoE['tot_u']) }}</td>
                <td colspan="1" class="num">{{ $fmt($bloccoE['tot_u1']) }}</td>
                <td colspan="4">Totale</td>
                <td colspan="1" class="num">{{ $fmt($bloccoE['tot_e']) }}</td>
                <td colspan="1" class="num">{{ $fmt($bloccoE['tot_e1']) }}</td>
            </tr>
            @endif
            <tr class="totale-row row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
                <td colspan="4"><strong>Totale entrate della gestione</strong></td>
                <td colspan="1" class="num">{{ $fmt($totaleEntrateAE) }}</td>
                <td colspan="1" class="num">{{ $fmt($totaleEntrateAE1) }}</td>
            </tr>
            <tr class="totale-row row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"><strong>Totale uscite della gestione</strong></td>
                <td colspan="1" class="num">{{ $fmt($totaleUsciteAE) }}</td>
                <td colspan="1" class="num">{{ $fmt($totaleUsciteAE1) }}</td>
                <td colspan="4"></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
            </tr>
            @foreach($righeDopoE as $rs)
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
                <td colspan="4"><strong>{{ $rs['etichetta'] }}</strong></td>
                <td colspan="1" class="num">{{ $rs['es_t'] !== null ? $fmt($rs['es_t']) : '' }}</td>
                <td colspan="1" class="num">{{ $rs['es_t1'] !== null ? $fmt($rs['es_t1']) : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="tabella-rendiconto tabella-sezione">
        {!! $colgroup12 !!}
        @if(!$headerShown){!! $theadRendiconto !!}@php $headerShown = true; @endphp @endif
        <tbody>
            @php $rowIndex = 0; @endphp
            @foreach($blocchiInvestimenti as $blocco)
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"><strong>{{ $blocco['titolo_uscite'] ?: '' }}</strong></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
                <td colspan="4"><strong>{{ $blocco['titolo_entrate'] ?: '' }}</strong></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
            </tr>
            @foreach($blocco['righe'] as $r)
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4">{{ $r['desc_u'] ?: '' }}</td>
                <td colspan="1" class="num">{{ $r['t_u'] !== null ? $fmt($r['t_u']) : '' }}</td>
                <td colspan="1" class="num">{{ $r['t1_u'] !== null ? $fmt($r['t1_u']) : '' }}</td>
                <td colspan="4">{{ $r['desc_e'] ?: '' }}</td>
                <td colspan="1" class="num">{{ $r['t_e'] !== null ? $fmt($r['t_e']) : '' }}</td>
                <td colspan="1" class="num">{{ $r['t1_e'] !== null ? $fmt($r['t1_e']) : '' }}</td>
            </tr>
            @endforeach
            <tr class="totale-row row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4">Totale</td>
                <td colspan="1" class="num">{{ $fmt($blocco['tot_u']) }}</td>
                <td colspan="1" class="num">{{ $fmt($blocco['tot_u1']) }}</td>
                <td colspan="4">Totale</td>
                <td colspan="1" class="num">{{ $fmt($blocco['tot_e']) }}</td>
                <td colspan="1" class="num">{{ $fmt($blocco['tot_e1']) }}</td>
            </tr>
            @endforeach
            @foreach($righeDopoInv as $rs)
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
                <td colspan="4"><strong>{{ $rs['etichetta'] }}</strong></td>
                <td colspan="1" class="num">{{ $rs['es_t'] !== null ? $fmt($rs['es_t']) : '' }}</td>
                <td colspan="1" class="num">{{ $rs['es_t1'] !== null ? $fmt($rs['es_t1']) : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="tabella-rendiconto tabella-sezione">
        {!! $colgroup12 !!}
        @if(!$headerShown){!! $theadRendiconto !!}@php $headerShown = true; @endphp @endif
        <tbody>
            @php $rowIndex = 0; @endphp
            @foreach($righeSintesi as $rs)
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
                <td colspan="4"><strong>{{ $rs['etichetta'] }}</strong></td>
                <td colspan="1" class="num">{{ $rs['es_t'] !== null ? $fmt($rs['es_t']) : '' }}</td>
                <td colspan="1" class="num">{{ $rs['es_t1'] !== null ? $fmt($rs['es_t1']) : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if(count($contiSaldi) > 0)
    <table class="tabella-rendiconto tabella-sezione">
        {!! $colgroup12 !!}
        @if(!$headerShown){!! $theadRendiconto !!}@php $headerShown = true; @endphp @endif
        <tbody>
            @php $rowIndex = 0; @endphp
            <tr class="totale-row row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="12"><strong>CASSA E BANCA</strong></td>
            </tr>
            @foreach($contiSaldi as $conto)
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
                <td colspan="4">{{ $conto['nome'] ?? '' }}</td>
                <td colspan="1" class="num">{{ isset($conto['saldo_anno']) ? $fmt($conto['saldo_anno']) : '' }}</td>
                <td colspan="1" class="num">{{ isset($conto['saldo_anno_precedente']) ? $fmt($conto['saldo_anno_precedente']) : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($bloccoFigurativi)
    <table class="tabella-rendiconto tabella-sezione">
        {!! $colgroup12 !!}
        @if(!$headerShown){!! $theadRendiconto !!}@php $headerShown = true; @endphp @endif
        <tbody>
            @php $rowIndex = 0; @endphp
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4"><strong>{{ $bloccoFigurativi['titolo_uscite'] ?: '' }}</strong></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
                <td colspan="4"><strong>{{ $bloccoFigurativi['titolo_entrate'] ?: '' }}</strong></td>
                <td colspan="1" class="num"></td>
                <td colspan="1" class="num"></td>
            </tr>
            @foreach($bloccoFigurativi['righe'] as $r)
            <tr class="row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4">{{ $r['desc_u'] ?: '' }}</td>
                <td colspan="1" class="num">{{ $r['t_u'] !== null ? $fmt($r['t_u']) : '' }}</td>
                <td colspan="1" class="num">{{ $r['t1_u'] !== null ? $fmt($r['t1_u']) : '' }}</td>
                <td colspan="4">{{ $r['desc_e'] ?: '' }}</td>
                <td colspan="1" class="num">{{ $r['t_e'] !== null ? $fmt($r['t_e']) : '' }}</td>
                <td colspan="1" class="num">{{ $r['t1_e'] !== null ? $fmt($r['t1_e']) : '' }}</td>
            </tr>
            @endforeach
            <tr class="totale-row row-{{ $rowIndex % 2 === 0 ? 'even' : 'odd' }}">
                @php $rowIndex++; @endphp
                <td colspan="4">Totale</td>
                <td colspan="1" class="num">{{ $fmt($bloccoFigurativi['tot_u']) }}</td>
                <td colspan="1" class="num">{{ $fmt($bloccoFigurativi['tot_u1']) }}</td>
                <td colspan="4">Totale</td>
                <td colspan="1" class="num">{{ $fmt($bloccoFigurativi['tot_e']) }}</td>
                <td colspan="1" class="num">{{ $fmt($bloccoFigurativi['tot_e1']) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <div class="risultato">
        Risultato per cassa (entrate − uscite): € {{ number_format((float)($rendiconto['risultato_per_cassa'] ?? 0), 2, ',', '.') }}
    </div>

    <div class="footer">
        Documento generato il {{ $rendiconto['data_generazione'] ?? now()->format('d/m/Y H:i') }}. Redatto con criterio di cassa – Modello D DM 5 marzo 2020 (GU n. 96 del 18-04-2020).
    </div>
    @include('pdf.footer-pagination')
</body>
</html>
