<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFatturaPassivaRequest;
use App\Http\Requests\UpdateFatturaPassivaRequest;
use App\Models\CodiceIva;
use App\Models\Conto;
use App\Models\FatturaPassiva;
use App\Models\Supplier;
use App\Services\FatturaPassivaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Controller per la registrazione e gestione delle fatture passive.
 *
 * Le route sono protette dal middleware 'cooperative'.
 * Le azioni di scrittura richiedono i ruoli admin, segreteria o contabile.
 */
class FatturaPassivaController extends Controller
{
    public function __construct(private FatturaPassivaService $service) {}

    // ─────────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $fatture = FatturaPassiva::query()
            ->with('supplier')
            ->when($request->string('numero')->isNotEmpty(), fn ($q, $v) => $q->where('numero_fattura', 'like', "%{$request->string('numero')}%"))
            ->when($request->string('stato')->isNotEmpty(), fn ($q) => $q->where('stato_pagamento', $request->string('stato')))
            ->when($request->integer('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->orderByDesc('data_registrazione')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Iva/FatturePassive/Index', [
            'fatture'    => $fatture,
            'filters'    => $request->only('numero', 'stato', 'supplier_id'),
            'suppliers'  => Supplier::attivi()->orderBy('name')->get(['id', 'name', 'ragione_sociale']),
            'statiLabel' => FatturaPassiva::STATI_LABEL,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Iva/FatturePassive/Create', [
            'suppliers'       => Supplier::attivi()->orderBy('name')->get(['id', 'name', 'ragione_sociale']),
            'codiciIva'       => CodiceIva::attivi()->orderBy('codice')->get(['id', 'codice', 'descrizione', 'percentuale', 'indetraibile_percentuale']),
            'conti'           => Conto::attivi()->ordered()->get(['id', 'name', 'code']),
            'tipiDocumento'   => FatturaPassiva::TIPI_DOCUMENTO,
            'esigibilitaOpts' => FatturaPassiva::ESIGIBILITA_LABEL,
        ]);
    }

    public function store(StoreFatturaPassivaRequest $request): RedirectResponse
    {
        try {
            $fattura = $this->service->registra(
                $request->testata(),
                $request->validated()['righe'],
            );

            return redirect()
                ->route('iva.fatture-passive.show', $fattura)
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Fattura {$fattura->numero_fattura} registrata con successo.",
                ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(FatturaPassiva $fatturaPassiva): Response
    {
        $fatturaPassiva->load(['righe.codiceIva', 'supplier', 'liquidazione']);

        return Inertia::render('Iva/FatturePassive/Show', [
            'fattura'    => $fatturaPassiva,
            'isReadOnly' => $fatturaPassiva->isReadOnly(),
            'statiLabel' => FatturaPassiva::STATI_LABEL,
            'tipiDocumento' => FatturaPassiva::TIPI_DOCUMENTO,
        ]);
    }

    public function edit(FatturaPassiva $fatturaPassiva): Response|RedirectResponse
    {
        if ($fatturaPassiva->isReadOnly()) {
            return redirect()
                ->route('iva.fatture-passive.show', $fatturaPassiva)
                ->with('flash', ['type' => 'error', 'message' => 'Fattura non modificabile: è agganciata a una liquidazione definitiva.']);
        }

        $fatturaPassiva->load(['righe.codiceIva']);

        return Inertia::render('Iva/FatturePassive/Edit', [
            'fattura'         => $fatturaPassiva,
            'suppliers'       => Supplier::attivi()->orderBy('name')->get(['id', 'name', 'ragione_sociale']),
            'codiciIva'       => CodiceIva::attivi()->orderBy('codice')->get(['id', 'codice', 'descrizione', 'percentuale', 'indetraibile_percentuale']),
            'conti'           => Conto::attivi()->ordered()->get(['id', 'name', 'code']),
            'tipiDocumento'   => FatturaPassiva::TIPI_DOCUMENTO,
            'esigibilitaOpts' => FatturaPassiva::ESIGIBILITA_LABEL,
        ]);
    }

    public function update(UpdateFatturaPassivaRequest $request, FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        try {
            $fattura = $this->service->aggiorna(
                $fatturaPassiva,
                $request->testata(),
                $request->validated()['righe'],
            );

            return redirect()
                ->route('iva.fatture-passive.show', $fattura)
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Fattura {$fattura->numero_fattura} aggiornata.",
                ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function destroy(FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        try {
            $this->service->verificaModificabile($fatturaPassiva);

            if ($fatturaPassiva->righe()->count() > 0) {
                $fatturaPassiva->righe()->delete();
            }
            $fatturaPassiva->delete();

            return redirect()
                ->route('iva.fatture-passive.index')
                ->with('flash', ['type' => 'success', 'message' => 'Fattura eliminata.']);
        } catch (InvalidArgumentException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cambi di stato
    // ─────────────────────────────────────────────────────────────────────

    public function marcaPagata(FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        try {
            $this->service->marcaPagata($fatturaPassiva);
            return back()->with('flash', ['type' => 'success', 'message' => 'Fattura marcata come pagata.']);
        } catch (InvalidArgumentException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function marcaParzialmentePagata(FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        try {
            $this->service->marcaParzialmentePagata($fatturaPassiva);
            return back()->with('flash', ['type' => 'success', 'message' => 'Fattura marcata come parzialmente pagata.']);
        } catch (InvalidArgumentException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function reimpostaDaPagare(FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        $this->service->reimpostaDaPagare($fatturaPassiva);
        return back()->with('flash', ['type' => 'success', 'message' => 'Stato fattura reimpostato a "Da pagare".']);
    }

    public function annulla(FatturaPassiva $fatturaPassiva): RedirectResponse
    {
        try {
            $this->service->annulla($fatturaPassiva);
            return back()->with('flash', ['type' => 'success', 'message' => 'Fattura annullata.']);
        } catch (InvalidArgumentException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Preview totali (AJAX)
    // ─────────────────────────────────────────────────────────────────────

    public function previewTotali(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'righe'                             => 'required|array|min:1',
            'righe.*.codice_iva_id'             => 'required|integer|exists:codici_iva,id',
            'righe.*.quantita'                  => 'required|numeric|min:0',
            'righe.*.prezzo_unitario'           => 'required|numeric',
            'righe.*.indetraibile_percentuale'  => 'nullable|numeric|min:0|max:100',
        ]);

        return response()->json(
            $this->service->calcolaTotaliPreview($validated['righe'])
        );
    }
}
