<?php

namespace App\Http\Controllers;

use App\Exceptions\IvaAlreadyClosedException;
use App\Exceptions\IvaPeriodoNonValidoException;
use App\Models\CodiceIva;
use App\Models\FatturaPassiva;
use App\Models\FatturaAttiva;
use App\Models\LiquidazioneIva;
use App\Services\AccontoIvaService;
use App\Models\Settings;
use App\Services\IvaService;
use App\Services\LipeXmlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $tipoPeriodo = $request->input('tipo_periodo', Settings::get('periodicita_liquidazione_iva', LiquidazioneIva::TIPO_MENSILE));

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
        $tipoPeriodo = $request->input('tipo_periodo', Settings::get('periodicita_liquidazione_iva', LiquidazioneIva::TIPO_MENSILE));

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
        $tipoPeriodo = $request->input('tipo_periodo', Settings::get('periodicita_liquidazione_iva', LiquidazioneIva::TIPO_MENSILE));

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
            'tipo_periodo' => ['required', 'in:mensile,trimestrale,annuale'],
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

    // ─────────────────────────────────────────────────────────────────────────
    // Fatture Passive - CRUD
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Form creazione nuova fattura passiva.
     */
    public function fatturePassiveCreate(): Response
    {
        $tenant = app('current_tenant');

        return Inertia::render('Iva/FatturePassive/Create', [
            'suppliers'       => \App\Models\Supplier::orderBy('ragione_sociale')->get(),
            'codiciIva'       => CodiceIva::attivi()->orderBy('percentuale')->get(),
            'conti'           => \App\Models\Conto::where('tenant_id', $tenant->id)->orderBy('numero')->get(),
            'tipiDocumento'   => FatturaPassiva::TIPI_DOCUMENTO,
            'esigibilitaOpts' => FatturaPassiva::ESIGIBILITA_LABEL,
        ]);
    }

    /**
     * Salva nuova fattura passiva con le righe.
     */
    public function fatturePassiveStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id'         => 'required|integer|exists:suppliers,id',
            'numero_fattura'      => 'required|string|max:50',
            'data_fattura'        => 'required|date',
            'data_ricezione'      => 'required|date',
            'data_registrazione'  => 'required|date',
            'data_scadenza'       => 'required|date',
            'esigibilita'         => ['required', 'in:immediata,differita,split_payment'],
            'tipo_documento'      => ['required', 'in:' . implode(',', array_keys(FatturaPassiva::TIPI_DOCUMENTO))],
            'note'                => 'nullable|string',
            'righe'               => 'required|array|min:1',
            'righe.*.codice_iva_id'            => 'required|integer|exists:codici_iva,id',
            'righe.*.conto_id'                 => 'nullable|integer|exists:conti,id',
            'righe.*.descrizione'              => 'required|string',
            'righe.*.quantita'                 => 'required|numeric|min:0.01',
            'righe.*.prezzo_unitario'          => 'required|numeric|min:0',
            'righe.*.indetraibile_percentuale' => 'nullable|numeric|min:0|max:100',
        ]);

        $tenant = app('current_tenant');

        // Crea fattura
        $fattura = FatturaPassiva::create([
            'tenant_id'           => $tenant->id,
            'supplier_id'         => $validated['supplier_id'],
            'numero_fattura'      => $validated['numero_fattura'],
            'data_fattura'        => $validated['data_fattura'],
            'data_ricezione'      => $validated['data_ricezione'],
            'data_registrazione'  => $validated['data_registrazione'],
            'data_scadenza'       => $validated['data_scadenza'],
            'esigibilita'         => $validated['esigibilita'],
            'tipo_documento'      => $validated['tipo_documento'],
            'note'                => $validated['note'],
            'stato_pagamento'     => FatturaPassiva::STATO_DA_PAGARE,
        ]);

        // Crea righe
        foreach ($validated['righe'] as $rigaData) {
            $qta = (float) $rigaData['quantita'];
            $prezzo = (float) $rigaData['prezzo_unitario'];
            $imponibile = round($qta * $prezzo, 2);

            $codiceIva = CodiceIva::find($rigaData['codice_iva_id']);
            $aliquota = $codiceIva ? (float) $codiceIva->percentuale : 0;
            $iva = round($imponibile * $aliquota / 100, 2);

            $indPct = (float) ($rigaData['indetraibile_percentuale'] ?? $codiceIva?->indetraibile_percentuale ?? 0);
            $ivaInd = round($iva * $indPct / 100, 2);

            $fattura->righe()->create([
                'tenant_id'                => $tenant->id,
                'codice_iva_id'            => $rigaData['codice_iva_id'],
                'conto_id'                 => $rigaData['conto_id'],
                'descrizione'              => $rigaData['descrizione'],
                'quantita'                 => $qta,
                'prezzo_unitario'          => $prezzo,
                'imponibile'               => $imponibile,
                'iva'                      => $iva,
                'totale'                   => round($imponibile + $iva, 2),
                'indetraibile_percentuale' => $indPct,
                'iva_indetraibile'         => $ivaInd,
            ]);
        }

        // Ricalcola totali
        $fattura->ricalcolaTotali();
        $fattura->save();

        return redirect()->route('iva.fatture-passive.show', $fattura)->with('flash', [
            'type'    => 'success',
            'message' => "Fattura {$fattura->numero_fattura} creata con successo.",
        ]);
    }

    /**
     * Form modifica fattura passiva.
     */
    public function fatturePassiveEdit(FatturaPassiva $fatturaPassiva): Response
    {
        $tenant = app('current_tenant');
        $fatturaPassiva->load('righe.codiceIva');

        return Inertia::render('Iva/FatturePassive/Edit', [
            'fattura'         => $fatturaPassiva,
            'suppliers'       => \App\Models\Supplier::orderBy('ragione_sociale')->get(),
            'codiciIva'       => CodiceIva::attivi()->orderBy('percentuale')->get(),
            'conti'           => \App\Models\Conto::where('tenant_id', $tenant->id)->orderBy('numero')->get(),
            'tipiDocumento'   => FatturaPassiva::TIPI_DOCUMENTO,
            'esigibilitaOpts' => FatturaPassiva::ESIGIBILITA_LABEL,
        ]);
    }

    /**
     * Aggiorna fattura passiva.
     */
    public function fatturePassiveUpdate(Request $request, FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        // Blocca modifica se fattura è read-only (agganciata a liquidazione definitiva)
        if ($fatturaPassiva->isReadOnly()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Impossibile modificare: fattura agganciata a liquidazione IVA definitiva.',
            ]);
        }

        $validated = $request->validate([
            'supplier_id'         => 'required|integer|exists:suppliers,id',
            'numero_fattura'      => 'required|string|max:50',
            'data_fattura'        => 'required|date',
            'data_ricezione'      => 'required|date',
            'data_registrazione'  => 'required|date',
            'data_scadenza'       => 'required|date',
            'esigibilita'         => ['required', 'in:immediata,differita,split_payment'],
            'tipo_documento'      => ['required', 'in:' . implode(',', array_keys(FatturaPassiva::TIPI_DOCUMENTO))],
            'note'                => 'nullable|string',
            'righe'               => 'required|array|min:1',
            'righe.*.codice_iva_id'            => 'required|integer|exists:codici_iva,id',
            'righe.*.conto_id'                 => 'nullable|integer|exists:conti,id',
            'righe.*.descrizione'              => 'required|string',
            'righe.*.quantita'                 => 'required|numeric|min:0.01',
            'righe.*.prezzo_unitario'          => 'required|numeric|min:0',
            'righe.*.indetraibile_percentuale' => 'nullable|numeric|min:0|max:100',
        ]);

        $tenant = app('current_tenant');

        // Aggiorna fattura
        $fatturaPassiva->update([
            'supplier_id'         => $validated['supplier_id'],
            'numero_fattura'      => $validated['numero_fattura'],
            'data_fattura'        => $validated['data_fattura'],
            'data_ricezione'      => $validated['data_ricezione'],
            'data_registrazione'  => $validated['data_registrazione'],
            'data_scadenza'       => $validated['data_scadenza'],
            'esigibilita'         => $validated['esigibilita'],
            'tipo_documento'      => $validated['tipo_documento'],
            'note'                => $validated['note'],
        ]);

        // Elimina righe vecchie
        $fatturaPassiva->righe()->delete();

        // Crea righe nuove
        foreach ($validated['righe'] as $rigaData) {
            $qta = (float) $rigaData['quantita'];
            $prezzo = (float) $rigaData['prezzo_unitario'];
            $imponibile = round($qta * $prezzo, 2);

            $codiceIva = CodiceIva::find($rigaData['codice_iva_id']);
            $aliquota = $codiceIva ? (float) $codiceIva->percentuale : 0;
            $iva = round($imponibile * $aliquota / 100, 2);

            $indPct = (float) ($rigaData['indetraibile_percentuale'] ?? $codiceIva?->indetraibile_percentuale ?? 0);
            $ivaInd = round($iva * $indPct / 100, 2);

            $fatturaPassiva->righe()->create([
                'tenant_id'                => $tenant->id,
                'codice_iva_id'            => $rigaData['codice_iva_id'],
                'conto_id'                 => $rigaData['conto_id'],
                'descrizione'              => $rigaData['descrizione'],
                'quantita'                 => $qta,
                'prezzo_unitario'          => $prezzo,
                'imponibile'               => $imponibile,
                'iva'                      => $iva,
                'totale'                   => round($imponibile + $iva, 2),
                'indetraibile_percentuale' => $indPct,
                'iva_indetraibile'         => $ivaInd,
            ]);
        }

        // Ricalcola totali
        $fatturaPassiva->ricalcolaTotali();
        $fatturaPassiva->save();

        return redirect()->route('iva.fatture-passive.show', $fatturaPassiva)->with('flash', [
            'type'    => 'success',
            'message' => "Fattura {$fatturaPassiva->numero_fattura} aggiornata con successo.",
        ]);
    }

    /**
     * Elimina fattura passiva (soft delete).
     */
    public function fatturePassiveDestroy(FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        if ($fatturaPassiva->isReadOnly()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Impossibile eliminare: fattura agganciata a liquidazione IVA definitiva.',
            ]);
        }

        $numero = $fatturaPassiva->numero_fattura;
        $fatturaPassiva->delete();

        return redirect()->route('iva.fatture-passive.index')->with('flash', [
            'type'    => 'success',
            'message' => "Fattura {$numero} eliminata con successo.",
        ]);
    }

    /**
     * Marca fattura passiva come pagata.
     */
    public function fatturePassiveMarkPaid(FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        if ($fatturaPassiva->isReadOnly()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Impossibile modificare: fattura agganciata a liquidazione IVA definitiva.',
            ]);
        }

        $fatturaPassiva->update(['stato_pagamento' => FatturaPassiva::STATO_PAGATA]);

        return back()->with('flash', [
            'type'    => 'success',
            'message' => "Fattura {$fatturaPassiva->numero_fattura} marcata come pagata.",
        ]);
    }

    /**
     * Marca fattura passiva come registrata.
     */
    public function fatturePassiveMarkRegistered(FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        // Questo metodo dipende dallo stato interno; per ora è un placeholder
        return back()->with('flash', [
            'type'    => 'info',
            'message' => 'Operazione non ancora implementata.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LIPE XML
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Genera e scarica il file XML LIPE per il trimestre richiesto.
     *
     * Query params: anno (default anno corrente), trimestre (1-4, default trimestre corrente).
     */
    public function lipeXml(Request $request): StreamedResponse
    {
        $request->validate([
            'anno'      => 'nullable|integer|min:2000|max:2100',
            'trimestre' => 'nullable|integer|min:1|max:4',
        ]);

        $anno      = (int) $request->input('anno',      now()->year);
        $trimestre = (int) $request->input('trimestre', (int) ceil(now()->month / 3));

        $tenant  = app('current_tenant');
        $service = app(LipeXmlService::class);

        try {
            $xml = $service->genera($tenant, $anno, $trimestre);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $filename = "LIPE_{$anno}_T{$trimestre}_{$tenant->slug}.xml";

        return response()->streamDownload(
            fn () => print($xml),
            $filename,
            [
                'Content-Type'        => 'application/xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Acconto IVA dicembre
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Pagina di calcolo acconto IVA (art. 6, L. 405/1990).
     * Mostra i tre metodi (storico, previsionale, analitico).
     */
    public function accontoIva(Request $request): Response
    {
        $anno    = (int) $request->input('anno', now()->year);
        $tenant  = app('current_tenant');
        $service = app(AccontoIvaService::class);

        $prospetto      = $service->calcola($tenant, $anno);
        $anniDisponibili = range(now()->year, max(now()->year - 5, 2020));

        return Inertia::render('Iva/AccontoIva', [
            'prospetto'       => $prospetto,
            'anno'            => $anno,
            'anni_disponibili' => $anniDisponibili,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Annulla fattura passiva
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Annulla fattura passiva.
     */
    public function fatturePassiveCancelTTL(FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        if ($fatturaPassiva->isReadOnly()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Impossibile modificare: fattura agganciata a liquidazione IVA definitiva.',
            ]);
        }

        $fatturaPassiva->update(['stato_pagamento' => FatturaPassiva::STATO_ANNULLATA]);

        return back()->with('flash', [
            'type'    => 'success',
            'message' => "Fattura {$fatturaPassiva->numero_fattura} annullata.",
        ]);
    }

    // ── CRUD Codici IVA ────────────────────────────────────────

    /**
     * Form creazione nuovo codice IVA.
     */
    public function codiciCreate(): Response
    {
        $tipi = [
            CodiceIva::TIPO_NORMALE        => 'Normale',
            CodiceIva::TIPO_ESENTE         => 'Esente',
            CodiceIva::TIPO_FUORI_CAMPO    => 'Fuori campo',
            CodiceIva::TIPO_NON_IMPONIBILE => 'Non imponibile',
            CodiceIva::TIPO_REVERSE_CHARGE => 'Reverse charge',
            CodiceIva::TIPO_SPLIT_PAYMENT  => 'Split payment',
        ];

        return Inertia::render('Iva/Codici/Create', [
            'tipi' => $tipi,
        ]);
    }

    /**
     * Salva nuovo codice IVA.
     */
    public function codiciStore(Request $request): RedirectResponse
    {
        $dati = $request->validate([
            'codice'                   => 'required|string|max:10|unique:codici_iva,codice,NULL,id,tenant_id,' . app('current_tenant')->id,
            'descrizione'              => 'required|string|max:255',
            'percentuale'              => 'required|numeric|min:0|max:100',
            'tipo'                     => 'required|in:' . implode(',', [
                CodiceIva::TIPO_NORMALE,
                CodiceIva::TIPO_ESENTE,
                CodiceIva::TIPO_FUORI_CAMPO,
                CodiceIva::TIPO_NON_IMPONIBILE,
                CodiceIva::TIPO_REVERSE_CHARGE,
                CodiceIva::TIPO_SPLIT_PAYMENT,
            ]),
            'natura_sdi'               => 'required|string|in:N,E,F,L,R,S',
            'indetraibile_percentuale' => 'required|numeric|min:0|max:100',
            'attivo'                   => 'boolean',
        ]);

        $dati['attivo'] = $request->boolean('attivo', true);

        CodiceIva::create($dati);

        return redirect()->route('iva.codici.index')->with('flash', [
            'type'    => 'success',
            'message' => "Codice IVA {$dati['codice']} creato.",
        ]);
    }

    /**
     * Form edit codice IVA.
     */
    public function codiciEdit(CodiceIva $codiceIva): Response
    {
        if ($codiceIva->di_sistema) {
            abort(403, 'Non puoi modificare codici IVA di sistema.');
        }

        $tipi = [
            CodiceIva::TIPO_NORMALE        => 'Normale',
            CodiceIva::TIPO_ESENTE         => 'Esente',
            CodiceIva::TIPO_FUORI_CAMPO    => 'Fuori campo',
            CodiceIva::TIPO_NON_IMPONIBILE => 'Non imponibile',
            CodiceIva::TIPO_REVERSE_CHARGE => 'Reverse charge',
            CodiceIva::TIPO_SPLIT_PAYMENT  => 'Split payment',
        ];

        return Inertia::render('Iva/Codici/Edit', [
            'codice' => $codiceIva,
            'tipi'   => $tipi,
        ]);
    }

    /**
     * Aggiorna codice IVA.
     */
    public function codiciUpdate(Request $request, CodiceIva $codiceIva): RedirectResponse
    {
        if ($codiceIva->di_sistema) {
            abort(403, 'Non puoi modificare codici IVA di sistema.');
        }

        $dati = $request->validate([
            'codice'                   => 'required|string|max:10|unique:codici_iva,codice,' . $codiceIva->id . ',id,tenant_id,' . app('current_tenant')->id,
            'descrizione'              => 'required|string|max:255',
            'percentuale'              => 'required|numeric|min:0|max:100',
            'tipo'                     => 'required|in:' . implode(',', [
                CodiceIva::TIPO_NORMALE,
                CodiceIva::TIPO_ESENTE,
                CodiceIva::TIPO_FUORI_CAMPO,
                CodiceIva::TIPO_NON_IMPONIBILE,
                CodiceIva::TIPO_REVERSE_CHARGE,
                CodiceIva::TIPO_SPLIT_PAYMENT,
            ]),
            'natura_sdi'               => 'required|string|in:N,E,F,L,R,S',
            'indetraibile_percentuale' => 'required|numeric|min:0|max:100',
            'attivo'                   => 'boolean',
        ]);

        $dati['attivo'] = $request->boolean('attivo', true);

        $codiceIva->update($dati);

        return redirect()->route('iva.codici.index')->with('flash', [
            'type'    => 'success',
            'message' => "Codice IVA {$codiceIva->codice} aggiornato.",
        ]);
    }

    /**
     * Elimina codice IVA.
     */
    public function codiciDestroy(CodiceIva $codiceIva): RedirectResponse
    {
        if ($codiceIva->di_sistema) {
            abort(403, 'Non puoi eliminare codici IVA di sistema.');
        }

        if ($codiceIva->righeFatturePassive()->exists() || $codiceIva->righeFattureAttive()->exists()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => "Non puoi eliminare {$codiceIva->codice}: è usato in fatture.",
            ]);
        }

        $codiceIva->delete();

        return redirect()->route('iva.codici.index')->with('flash', [
            'type'    => 'success',
            'message' => "Codice IVA eliminato.",
        ]);
    }
}
