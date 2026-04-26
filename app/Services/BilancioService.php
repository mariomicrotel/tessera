<?php

namespace App\Services;

use App\Models\ContoContabile;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic per il Bilancio CEE e il Rendiconto Gestionale ETS.
 *
 * Legge dal piano dei conti (conti_contabili.tipo_bilancio) e dai
 * movimenti contabili confermati dell'anno per costruire:
 *
 *  1. Stato Patrimoniale (SP) — struttura IV Direttiva CEE
 *  2. Conto Economico (CE)    — struttura IV Direttiva CEE
 *  3. Rendiconto Gestionale ETS — per area di attività (istituzionale/commerciale)
 *
 * Calcolo saldi per natura:
 *  - Attivo:           saldo = Σ dare − Σ avere  (positivo = asset)
 *  - Passivo / PN:     saldo = Σ avere − Σ dare  (positivo = debito / capitale)
 *  - Costo:            saldo = Σ dare − Σ avere  (positivo = costo)
 *  - Ricavo:           saldo = Σ avere − Σ dare  (positivo = ricavo)
 */
class BilancioService
{
    // ─────────────────────────────────────────────────────────────────────
    // Stato Patrimoniale
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Costruisce lo Stato Patrimoniale per l'anno dato.
     *
     * @return array{
     *   attivo:    array,
     *   passivo:   array,
     *   totale_attivo:  float,
     *   totale_passivo: float,
     *   differenza:     float,
     * }
     */
    public function statoPatrimoniale(Tenant $tenant, int $anno, int $annoPrec): array
    {
        $saldi = $this->saldiByConto($tenant->id, $anno);
        $saldiP = $this->saldiByConto($tenant->id, $annoPrec);

        // Conti SP del tenant
        $conti = ContoContabile::where('tenant_id', $tenant->id)
            ->whereIn('tipo_bilancio', [
                ContoContabile::TIPO_SP_ATTIVO,
                ContoContabile::TIPO_SP_PASSIVO,
                ContoContabile::TIPO_PN,
            ])
            ->where('movimentabile', true)
            ->orderBy('codice')
            ->get();

        // ATTIVO: gruppa per mastro (livello 2 via codice prefix)
        $attivo  = $this->raggruppaVoci($conti->where('tipo_bilancio', ContoContabile::TIPO_SP_ATTIVO),   $saldi, $saldiP, 'dare');
        $passivo = $this->raggruppaVoci($conti->whereIn('tipo_bilancio', [ContoContabile::TIPO_SP_PASSIVO, ContoContabile::TIPO_PN]), $saldi, $saldiP, 'avere');

        $totaleAttivo  = collect($attivo)->sum('saldo');
        $totalePassivo = collect($passivo)->sum('saldo');

        return [
            'anno'           => $anno,
            'anno_prec'      => $annoPrec,
            'attivo'         => $attivo,
            'passivo'        => $passivo,
            'totale_attivo'  => round($totaleAttivo,  2),
            'totale_passivo' => round($totalePassivo, 2),
            'differenza'     => round($totaleAttivo - $totalePassivo, 2),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Conto Economico
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Costruisce il Conto Economico CEE per l'anno dato.
     *
     * @return array{
     *   sezioni:             array,   // A) valore prod, B) costi prod, C) finanziari, D) rettifiche, E) imposte
     *   valore_produzione:   float,
     *   costi_produzione:    float,
     *   risultato_operativo: float,
     *   proventi_oneri_fin:  float,
     *   rettifiche:          float,
     *   risultato_ante_imp:  float,
     *   imposte:             float,
     *   risultato_esercizio: float,
     * }
     */
    public function contoEconomico(Tenant $tenant, int $anno, int $annoPrec): array
    {
        $saldi  = $this->saldiByConto($tenant->id, $anno);
        $saldiP = $this->saldiByConto($tenant->id, $annoPrec);

        $conti = ContoContabile::where('tenant_id', $tenant->id)
            ->whereIn('tipo_bilancio', [
                ContoContabile::TIPO_CE_VALORE_PRODUZIONE,
                ContoContabile::TIPO_CE_COSTI_PRODUZIONE,
                ContoContabile::TIPO_CE_PROVENTI_ONERI_FINANZIARI,
                ContoContabile::TIPO_CE_RETTIFICHE_FINANZIARIE,
                ContoContabile::TIPO_CE_IMPOSTE,
            ])
            ->where('movimentabile', true)
            ->orderBy('codice')
            ->get();

        // Sezioni CEE
        $sezioni = [
            'A' => [
                'label'   => 'A) Valore della produzione',
                'tipo'    => ContoContabile::TIPO_CE_VALORE_PRODUZIONE,
                'segno'   => 'avere',
                'voci'    => [],
                'totale'  => 0.0,
                'totale_prec' => 0.0,
            ],
            'B' => [
                'label'   => 'B) Costi della produzione',
                'tipo'    => ContoContabile::TIPO_CE_COSTI_PRODUZIONE,
                'segno'   => 'dare',
                'voci'    => [],
                'totale'  => 0.0,
                'totale_prec' => 0.0,
            ],
            'C' => [
                'label'   => 'C) Proventi e oneri finanziari',
                'tipo'    => ContoContabile::TIPO_CE_PROVENTI_ONERI_FINANZIARI,
                'segno'   => 'avere',  // netto (può essere negativo)
                'voci'    => [],
                'totale'  => 0.0,
                'totale_prec' => 0.0,
            ],
            'D' => [
                'label'   => 'D) Rettifiche di valore di attività finanziarie',
                'tipo'    => ContoContabile::TIPO_CE_RETTIFICHE_FINANZIARIE,
                'segno'   => 'avere',
                'voci'    => [],
                'totale'  => 0.0,
                'totale_prec' => 0.0,
            ],
            'E' => [
                'label'   => 'E) Imposte sul reddito',
                'tipo'    => ContoContabile::TIPO_CE_IMPOSTE,
                'segno'   => 'dare',
                'voci'    => [],
                'totale'  => 0.0,
                'totale_prec' => 0.0,
            ],
        ];

        foreach ($sezioni as $key => &$sez) {
            $contiFiltrati = $conti->where('tipo_bilancio', $sez['tipo']);
            $sez['voci']       = $this->raggruppaVoci($contiFiltrati, $saldi, $saldiP, $sez['segno']);
            $sez['totale']     = round(collect($sez['voci'])->sum('saldo'),      2);
            $sez['totale_prec'] = round(collect($sez['voci'])->sum('saldo_prec'), 2);
        }
        unset($sez);

        $vp  = $sezioni['A']['totale'];
        $cp  = $sezioni['B']['totale'];
        $fin = $sezioni['C']['totale'];
        $ret = $sezioni['D']['totale'];
        $imp = $sezioni['E']['totale'];

        $vpP  = $sezioni['A']['totale_prec'];
        $cpP  = $sezioni['B']['totale_prec'];
        $finP = $sezioni['C']['totale_prec'];
        $retP = $sezioni['D']['totale_prec'];
        $impP = $sezioni['E']['totale_prec'];

        $ris_op      = round($vp - $cp,              2);
        $ris_ante    = round($ris_op + $fin + $ret,  2);
        $ris_es      = round($ris_ante - $imp,       2);

        $ris_op_p    = round($vpP - $cpP,            2);
        $ris_ante_p  = round($ris_op_p + $finP + $retP, 2);
        $ris_es_p    = round($ris_ante_p - $impP,    2);

        return [
            'anno'                      => $anno,
            'anno_prec'                 => $annoPrec,
            'sezioni'                   => $sezioni,
            'valore_produzione'         => $vp,
            'costi_produzione'          => $cp,
            'risultato_operativo'       => $ris_op,
            'proventi_oneri_fin'        => $fin,
            'rettifiche'                => $ret,
            'risultato_ante_imposte'    => $ris_ante,
            'imposte'                   => $imp,
            'risultato_esercizio'       => $ris_es,
            // Anno precedente
            'valore_produzione_prec'    => $vpP,
            'costi_produzione_prec'     => $cpP,
            'risultato_operativo_prec'  => $ris_op_p,
            'risultato_esercizio_prec'  => $ris_es_p,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Rendiconto Gestionale ETS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Rendiconto Gestionale ETS per area di attività.
     *
     * Aggrega i movimenti per campo `gestione` (istituzionale / commerciale / null).
     * Produce entrate e uscite per area con subtotali e risultato per area.
     */
    public function rendicontoGestionale(Tenant $tenant, int $anno, int $annoPrec): array
    {
        $aree = [
            'istituzionale' => 'Attività istituzionale di interesse generale',
            'commerciale'   => 'Attività commerciale / accessoria',
            ''              => 'Gestione non classificata',
        ];

        $risultati = [];

        foreach ($aree as $gestione => $label) {
            $dati = $this->saldiByConto($tenant->id, $anno,     $gestione ?: null);
            $datiP = $this->saldiByConto($tenant->id, $annoPrec, $gestione ?: null);

            $entrate = DB::table('righe_movimento_contabile as r')
                ->join('movimenti_contabili as m', 'm.id', '=', 'r.movimento_id')
                ->join('conti_contabili as c', 'c.id', '=', 'r.conto_contabile_id')
                ->where('m.tenant_id', $tenant->id)
                ->where('m.anno_esercizio', $anno)
                ->where('m.stato', 'confermato')
                ->where('c.natura', 'ricavo')
                ->when($gestione !== '', fn ($q) => $q->where('r.gestione', $gestione))
                ->when($gestione === '', fn ($q) => $q->whereNull('r.gestione'))
                ->select(
                    'c.codice',
                    'c.descrizione',
                    DB::raw('SUM(r.importo_avere) - SUM(r.importo_dare) as saldo')
                )
                ->groupBy('c.id', 'c.codice', 'c.descrizione')
                ->having('saldo', '>', 0)
                ->orderBy('c.codice')
                ->get();

            $uscite = DB::table('righe_movimento_contabile as r')
                ->join('movimenti_contabili as m', 'm.id', '=', 'r.movimento_id')
                ->join('conti_contabili as c', 'c.id', '=', 'r.conto_contabile_id')
                ->where('m.tenant_id', $tenant->id)
                ->where('m.anno_esercizio', $anno)
                ->where('m.stato', 'confermato')
                ->where('c.natura', 'costo')
                ->when($gestione !== '', fn ($q) => $q->where('r.gestione', $gestione))
                ->when($gestione === '', fn ($q) => $q->whereNull('r.gestione'))
                ->select(
                    'c.codice',
                    'c.descrizione',
                    DB::raw('SUM(r.importo_dare) - SUM(r.importo_avere) as saldo')
                )
                ->groupBy('c.id', 'c.codice', 'c.descrizione')
                ->having('saldo', '>', 0)
                ->orderBy('c.codice')
                ->get();

            $totEntrate = round((float) $entrate->sum('saldo'), 2);
            $totUscite  = round((float) $uscite->sum('saldo'),  2);

            // Anno precedente totali
            $totEntrateP = (float) DB::table('righe_movimento_contabile as r')
                ->join('movimenti_contabili as m', 'm.id', '=', 'r.movimento_id')
                ->join('conti_contabili as c', 'c.id', '=', 'r.conto_contabile_id')
                ->where('m.tenant_id', $tenant->id)
                ->where('m.anno_esercizio', $annoPrec)
                ->where('m.stato', 'confermato')
                ->where('c.natura', 'ricavo')
                ->when($gestione !== '', fn ($q) => $q->where('r.gestione', $gestione))
                ->when($gestione === '', fn ($q) => $q->whereNull('r.gestione'))
                ->value(DB::raw('SUM(r.importo_avere) - SUM(r.importo_dare)'));

            $totUsciteP = (float) DB::table('righe_movimento_contabile as r')
                ->join('movimenti_contabili as m', 'm.id', '=', 'r.movimento_id')
                ->join('conti_contabili as c', 'c.id', '=', 'r.conto_contabile_id')
                ->where('m.tenant_id', $tenant->id)
                ->where('m.anno_esercizio', $annoPrec)
                ->where('m.stato', 'confermato')
                ->where('c.natura', 'costo')
                ->when($gestione !== '', fn ($q) => $q->where('r.gestione', $gestione))
                ->when($gestione === '', fn ($q) => $q->whereNull('r.gestione'))
                ->value(DB::raw('SUM(r.importo_dare) - SUM(r.importo_avere)'));

            $risultati[$gestione ?: 'non_classificata'] = [
                'label'       => $label,
                'gestione'    => $gestione ?: null,
                'entrate'     => $entrate,
                'uscite'      => $uscite,
                'tot_entrate' => $totEntrate,
                'tot_uscite'  => $totUscite,
                'risultato'   => round($totEntrate - $totUscite, 2),
                'tot_entrate_prec' => round((float) $totEntrateP, 2),
                'tot_uscite_prec'  => round((float) $totUsciteP,  2),
                'risultato_prec'   => round((float) $totEntrateP - (float) $totUsciteP, 2),
            ];
        }

        // Riepilogo generale
        $totEntrate = array_sum(array_column($risultati, 'tot_entrate'));
        $totUscite  = array_sum(array_column($risultati, 'tot_uscite'));

        return [
            'anno'           => $anno,
            'anno_prec'      => $annoPrec,
            'aree'           => $risultati,
            'tot_entrate'    => round($totEntrate, 2),
            'tot_uscite'     => round($totUscite, 2),
            'risultato_netto' => round($totEntrate - $totUscite, 2),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Calcola i saldi di tutti i conti movimentabili del tenant per l'anno.
     *
     * @return Collection keyed by conto_contabile_id
     */
    private function saldiByConto(int $tenantId, int $anno, ?string $gestione = null): Collection
    {
        $query = DB::table('righe_movimento_contabile as r')
            ->join('movimenti_contabili as m', 'm.id', '=', 'r.movimento_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.anno_esercizio', $anno)
            ->where('m.stato', 'confermato')
            ->select(
                'r.conto_contabile_id',
                DB::raw('SUM(r.importo_dare)  as tot_dare'),
                DB::raw('SUM(r.importo_avere) as tot_avere')
            )
            ->groupBy('r.conto_contabile_id');

        if ($gestione !== null) {
            $query->where('r.gestione', $gestione);
        }

        return $query->get()->keyBy('conto_contabile_id');
    }

    /**
     * Raggruppa i conti per mastro (secondo livello del codice) e calcola saldi.
     *
     * @param  Collection $conti       conti movimentabili (già filtrati per tipo)
     * @param  Collection $saldi       saldi anno corrente (keyed by conto_id)
     * @param  Collection $saldiPrec   saldi anno precedente
     * @param  string     $segno       'dare' | 'avere' — come calcolare il saldo positivo
     * @return array<int, array{mastro: string, voci: array, saldo: float, saldo_prec: float}>
     */
    private function raggruppaVoci(
        Collection $conti,
        Collection $saldi,
        Collection $saldiPrec,
        string $segno
    ): array {
        $gruppi = [];

        foreach ($conti as $conto) {
            // Estrae il mastro dal codice (es. "1.10.1001" → "1.10")
            $parts  = explode('.', $conto->codice);
            $mastro = count($parts) >= 2
                ? $parts[0] . '.' . $parts[1]
                : $parts[0];

            if (! isset($gruppi[$mastro])) {
                $gruppi[$mastro] = [
                    'mastro'    => $mastro,
                    'label'     => '', // filled below
                    'voci'      => [],
                    'saldo'     => 0.0,
                    'saldo_prec' => 0.0,
                ];
            }

            $s  = $saldi->get($conto->id);
            $sp = $saldiPrec->get($conto->id);

            $saldo = $this->calcolaSaldo($s,  $segno);
            $saldoP = $this->calcolaSaldo($sp, $segno);

            $gruppi[$mastro]['voci'][] = [
                'id'          => $conto->id,
                'codice'      => $conto->codice,
                'descrizione' => $conto->descrizione,
                'saldo'       => $saldo,
                'saldo_prec'  => $saldoP,
            ];

            $gruppi[$mastro]['saldo']      += $saldo;
            $gruppi[$mastro]['saldo_prec'] += $saldoP;

            // Label del mastro = prima voce che inizia per mastro prefix
            if (empty($gruppi[$mastro]['label'])) {
                $gruppi[$mastro]['label'] = $conto->descrizione;
            }
        }

        // Arrotonda e rimuove mastri con saldo zero (per entrambi gli anni)
        return array_values(array_filter(
            array_map(function ($g) {
                $g['saldo']      = round($g['saldo'],      2);
                $g['saldo_prec'] = round($g['saldo_prec'], 2);
                return $g;
            }, $gruppi),
            fn ($g) => $g['saldo'] != 0 || $g['saldo_prec'] != 0
        ));
    }

    private function calcolaSaldo(?object $row, string $segno): float
    {
        if (! $row) {
            return 0.0;
        }

        $dare  = (float) $row->tot_dare;
        $avere = (float) $row->tot_avere;

        return $segno === 'dare'
            ? round($dare  - $avere, 2)
            : round($avere - $dare,  2);
    }
}
