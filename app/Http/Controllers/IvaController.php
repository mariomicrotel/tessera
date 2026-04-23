<?php

namespace App\Http\Controllers;

use App\Exceptions\IvaAlreadyClosedException;
use App\Exceptions\IvaPeriodoNonValidoException;
use App\Models\CodiceIva;
use App\Models\FatturaPassiva;
use App\Models\FatturaAttiva;
use App\Models\LiquidazioneIva;
use App\Services\IvaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller per la gestione del modulo IVA.
 */
class IvaController extends Controller
{
    /**
     * Dashboard IVA: riepilogo codici, fatture, liquidazioni.
     */
    public function dashboard()
    {
        $codiciCount = CodiceIva::attivi()->count();
        $fatturePassiveCount = FatturaPassiva::count();
        $fattureAttiveCount = FatturaAttiva::count();
        $liquidazioniCount = LiquidazioneIva::count();

        // Liquidazioni in bozza non chiuse
        $liquidazioniBozza = LiquidazioneIva::bozze()->get();

        // Ultimi incassi/pagamenti
        $ultimePassive = FatturaPassiva::latest('data_registrazione')->take(5)->get();
        $ultimeAttive = FatturaAttiva::latest('data_fattura')->take(5)->get();

        return Inertia::render('Iva/Dashboard', [
            'codiciCount'           => $codiciCount,
            'fatturePassiveCount'   => $fatturePassiveCount,
            'fattureAttiveCount'    => $fattureAttiveCount,
            'liquidazioniCount'     => $liquidazioniCount,
            'liquidazioniBozza'     => $liquidazioniBozza,
            'ultimePassive'         => $ultimePassive,
            'ultimeAttive'          => $ultimeAttive,
        ]);
    }

    /**
     * Lista codici IVA.
     */
    public function codiciIndex(Request $request)
    {
        $codici = CodiceIva::query()
            ->when($request->boolean('attivi'), fn ($q) => $q->attivi())
            ->orderBy('codice')
            ->paginate(20);

        return Inertia::render('Iva/Codici/Index', [
            'codici' => $codici,
            'filters' => $request->only('attivi'),
        ]);
    }

    /**
     * Dettaglio codice IVA.
     */
    public function codiceShow(CodiceIva $codiceIva)
    {
        $codiceIva->load(['righeFatturePassive', 'righeFattureAttive']);

        return Inertia::render('Iva/Codici/Show', [
            'codice' => $codiceIva,
        ]);
    }

    /**
     * Lista fatture passive.
     */
    public function fatturePassiveIndex(Request $request)
    {
        $query = FatturaPassiva::query()
            ->with('righe.codiceIva')
            ->when($request->string('numero'), fn ($q, $v) => $q->where('numero_fattura', 'like', "%$v%"))
            ->when($request->string('stato'), fn ($q, $v) => $q->where('stato_pagamento', $v))
            ->orderByDesc('data_registrazione');

        $fatture = $query->paginate(20);

        return Inertia::render('Iva/FatturePassive/Index', [
            'fatture' => $fatture,
            'filters' => $request->only('numero', 'stato'),
        ]);
    }

    /**
     * Dettaglio fattura passiva.
     */
    public function fatturaPassivaShow(FatturaPassiva $fatturaPassiva)
    {
        $fatturaPassiva->load('righe.codiceIva');

        return Inertia::render('Iva/FatturePassive/Show', [
            'fattura' => $fatturaPassiva,
        ]);
    }

    /**
     * Lista fatture attive.
     */
    public function fattureAttiveIndex(Request $request)
    {
        $query = FatturaAttiva::query()
            ->with('righe.codiceIva')
            ->when($request->string('numero'), fn ($q, $v) => $q->where('numero_fattura', 'like', "%$v%"))
            ->when($request->string('stato'), fn ($q, $v) => $q->where('stato', $v))
            ->orderByDesc('data_fattura');

        $fatture = $query->paginate(20);

        return Inertia::render('Iva/FattureAttive/Index', [
            'fatture' => $fatture,
            'filters' => $request->only('numero', 'stato'),
        ]);
    }

