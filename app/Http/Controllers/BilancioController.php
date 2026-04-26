<?php

namespace App\Http\Controllers;

use App\Services\BilancioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bilancio CEE e Rendiconto Gestionale ETS.
 *
 * Produce:
 *  - Stato Patrimoniale (SP) secondo IV Direttiva CEE
 *  - Conto Economico (CE) secondo IV Direttiva CEE
 *  - Rendiconto Gestionale ETS per area di attività
 *
 * I dati vengono letti dai conti contabili già classificati con `tipo_bilancio`
 * e dai movimenti contabili confermati dell'anno richiesto.
 *
 * Middleware: role:admin,contabile (via route)
 */
class BilancioController extends Controller
{
    public function __construct(private readonly BilancioService $service) {}

    // ─────────────────────────────────────────────────────────────────────
    // Vista principale: tabs SP / CE / Rendiconto
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $tenant     = app('current_tenant');
        $anno       = $request->integer('anno', now()->year - 1);
        $annoPrec   = $anno - 1;
        $tab        = $request->get('tab', 'ce'); // 'sp' | 'ce' | 'rendiconto'

        $sp          = $this->service->statoPatrimoniale($tenant, $anno, $annoPrec);
        $ce          = $this->service->contoEconomico($tenant, $anno, $annoPrec);
        $rendiconto  = $this->service->rendicontoGestionale($tenant, $anno, $annoPrec);

        return Inertia::render('Bilancio/CEE/Index', [
            'sp'          => $sp,
            'ce'          => $ce,
            'rendiconto'  => $rendiconto,
            'anno'        => $anno,
            'annoPrec'    => $annoPrec,
            'tab'         => $tab,
            'anniRange'   => range(now()->year, now()->year - 5),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export PDF — Stato Patrimoniale
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdfSp(Request $request): HttpResponse
    {
        $tenant   = app('current_tenant');
        $anno     = $request->integer('anno', now()->year - 1);
        $annoPrec = $anno - 1;

        $sp = $this->service->statoPatrimoniale($tenant, $anno, $annoPrec);

        $pdf = Pdf::loadView('pdf.bilancio-sp', [
            'sp'     => $sp,
            'anno'   => $anno,
            'tenant' => $tenant,
        ])->setPaper('A4', 'portrait');

        return $pdf->download("StatoPatrimoniale_{$anno}.pdf");
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export PDF — Conto Economico
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdfCe(Request $request): HttpResponse
    {
        $tenant   = app('current_tenant');
        $anno     = $request->integer('anno', now()->year - 1);
        $annoPrec = $anno - 1;

        $ce = $this->service->contoEconomico($tenant, $anno, $annoPrec);

        $pdf = Pdf::loadView('pdf.bilancio-ce', [
            'ce'     => $ce,
            'anno'   => $anno,
            'tenant' => $tenant,
        ])->setPaper('A4', 'portrait');

        return $pdf->download("ContoEconomico_{$anno}.pdf");
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export PDF — Rendiconto Gestionale
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdfRendiconto(Request $request): HttpResponse
    {
        $tenant   = app('current_tenant');
        $anno     = $request->integer('anno', now()->year - 1);
        $annoPrec = $anno - 1;

        $rendiconto = $this->service->rendicontoGestionale($tenant, $anno, $annoPrec);

        $pdf = Pdf::loadView('pdf.bilancio-rendiconto', [
            'rendiconto' => $rendiconto,
            'anno'       => $anno,
            'tenant'     => $tenant,
        ])->setPaper('A4', 'portrait');

        return $pdf->download("RendicontoGestionale_{$anno}.pdf");
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export CSV — dati CEE completi
    // ─────────────────────────────────────────────────────────────────────

    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $tenant   = app('current_tenant');
        $anno     = $request->integer('anno', now()->year - 1);
        $annoPrec = $anno - 1;

        $sp         = $this->service->statoPatrimoniale($tenant, $anno, $annoPrec);
        $ce         = $this->service->contoEconomico($tenant, $anno, $annoPrec);

        $filename = "BilancioETS_{$anno}.csv";

        return response()->streamDownload(function () use ($sp, $ce, $anno, $annoPrec) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($out, ['BILANCIO ETS - FORMATO CEE', '', '', '']);
            fputcsv($out, ['Anno', $anno, 'Anno precedente', $annoPrec]);
            fputcsv($out, []);

            // SP Attivo
            fputcsv($out, ['STATO PATRIMONIALE - ATTIVO', '', $anno, $annoPrec]);
            foreach ($sp['attivo'] as $g) {
                fputcsv($out, [$g['mastro'], $g['label'], number_format($g['saldo'], 2, '.', ''), number_format($g['saldo_prec'], 2, '.', '')]);
                foreach ($g['voci'] as $v) {
                    fputcsv($out, ['  ' . $v['codice'], $v['descrizione'], number_format($v['saldo'], 2, '.', ''), number_format($v['saldo_prec'], 2, '.', '')]);
                }
            }
            fputcsv($out, ['TOTALE ATTIVO', '', number_format($sp['totale_attivo'], 2, '.', ''), '']);
            fputcsv($out, []);

            // SP Passivo
            fputcsv($out, ['STATO PATRIMONIALE - PASSIVO + PN', '', $anno, $annoPrec]);
            foreach ($sp['passivo'] as $g) {
                fputcsv($out, [$g['mastro'], $g['label'], number_format($g['saldo'], 2, '.', ''), number_format($g['saldo_prec'], 2, '.', '')]);
                foreach ($g['voci'] as $v) {
                    fputcsv($out, ['  ' . $v['codice'], $v['descrizione'], number_format($v['saldo'], 2, '.', ''), number_format($v['saldo_prec'], 2, '.', '')]);
                }
            }
            fputcsv($out, ['TOTALE PASSIVO + PN', '', number_format($sp['totale_passivo'], 2, '.', ''), '']);
            fputcsv($out, []);

            // CE
            fputcsv($out, ['CONTO ECONOMICO', '', $anno, $annoPrec]);
            foreach ($ce['sezioni'] as $key => $sez) {
                fputcsv($out, [$key . ')', $sez['label'], '', '']);
                foreach ($sez['voci'] as $g) {
                    foreach ($g['voci'] as $v) {
                        fputcsv($out, ['  ' . $v['codice'], $v['descrizione'], number_format($v['saldo'], 2, '.', ''), number_format($v['saldo_prec'], 2, '.', '')]);
                    }
                    fputcsv($out, ['', 'Subtotale ' . $g['mastro'], number_format($g['saldo'], 2, '.', ''), number_format($g['saldo_prec'], 2, '.', '')]);
                }
                fputcsv($out, ['', 'Totale ' . $sez['label'], number_format($sez['totale'], 2, '.', ''), number_format($sez['totale_prec'], 2, '.', '')]);
            }
            fputcsv($out, ['', 'RISULTATO DI ESERCIZIO', number_format($ce['risultato_esercizio'], 2, '.', ''), number_format($ce['risultato_esercizio_prec'], 2, '.', '')]);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
