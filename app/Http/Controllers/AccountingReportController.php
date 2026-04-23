<?php

namespace App\Http\Controllers;

use App\Models\CooperativeShare;
use App\Models\PrestitoSocialeLibretto;
use App\Models\PrimaNotaEntry;
use App\Models\Ristorno;
use App\Services\RendicontoCassaSchemaCooperativa;
use App\Services\RendicontoCassaSchemaResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report contabilità: prima nota per periodo, totali. Export CSV semplice.
 */
class AccountingReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,contabile');
    }

    public function index(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));

        $entries = PrimaNotaEntry::query()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $totalEntrate = $entries->where('amount', '>', 0)->sum('amount');
        $totalUscite = abs($entries->where('amount', '<', 0)->sum('amount'));

        return Inertia::render('Reports/Accounting', [
            'entries' => $entries,
            'totalEntrate' => $totalEntrate,
            'totalUscite' => $totalUscite,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Conto Economico Semplificato
    // ──────────────────────────────────────────────────────────────────────

    public function contoEconomico(Request $request)
    {
        $anno          = $request->filled('anno')          ? (int) $request->anno          : now()->year;
        $confrontaAnno = $request->filled('confronta_anno') ? (int) $request->confronta_anno : $anno - 1;
        $gestione      = $request->get('gestione'); // null | 'istituzionale' | 'commerciale'

        [$entrate, $uscite, $totals] = $this->buildContoEconomicoData($anno, $confrontaAnno, $gestione);

        $anniDisponibili = range(now()->year, max(now()->year - 9, 2020));

        return Inertia::render('Reports/ContoEconomico', [
            'entrate'               => $entrate,
            'uscite'                => $uscite,
            'totaleEntrate'         => $totals['tot_entrate'],
            'totaleUscite'          => $totals['tot_uscite'],
            'totaleEntrateConfronta' => $totals['tot_entrate_confronta'],
            'totaleUsciteConfronta'  => $totals['tot_uscite_confronta'],
            'avanzo'                => $totals['avanzo'],
            'avanzoConfronta'       => $totals['avanzo_confronta'],
            'anno'                  => $anno,
            'confrontaAnno'         => $confrontaAnno,
            'anniDisponibili'       => $anniDisponibili,
            'gestione'              => $gestione,
            'filters'               => $request->only('anno', 'confronta_anno', 'gestione'),
        ]);
    }

    public function exportContoEconomico(Request $request): StreamedResponse
    {
        $anno          = $request->filled('anno')          ? (int) $request->anno          : now()->year;
        $confrontaAnno = $request->filled('confronta_anno') ? (int) $request->confronta_anno : $anno - 1;
        $gestione      = $request->get('gestione');

        [$entrate, $uscite, $totals] = $this->buildContoEconomicoData($anno, $confrontaAnno, $gestione);

        $filename = 'conto_economico_' . $anno . '_vs_' . $confrontaAnno . '.csv';

        return response()->streamDownload(function () use ($entrate, $uscite, $totals, $anno, $confrontaAnno) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
            fputcsv($out, ['Sezione', 'Macro area', 'Codice', 'Voce', $anno, $confrontaAnno, 'Variazione %']);

            foreach ($entrate as $row) {
                fputcsv($out, [
                    'ENTRATE',
                    $row['macro_name'],
                    $row['ministerial_code'],
                    $row['label'],
                    number_format($row['anno'], 2, '.', ''),
                    number_format($row['confronta_anno'], 2, '.', ''),
                    $row['variazione'] !== null ? $row['variazione'] . '%' : 'n/a',
                ]);
            }
            fputcsv($out, ['TOTALE ENTRATE', '', '', '', $totals['tot_entrate'], $totals['tot_entrate_confronta'], '']);

            foreach ($uscite as $row) {
                fputcsv($out, [
                    'USCITE',
                    $row['macro_name'],
                    $row['ministerial_code'],
                    $row['label'],
                    number_format($row['anno'], 2, '.', ''),
                    number_format($row['confronta_anno'], 2, '.', ''),
                    $row['variazione'] !== null ? $row['variazione'] . '%' : 'n/a',
                ]);
            }
            fputcsv($out, ['TOTALE USCITE', '', '', '', $totals['tot_uscite'], $totals['tot_uscite_confronta'], '']);

            fputcsv($out, ['AVANZO/DISAVANZO', '', '', '', $totals['avanzo'], $totals['avanzo_confronta'], '']);
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Costruisce i dati del Conto Economico per i due anni.
     * Restituisce [$entrate, $uscite, $totals].
     */
    private function buildContoEconomicoData(int $anno, int $confrontaAnno, ?string $gestione): array
    {
        $baseAnno      = PrimaNotaEntry::query()->whereYear('date', $anno);
        $baseConfronta = PrimaNotaEntry::query()->whereYear('date', $confrontaAnno);

        if ($gestione && in_array($gestione, ['istituzionale', 'commerciale'], true)) {
            $baseAnno->where('gestione', $gestione);
            $baseConfronta->where('gestione', $gestione);
        }

        $totalsAnno      = (clone $baseAnno)
            ->select('rendiconto_code', DB::raw('SUM(amount) as totale'))
            ->groupBy('rendiconto_code')
            ->pluck('totale', 'rendiconto_code');

        $totalsConfronta = (clone $baseConfronta)
            ->select('rendiconto_code', DB::raw('SUM(amount) as totale'))
            ->groupBy('rendiconto_code')
            ->pluck('totale', 'rendiconto_code');

        $entrate = [];
        $uscite  = [];
        $totE    = 0.0;
        $totU    = 0.0;
        $totEC   = 0.0;
        $totUC   = 0.0;

        $schema = RendicontoCassaSchemaResolver::class();
        foreach ($schema::getSelectableVoices() as $voce) {
            $code = $voce['code'];
            $tipo = $voce['tipo']; // 'entrata' | 'uscita'
            $info = $schema::getInfoByCode($code);

            $rawAnno      = (float) ($totalsAnno[$code]      ?? 0);
            $rawConfronta = (float) ($totalsConfronta[$code] ?? 0);

            if ($tipo === 'entrata') {
                $valAnno      = max(0.0, $rawAnno);
                $valConfronta = max(0.0, $rawConfronta);
            } else {
                $valAnno      = abs(min(0.0, $rawAnno));
                $valConfronta = abs(min(0.0, $rawConfronta));
            }

            $variazione = null;
            if ($valConfronta != 0) {
                $variazione = round((($valAnno - $valConfronta) / $valConfronta) * 100, 1);
            }

            $item = [
                'code'             => $code,
                'ministerial_code' => $info['ministerial_code'] ?? $code,
                'label'            => $info['name']             ?? $code,
                'macro_name'       => $voce['macro_name']       ?? '',
                'anno'             => round($valAnno,      2),
                'confronta_anno'   => round($valConfronta, 2),
                'variazione'       => $variazione,
            ];

            if ($tipo === 'entrata') {
                $entrate[] = $item;
                $totE  += $valAnno;
                $totEC += $valConfronta;
            } else {
                $uscite[] = $item;
                $totU  += $valAnno;
                $totUC += $valConfronta;
            }
        }

        $avanzo          = round($totE  - $totU,  2);
        $avanzoConfronta = round($totEC - $totUC, 2);

        $totals = [
            'tot_entrate'          => round($totE,  2),
            'tot_uscite'           => round($totU,  2),
            'tot_entrate_confronta' => round($totEC, 2),
            'tot_uscite_confronta'  => round($totUC, 2),
            'avanzo'               => $avanzo,
            'avanzo_confronta'     => $avanzoConfronta,
        ];

        return [$entrate, $uscite, $totals];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Conto Economico Cooperativa
    // ──────────────────────────────────────────────────────────────────────

    public function contoEconomicoCooperativa(Request $request)
    {
        $anno          = $request->filled('anno')           ? (int) $request->anno           : now()->year;
        $confrontaAnno = $request->filled('confronta_anno') ? (int) $request->confronta_anno : $anno - 1;
        $gestione      = $request->get('gestione');

        [$entrate, $uscite, $totals] = $this->buildContoEconomicoData($anno, $confrontaAnno, $gestione);

        // ── Dati aggiuntivi cooperativa ────────────────────────────────────
        $capitaleVersatoTotale = (float) CooperativeShare::sum('totale_versato');

        $ristorniAnno = Ristorno::with('entries')
            ->where('anno', $anno)
            ->whereIn('status', [Ristorno::STATUS_DELIBERATO, Ristorno::STATUS_PAGATO])
            ->get()
            ->map(fn ($r) => [
                'id'                       => $r->id,
                'anno'                     => $r->anno,
                'status'                   => $r->status,
                'importo_totale_deliberato' => (float) $r->importo_totale_deliberato,
                'aliquota_ritenuta'        => (float) $r->aliquota_ritenuta,
                'data_delibera_assemblea'  => $r->data_delibera_assemblea?->format('Y-m-d'),
                'data_pagamento'           => $r->data_pagamento?->format('Y-m-d'),
                'totale_lordo'             => round((float) $r->entries->sum('importo_lordo'), 2),
                'totale_ritenuta'          => round((float) $r->entries->sum('importo_ritenuta'), 2),
                'totale_netto'             => round((float) $r->entries->sum('importo_netto'), 2),
            ]);

        $totalePrestitoSociale = (float) PrestitoSocialeLibretto::attivi()->sum('saldo_attuale');

        $anniDisponibili = range(now()->year, max(now()->year - 9, 2020));

        return Inertia::render('Reports/ContoEconomicoCooperativa', [
            'entrate'                   => $entrate,
            'uscite'                    => $uscite,
            'totaleEntrate'             => $totals['tot_entrate'],
            'totaleUscite'              => $totals['tot_uscite'],
            'totaleEntrateConfronta'    => $totals['tot_entrate_confronta'],
            'totaleUsciteConfronta'     => $totals['tot_uscite_confronta'],
            'avanzo'                    => $totals['avanzo'],
            'avanzoConfronta'           => $totals['avanzo_confronta'],
            'anno'                      => $anno,
            'confrontaAnno'             => $confrontaAnno,
            'anniDisponibili'           => $anniDisponibili,
            'gestione'                  => $gestione,
            'filters'                   => $request->only('anno', 'confronta_anno', 'gestione'),
            'capitale_versato_totale'   => $capitaleVersatoTotale,
            'ristorni_anno'             => $ristorniAnno,
            'totale_prestito_sociale'   => $totalePrestitoSociale,
        ]);
    }

    public function exportContoEconomicoCooperativa(Request $request): StreamedResponse
    {
        $anno          = $request->filled('anno')           ? (int) $request->anno           : now()->year;
        $confrontaAnno = $request->filled('confronta_anno') ? (int) $request->confronta_anno : $anno - 1;
        $gestione      = $request->get('gestione');

        [$entrate, $uscite, $totals] = $this->buildContoEconomicoData($anno, $confrontaAnno, $gestione);

        $filename = 'conto_economico_coop_' . $anno . '_vs_' . $confrontaAnno . '.csv';

        return response()->streamDownload(function () use ($entrate, $uscite, $totals, $anno, $confrontaAnno) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Sezione', 'Macro area', 'Codice', 'Voce', $anno, $confrontaAnno, 'Variazione %'], ';');

            foreach ($entrate as $row) {
                fputcsv($out, [
                    'ENTRATE',
                    $row['macro_name'],
                    $row['ministerial_code'],
                    $row['label'],
                    number_format($row['anno'], 2, ',', '.'),
                    number_format($row['confronta_anno'], 2, ',', '.'),
                    $row['variazione'] !== null ? $row['variazione'] . '%' : 'n/a',
                ], ';');
            }
            fputcsv($out, ['TOTALE ENTRATE', '', '', '',
                number_format($totals['tot_entrate'], 2, ',', '.'),
                number_format($totals['tot_entrate_confronta'], 2, ',', '.'),
                '',
            ], ';');

            foreach ($uscite as $row) {
                fputcsv($out, [
                    'USCITE',
                    $row['macro_name'],
                    $row['ministerial_code'],
                    $row['label'],
                    number_format($row['anno'], 2, ',', '.'),
                    number_format($row['confronta_anno'], 2, ',', '.'),
                    $row['variazione'] !== null ? $row['variazione'] . '%' : 'n/a',
                ], ';');
            }
            fputcsv($out, ['TOTALE USCITE', '', '', '',
                number_format($totals['tot_uscite'], 2, ',', '.'),
                number_format($totals['tot_uscite_confronta'], 2, ',', '.'),
                '',
            ], ';');

            fputcsv($out, ['AVANZO/DISAVANZO', '', '', '',
                number_format($totals['avanzo'], 2, ',', '.'),
                number_format($totals['avanzo_confronta'], 2, ',', '.'),
                '',
            ], ';');

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Situazione Capitale (solo cooperative)
    // ──────────────────────────────────────────────────────────────────────

    public function situazioneCapitale(Request $request)
    {
        $anno = $request->filled('anno') ? (int) $request->anno : now()->year;

        // ── Capitale sociale ──────────────────────────────────────────────
        $capitaleSottoscritto = (float) CooperativeShare::sum('totale_sottoscritto');
        $capitaleVersato      = (float) CooperativeShare::sum('totale_versato');
        $capitaleDaVersare    = max(0.0, round($capitaleSottoscritto - $capitaleVersato, 2));

        $sociPerStatus = CooperativeShare::select('status', DB::raw('COUNT(*) as conteggio'))
            ->groupBy('status')
            ->pluck('conteggio', 'status')
            ->toArray();

        $totSoci = array_sum($sociPerStatus);
        $valoreMedioPerSocio = $totSoci > 0 ? round($capitaleVersato / $totSoci, 2) : 0.0;

        // ── Storico capitale per anno ──────────────────────────────────────
        // Raggruppa sottoscrizioni per anno (data_sottoscrizione)
        $storicoCapitale = CooperativeShare::selectRaw(
            'YEAR(data_sottoscrizione) as anno_sottoscrizione,
             SUM(totale_sottoscritto) as sottoscritto,
             SUM(totale_versato) as versato,
             COUNT(*) as numero_quote'
        )
            ->whereNotNull('data_sottoscrizione')
            ->groupByRaw('YEAR(data_sottoscrizione)')
            ->orderBy('anno_sottoscrizione')
            ->get()
            ->map(fn ($r) => [
                'anno'            => (int) $r->anno_sottoscrizione,
                'sottoscritto'    => round((float) $r->sottoscritto, 2),
                'versato'         => round((float) $r->versato, 2),
                'numero_quote'    => (int) $r->numero_quote,
            ]);

        // ── Prestito sociale ──────────────────────────────────────────────
        $totalePrestitoSociale = (float) PrestitoSocialeLibretto::attivi()->sum('saldo_attuale');
        $numLibretti           = PrestitoSocialeLibretto::attivi()->count();

        // Interessi accreditati nell'anno selezionato (movimenti tipo interessi)
        $interessiAnno = (float) DB::table('prestito_sociale_movimenti')
            ->where('tipo', 'interessi')
            ->where('segno', 'avere')
            ->where('anno_competenza', $anno)
            ->whereIn('libretto_id', PrestitoSocialeLibretto::attivi()->pluck('id'))
            ->sum('importo');

        // ── Riserve (prima nota B08/B09) ──────────────────────────────────
        $riservaLegale = (float) abs(
            PrimaNotaEntry::where('rendiconto_code', RendicontoCassaSchemaCooperativa::CODE_RISERVA_LEGALE)
                ->sum('amount')
        );
        $riservaIndivisibile = (float) abs(
            PrimaNotaEntry::where('rendiconto_code', RendicontoCassaSchemaCooperativa::CODE_RISERVA_INDIVISIBILE)
                ->sum('amount')
        );
        $riservaLegaleAnno = (float) abs(
            PrimaNotaEntry::where('rendiconto_code', RendicontoCassaSchemaCooperativa::CODE_RISERVA_LEGALE)
                ->whereYear('date', $anno)
                ->sum('amount')
        );
        $riservaIndivisibileAnno = (float) abs(
            PrimaNotaEntry::where('rendiconto_code', RendicontoCassaSchemaCooperativa::CODE_RISERVA_INDIVISIBILE)
                ->whereYear('date', $anno)
                ->sum('amount')
        );

        $riserveAccantonate = [
            'legale'           => round($riservaLegale, 2),
            'indivisibile'     => round($riservaIndivisibile, 2),
            'totale'           => round($riservaLegale + $riservaIndivisibile, 2),
            'legale_anno'      => round($riservaLegaleAnno, 2),
            'indivisibile_anno' => round($riservaIndivisibileAnno, 2),
            'totale_anno'      => round($riservaLegaleAnno + $riservaIndivisibileAnno, 2),
        ];

        // ── Ristorni dell'anno ─────────────────────────────────────────────
        $ristorniAnno = Ristorno::where('anno', $anno)
            ->whereIn('status', [Ristorno::STATUS_DELIBERATO, Ristorno::STATUS_PAGATO])
            ->get(['id', 'anno', 'importo_totale_deliberato', 'aliquota_ritenuta', 'status', 'data_delibera_assemblea', 'data_pagamento']);

        $anniDisponibili = range(now()->year, max(now()->year - 9, 2020));

        return Inertia::render('Reports/SituazioneCapitale', [
            'anno'                      => $anno,
            'anniDisponibili'           => $anniDisponibili,
            'capitale_sottoscritto_totale' => round($capitaleSottoscritto, 2),
            'capitale_versato_totale'   => round($capitaleVersato, 2),
            'capitale_da_versare'       => $capitaleDaVersare,
            'soci_per_status'           => $sociPerStatus,
            'totale_soci'               => $totSoci,
            'valore_medio_per_socio'    => $valoreMedioPerSocio,
            'storico_capitale'          => $storicoCapitale,
            'totale_prestito_sociale'   => round($totalePrestitoSociale, 2),
            'num_libretti_attivi'       => $numLibretti,
            'interessi_anno'            => round($interessiAnno, 2),
            'riserve_accantonate'       => $riserveAccantonate,
            'ristorni_anno'             => $ristorniAnno,
            'filters'                   => $request->only('anno'),
        ]);
    }

    public function exportSituazioneCapitale(Request $request): StreamedResponse
    {
        // Riesegue la logica di situazioneCapitale per popolare i dati CSV
        $anno = $request->filled('anno') ? (int) $request->anno : now()->year;

        $capitaleSottoscritto = (float) CooperativeShare::sum('totale_sottoscritto');
        $capitaleVersato      = (float) CooperativeShare::sum('totale_versato');

        $shares = CooperativeShare::with('member')->get();

        $filename = 'situazione_capitale_' . $anno . '_' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($shares, $capitaleSottoscritto, $capitaleVersato, $anno) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Socio', 'C.F.', 'Tipo', 'Quote', 'Valore Unitario', 'Sottoscritto', 'Versato', 'Da Versare', 'Status'], ';');

            foreach ($shares as $share) {
                $m = $share->member;
                $nome = $m
                    ? ($m->ragione_sociale ?: trim(($m->cognome ?? '') . ' ' . ($m->nome ?? '')))
                    : 'N/D';
                fputcsv($out, [
                    $nome,
                    $m?->codice_fiscale ?? '',
                    $m?->memberType?->display_name ?? '',
                    $share->numero_quote,
                    number_format((float) $share->valore_unitario, 2, ',', '.'),
                    number_format((float) $share->totale_sottoscritto, 2, ',', '.'),
                    number_format((float) $share->totale_versato, 2, ',', '.'),
                    number_format(max(0, (float) $share->totale_sottoscritto - (float) $share->totale_versato), 2, ',', '.'),
                    $share->status,
                ], ';');
            }

            fputcsv($out, [], ';');
            fputcsv($out, ['TOTALE SOTTOSCRITTO', '', '', '', '', number_format($capitaleSottoscritto, 2, ',', '.'), '', '', ''], ';');
            fputcsv($out, ['TOTALE VERSATO',      '', '', '', '', '', number_format($capitaleVersato, 2, ',', '.'),      '', ''], ';');
            fputcsv($out, ['DA VERSARE',           '', '', '', '', '', '', number_format(max(0, $capitaleSottoscritto - $capitaleVersato), 2, ',', '.'), ''], ';');

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Prima nota – export CSV per periodo
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Export CSV prima nota per periodo.
     */
    public function export(Request $request): StreamedResponse
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));

        $entries = PrimaNotaEntry::query()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->get();

        $filename = 'prima_nota_' . $from . '_' . $to . '.csv';

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Data', 'Conto', 'Descrizione', 'Importo']);
            foreach ($entries as $e) {
                fputcsv($out, [
                    $e->date->format('d/m/Y'),
                    $e->rendiconto_label,
                    $e->description ?? '',
                    $e->amount,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