    /**
     * Dettaglio fattura attiva.
     */
    public function fatturaAttivaShow(FatturaAttiva $fatturaAttiva)
    {
        $fatturaAttiva->load('righe.codiceIva');

        return Inertia::render('Iva/FattureAttive/Show', [
            'fattura' => $fatturaAttiva,
        ]);
    }

    /**
     * Lista liquidazioni IVA.
     */
    public function liquidazioniIndex(Request $request)
    {
        $query = LiquidazioneIva::query()
            ->when($request->string('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('anno')
            ->orderByDesc('periodo');

        $liquidazioni = $query->paginate(20);

        return Inertia::render('Iva/Liquidazioni/Index', [
            'liquidazioni' => $liquidazioni,
            'filters' => $request->only('status'),
        ]);
    }

    /**
     * Dettaglio liquidazione IVA.
     */
    public function liquidazioneShow(LiquidazioneIva $liquidazioneIva)
    {
        $liquidazioneIva->load([
            'fatturePassive' => fn ($q) => $q->with('righe.codiceIva'),
            'fattureAttive' => fn ($q) => $q->with('righe.codiceIva'),
        ]);

        return Inertia::render('Iva/Liquidazioni/Show', [
            'liquidazione' => $liquidazioneIva,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Registro Acquisti
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Registro IVA acquisti: fatture passive filtrabili per anno/periodo.
     */
    public function registroAcquisti(Request $request): Response
    {
        $tenant      = app('current_tenant');
        $anno        = (int) $request->input('anno', now()->year);
        $periodo     = (int) $request->input('periodo', now()->month);
        $tipoPeriodo = $request->input('tipo_periodo', LiquidazioneIva::TIPO_MENSILE);

        $service    = app(IvaService::class);
        $dataInizio = null;
        $dataFine   = null;
        $errore     = null;

        try {
            [$dataInizio, $dataFine] = $service->rangeDate($anno, $periodo, $tipoPeriodo);
        } catch (IvaPeriodoNonValidoException $e) {
            $errore = $e->getMessage();
        }

        $baseQuery = fn () => FatturaPassiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->where('stato_pagamento', '!=', FatturaPassiva::STATO_ANNULLATA)
            ->when($dataInizio && $dataFine, fn ($q) => $q->whereBetween('data_registrazione', [$dataInizio, $dataFine]));

        $fatture = ($baseQuery)()
            ->with('righe.codiceIva')
            ->orderBy('data_registrazione')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $totImponibile = ($baseQuery)()->sum('imponibile_totale');
        $totIva        = ($baseQuery)()->sum('iva_totale');

        return Inertia::render('Iva/RegistroAcquisti', [
            'fatture'    => $fatture,
            'totali'     => [
                'imponibile' => round((float) $totImponibile, 2),
                'iva'        => round((float) $totIva, 2),
                'totale'     => round((float) $totImponibile + (float) $totIva, 2),
            ],
            'filters'    => compact('anno', 'periodo', 'tipoPeriodo'),
            'dataInizio' => $dataInizio,
            'dataFine'   => $dataFine,
            'errore'     => $errore,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Registro Vendite
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Registro IVA vendite: fatture attive filtrabili per anno/periodo.
     */
    public function registroVendite(Request $request): Response
    {
        $tenant      = app('current_tenant');
        $anno        = (int) $request->input('anno', now()->year);
        $periodo     = (int) $request->input('periodo', now()->month);
        $tipoPeriodo = $request->input('tipo_periodo', LiquidazioneIva::TIPO_MENSILE);

        $service    = app(IvaService::class);
        $dataInizio = null;
        $dataFine   = null;
        $errore     = null;

        try {
            [$dataInizio, $dataFine] = $service->rangeDate($anno, $periodo, $tipoPeriodo);
        } catch (IvaPeriodoNonValidoException $e) {
            $errore = $e->getMessage();
        }

        $baseQuery = fn () => FatturaAttiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->whereNotIn('stato', [FatturaAttiva::STATO_ANNULLATA, FatturaAttiva::STATO_BOZZA])
            ->when($dataInizio && $dataFine, fn ($q) => $q->whereBetween('data_fattura', [$dataInizio, $dataFine]));

        $fatture = ($baseQuery)()
            ->with('righe.codiceIva')
            ->orderBy('data_fattura')
            ->orderBy('progressivo')
            ->paginate(25)
            ->withQueryString();

        $totImponibile = ($baseQuery)()->sum('imponibile_totale');
        $totIva        = ($baseQuery)()->sum('iva_totale');

        return Inertia::render('Iva/RegistroVendite', [
            'fatture'    => $fatture,
            'totali'     => [
                'imponibile' => round((float) $totImponibile, 2),
                'iva'        => round((float) $totIva, 2),
                'totale'     => round((float) $totImponibile + (float) $totIva, 2),
            ],
            'filters'    => compact('anno', 'periodo', 'tipoPeriodo'),
            'dataInizio' => $dataInizio,
            'dataFine'   => $dataFine,
            'errore'     => $errore,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Liquidazione periodica
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Pagina liquidazione IVA: riepilogo saldi + storico + bottone chiudi.
     */
    public function liquidazione(Request $request): Response
    {
        $tenant      = app('current_tenant');
        $anno        = (int) $request->input('anno', now()->year);
        $periodo     = (int) $request->input('periodo', now()->month);
        $tipoPeriodo = $request->input('tipo_periodo', LiquidazioneIva::TIPO_MENSILE);

        $service = app(IvaService::class);
        $saldi   = null;
        $errore  = null;

        try {
            $saldi = $service->calcolaLiquidazione($tenant->id, $anno, $periodo, $tipoPeriodo);
        } catch (IvaPeriodoNonValidoException $e) {
            $errore = $e->getMessage();
        }

        // Liquidazione del periodo corrente (già chiusa o bozza)
        $liquidazioneCorrente = LiquidazioneIva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('anno', $anno)
            ->where('periodo', $periodo)
            ->where('tipo_periodo', $tipoPeriodo)
            ->first();

        // Storico liquidazioni definitiva/versata
        $storico = LiquidazioneIva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [LiquidazioneIva::STATUS_DEFINITIVA, LiquidazioneIva::STATUS_VERSATA])
            ->orderByDesc('anno')
            ->orderByDesc('periodo')
            ->get();

        return Inertia::render('Iva/Liquidazione', [
            'saldi'                => $saldi,
            'storico'              => $storico,
            'liquidazioneCorrente' => $liquidazioneCorrente,
            'filters'              => compact('anno', 'periodo', 'tipoPeriodo'),
            'errore'               => $errore,
        ]);
    }

    /**
     * Chiude definitivamente la liquidazione IVA del periodo selezionato.
     */
    public function chiudiLiquidazione(Request $request): RedirectResponse
    {
        $request->validate([
            'anno'        => 'required|integer|min:2000|max:2100',
            'periodo'     => 'required|integer|min:1|max:12',
            'tipo_periodo' => ['required', 'in:mensile,trimestrale'],
        ]);

        $tenant  = app('current_tenant');
        $service = app(IvaService::class);

        try {
            $liquidazione = $service->chiudiLiquidazione(
                $tenant->id,
                (int) $request->anno,
                (int) $request->periodo,
                $request->tipo_periodo,
            );

            return redirect()->route('iva.liquidazione', [
                'tenant'       => $tenant->slug,
                'anno'         => $request->anno,
                'periodo'      => $request->periodo,
                'tipo_periodo' => $request->tipo_periodo,
            ])->with('flash', [
                'type'    => 'success',
                'message' => "Liquidazione IVA {$liquidazione->periodo_label} {$liquidazione->anno} chiusa con successo.",
            ]);
        } catch (IvaAlreadyClosedException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        } catch (IvaPeriodoNonValidoException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
