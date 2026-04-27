<?php

namespace App\Http\Controllers;

use App\Models\CodiceIva;
use App\Models\ContoContabile;
use App\Models\FatturaAttiva;
use App\Models\Member;
use App\Services\FatturaAttivaService;
use App\Services\FatturaXmlService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestione completa del ciclo attivo di vendita (fatture attive).
 *
 * Middleware: role:admin,contabile (via route)
 */
class FatturaAttivaController extends Controller
{
    public function __construct(
        private readonly FatturaAttivaService $service,
        private readonly FatturaXmlService    $xmlService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', FatturaAttiva::class);

        $fatture = FatturaAttiva::query()
            ->with('righe.codiceIva')
            ->when($request->string('numero')->isNotEmpty(),
                fn ($q) => $q->where('numero_fattura', 'like', "%{$request->string('numero')}%"))
            ->when($request->string('stato')->isNotEmpty(),
                fn ($q) => $q->where('stato', $request->string('stato')))
            ->when($request->string('stato_pagamento')->isNotEmpty(),
                fn ($q) => $q->where('stato_pagamento', $request->string('stato_pagamento')))
            ->when($request->string('tipo_documento')->isNotEmpty(),
                fn ($q) => $q->where('tipo_documento', $request->string('tipo_documento')))
            ->when($request->integer('anno'),
                fn ($q) => $q->where('anno', $request->integer('anno')))
            ->orderByDesc('data_fattura')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // KPI — esclude note di credito (TD04) dai totali "da incassare"
        $kpi = [
            'totale_emesse'     => (float) FatturaAttiva::emesse()->sum('totale_documento'),
            'totale_incassate'  => (float) FatturaAttiva::emesse()
                ->where('stato_pagamento', FatturaAttiva::STATO_PAG_INCASSATA)
                ->sum('totale_documento'),
            'totale_da_incassare' => (float) FatturaAttiva::emesse()
                ->where('tipo_documento', '!=', 'TD04')
                ->where('stato_pagamento', FatturaAttiva::STATO_PAG_DA_INCASSARE)
                ->sum('totale_documento'),
        ];

        return Inertia::render('Iva/FattureAttive/Index', [
            'fatture'       => $fatture,
            'kpi'           => $kpi,
            'filters'       => $request->only('numero', 'stato', 'stato_pagamento', 'tipo_documento', 'anno'),
            'statiLabel'    => [
                FatturaAttiva::STATO_BOZZA       => 'Bozza',
                FatturaAttiva::STATO_EMESSA       => 'Emessa',
                FatturaAttiva::STATO_INVIATA_SDI  => 'Inviata SDI',
                FatturaAttiva::STATO_ACCETTATA    => 'Accettata',
                FatturaAttiva::STATO_SCARTATA     => 'Scartata',
                FatturaAttiva::STATO_ANNULLATA    => 'Annullata',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────

    public function create(Request $request): InertiaResponse
    {
        $this->authorize('create', FatturaAttiva::class);

        $anno = (int) $request->input('anno', now()->year);

        return Inertia::render('Iva/FattureAttive/Create', [
            'codiciIva'          => CodiceIva::attivi()->orderBy('codice')
                ->get(['id', 'codice', 'descrizione', 'percentuale']),
            'clienti'            => Member::orderBy('cognome')->orderBy('nome')
                ->get(['id', 'nome', 'cognome', 'ragione_sociale', 'codice_fiscale']),
            'contiAttivo'        => ContoContabile::attivi()->movimentabili()
                ->whereIn('natura', [ContoContabile::NATURA_ATTIVO])
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
            'contiRicavo'        => ContoContabile::attivi()->movimentabili()
                ->where('natura', ContoContabile::NATURA_RICAVO)
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
            'contiIva'           => ContoContabile::attivi()->movimentabili()
                ->whereIn('natura', [ContoContabile::NATURA_PASSIVO, ContoContabile::NATURA_TRANSITORIO])
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
            'numeroSuggerito'    => $this->service->calcolaNumeroProgressivo(
                app('current_tenant'), $anno
            ),
            'anno'               => $anno,
            'tipiDocumento'      => ['TD01' => 'Fattura', 'TD07' => 'Fattura semplificata', 'TD24' => 'Fattura differita'],
            'esigibilitaOpts'    => [
                FatturaAttiva::ESIGIBILITA_IMMEDIATA     => 'Esigibilità immediata',
                FatturaAttiva::ESIGIBILITA_DIFFERITA     => 'Esigibilità differita',
                FatturaAttiva::ESIGIBILITA_SPLIT_PAYMENT => 'Split Payment',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', FatturaAttiva::class);

        $data = $request->validate([
            'anno'                => 'required|integer|min:2000|max:2100',
            'sezionale'           => 'nullable|string|max:20',
            'cliente_id'          => 'nullable|exists:members,id',
            'data_fattura'        => 'required|date',
            'data_scadenza'       => 'nullable|date|after_or_equal:data_fattura',
            'tipo_documento'      => 'required|in:TD01,TD07,TD24,TD04',
            'esigibilita'         => 'required|in:immediata,differita,split_payment',
            'stato'               => 'required|in:bozza,emessa',
            'note'                => 'nullable|string|max:1000',
            'conto_crediti_id'    => 'nullable|exists:conti_contabili,id',
            'conto_ricavi_id'     => 'nullable|exists:conti_contabili,id',
            'conto_iva_debito_id' => 'nullable|exists:conti_contabili,id',
            'righe'               => 'required|array|min:1',
            'righe.*.codice_iva_id'      => 'required|exists:codici_iva,id',
            'righe.*.descrizione'        => 'required|string|max:500',
            'righe.*.quantita'           => 'required|numeric|min:0',
            'righe.*.prezzo_unitario'    => 'required|numeric|min:0',
            'righe.*.sconto_percentuale' => 'nullable|numeric|min:0|max:100',
            'righe.*.conto_id'           => 'nullable|exists:conti,id',
        ]);

        try {
            $righe  = $data['righe'];
            unset($data['righe']);

            $fattura = $this->service->crea(app('current_tenant'), $data, $righe);

            return redirect()
                ->route('iva.fatture-attive.show', [request()->route('tenant'), $fattura])
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Fattura {$fattura->numero_fattura} creata con successo.",
                ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────

    public function show(FatturaAttiva $fatturaAttiva): InertiaResponse
    {
        $this->authorize('view', $fatturaAttiva);

        $fatturaAttiva->load(['righe.codiceIva', 'liquidazione']);

        return Inertia::render('Iva/FattureAttive/Show', [
            'fattura'         => $fatturaAttiva,
            'contiIncasso'    => ContoContabile::attivi()->movimentabili()
                ->where('natura', ContoContabile::NATURA_ATTIVO)
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
            'contiCrediti'    => ContoContabile::attivi()->movimentabili()
                ->where('natura', ContoContabile::NATURA_ATTIVO)
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────

    public function edit(FatturaAttiva $fatturaAttiva): InertiaResponse
    {
        $this->authorize('update', $fatturaAttiva);

        if ($fatturaAttiva->isReadOnly()) {
            return redirect()
                ->route('iva.fatture-attive.show', [request()->route('tenant'), $fatturaAttiva])
                ->with('flash', ['type' => 'warning', 'message' => 'Fattura non modificabile.']);
        }

        $fatturaAttiva->load('righe.codiceIva');

        return Inertia::render('Iva/FattureAttive/Edit', [
            'fattura'        => $fatturaAttiva,
            'codiciIva'      => CodiceIva::attivi()->orderBy('codice')
                ->get(['id', 'codice', 'descrizione', 'percentuale']),
            'clienti'        => Member::orderBy('cognome')->orderBy('nome')
                ->get(['id', 'nome', 'cognome', 'ragione_sociale', 'codice_fiscale']),
            'tipiDocumento'  => ['TD01' => 'Fattura', 'TD07' => 'Fattura semplificata', 'TD24' => 'Fattura differita'],
            'esigibilitaOpts' => [
                FatturaAttiva::ESIGIBILITA_IMMEDIATA     => 'Esigibilità immediata',
                FatturaAttiva::ESIGIBILITA_DIFFERITA     => 'Esigibilità differita',
                FatturaAttiva::ESIGIBILITA_SPLIT_PAYMENT => 'Split Payment',
            ],
        ]);
    }

    public function update(Request $request, FatturaAttiva $fatturaAttiva): RedirectResponse
    {
        $this->authorize('update', $fatturaAttiva);

        $data = $request->validate([
            'cliente_id'          => 'nullable|exists:members,id',
            'data_fattura'        => 'required|date',
            'data_scadenza'       => 'nullable|date|after_or_equal:data_fattura',
            'esigibilita'         => 'required|in:immediata,differita,split_payment',
            'note'                => 'nullable|string|max:1000',
            'righe'               => 'required|array|min:1',
            'righe.*.codice_iva_id'      => 'required|exists:codici_iva,id',
            'righe.*.descrizione'        => 'required|string|max:500',
            'righe.*.quantita'           => 'required|numeric|min:0',
            'righe.*.prezzo_unitario'    => 'required|numeric|min:0',
            'righe.*.sconto_percentuale' => 'nullable|numeric|min:0|max:100',
            'righe.*.conto_id'           => 'nullable|exists:conti,id',
        ]);

        try {
            $righe = $data['righe'];
            unset($data['righe']);

            $this->service->aggiorna($fatturaAttiva, $data, $righe);

            return redirect()
                ->route('iva.fatture-attive.show', [request()->route('tenant'), $fatturaAttiva])
                ->with('flash', ['type' => 'success', 'message' => 'Fattura aggiornata.']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Paga
    // ─────────────────────────────────────────────────────────────────────

    public function paga(Request $request, FatturaAttiva $fatturaAttiva): RedirectResponse
    {
        $data = $request->validate([
            'importo'          => 'required|numeric|min:0.01',
            'data_pagamento'   => 'required|date',
            'conto_incasso_id' => 'nullable|exists:conti_contabili,id',
            'conto_crediti_id' => 'nullable|exists:conti_contabili,id',
        ]);

        try {
            $this->service->registraPagamento($fatturaAttiva, $data);

            return redirect()
                ->route('iva.fatture-attive.show', [request()->route('tenant'), $fatturaAttiva])
                ->with('flash', ['type' => 'success', 'message' => 'Pagamento registrato.']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Storna
    // ─────────────────────────────────────────────────────────────────────

    public function storna(FatturaAttiva $fatturaAttiva): RedirectResponse
    {
        try {
            $notaCredito = $this->service->storna($fatturaAttiva);

            return redirect()
                ->route('iva.fatture-attive.show', [request()->route('tenant'), $notaCredito])
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Nota di credito {$notaCredito->numero_fattura} generata. Fattura {$fatturaAttiva->numero_fattura} annullata.",
                ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Crea Nota di Credito (TD04)
    // ─────────────────────────────────────────────────────────────────────

    public function creaNotaCredito(FatturaAttiva $fatturaAttiva): InertiaResponse|RedirectResponse
    {
        if (! in_array($fatturaAttiva->stato, [
            FatturaAttiva::STATO_EMESSA,
            FatturaAttiva::STATO_INVIATA_SDI,
            FatturaAttiva::STATO_ACCETTATA,
        ], true)) {
            return redirect()
                ->route('iva.fatture-attive.show', [request()->route('tenant'), $fatturaAttiva])
                ->with('flash', ['type' => 'error', 'message' => 'La fattura non è idonea per la creazione di nota di credito.']);
        }

        $fatturaAttiva->load('righe.codiceIva');

        return Inertia::render('Iva/FattureAttive/CreateNotaCredito', [
            'fattura'           => $fatturaAttiva,
            'contiCrediti'      => ContoContabile::attivi()->movimentabili()
                ->where('natura', ContoContabile::NATURA_ATTIVO)
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
            'contiRicavo'       => ContoContabile::attivi()->movimentabili()
                ->where('natura', ContoContabile::NATURA_RICAVO)
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
            'contiIva'          => ContoContabile::attivi()->movimentabili()
                ->whereIn('natura', [ContoContabile::NATURA_PASSIVO, ContoContabile::NATURA_TRANSITORIO])
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
        ]);
    }

    public function storeNotaCredito(Request $request, FatturaAttiva $fatturaAttiva): RedirectResponse
    {
        $data = $request->validate([
            'tipo_storno'         => 'required|in:totale,parziale',
            'importo_storno'      => 'nullable|numeric|min:0.01',
            'motivo_nota_credito' => 'required|string|max:500',
            'righe'               => 'nullable|array',
            'conto_crediti_id'    => 'nullable|exists:conti_contabili,id',
            'conto_ricavi_id'     => 'nullable|exists:conti_contabili,id',
            'conto_iva_debito_id' => 'nullable|exists:conti_contabili,id',
        ]);

        try {
            $notaCredito = $this->service->creaNdiCredito($fatturaAttiva, $data);

            return redirect()
                ->route('iva.fatture-attive.show', [request()->route('tenant'), $notaCredito])
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Nota di credito {$notaCredito->numero_fattura} creata con successo.",
                ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(FatturaAttiva $fatturaAttiva): RedirectResponse
    {
        $this->authorize('delete', $fatturaAttiva);

        if ($fatturaAttiva->stato !== FatturaAttiva::STATO_BOZZA) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'È possibile eliminare solo le fatture in bozza.',
            ]);
        }

        $numero = $fatturaAttiva->numero_fattura;
        $fatturaAttiva->righe()->delete();
        $fatturaAttiva->delete();

        return redirect()
            ->route('iva.fatture-attive.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => "Fattura {$numero} eliminata."]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export PDF
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdf(FatturaAttiva $fatturaAttiva)
    {
        $fatturaAttiva->load(['righe.codiceIva']);
        $tenant = app('current_tenant');

        $pdf = Pdf::loadView('pdf.fattura-attiva', [
            'fattura' => $fatturaAttiva,
            'tenant'  => $tenant,
        ]);

        return $pdf->download("fattura-{$fatturaAttiva->numero_fattura}.pdf");
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fattura Elettronica XML (F2)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera l'XML FatturaPA e lo restituisce come download.
     * Salva anche il path su FatturaAttiva::xml_sdi_path.
     */
    public function downloadXml(FatturaAttiva $fatturaAttiva)
    {
        $this->authorize('view', $fatturaAttiva);

        if (! in_array($fatturaAttiva->stato, [
            FatturaAttiva::STATO_EMESSA,
            FatturaAttiva::STATO_INVIATA_SDI,
            FatturaAttiva::STATO_ACCETTATA,
            FatturaAttiva::STATO_SCARTATA,
        ], true)) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'È possibile generare l\'XML solo per fatture emesse.',
            ]);
        }

        $fatturaAttiva->load(['righe.codiceIva']);

        try {
            $path = $this->xmlService->genera($fatturaAttiva);
            $filename = basename($path);

            return response()->streamDownload(function () use ($path) {
                echo \Illuminate\Support\Facades\Storage::disk('private')->get($path);
            }, $filename, [
                'Content-Type' => 'application/xml',
            ]);
        } catch (\Exception $e) {
            return back()->with('flash', ['type' => 'error', 'message' => "Errore generazione XML: {$e->getMessage()}"]);
        }
    }

    /**
     * Aggiorna lo stato SDI di una fattura (inviata/accettata/scartata).
     * In un'integrazione reale questo verrebbe chiamato dal webhook AdE.
     */
    public function aggiornaStatoSdi(Request $request, FatturaAttiva $fatturaAttiva): RedirectResponse
    {
        $this->authorize('update', $fatturaAttiva);

        $data = $request->validate([
            'stato'              => 'required|in:inviata_sdi,accettata,scartata',
            'sdi_identificativo' => 'nullable|string|max:100',
        ]);

        $fatturaAttiva->update([
            'stato'              => $data['stato'],
            'sdi_identificativo' => $data['sdi_identificativo'] ?? $fatturaAttiva->sdi_identificativo,
        ]);

        return redirect()
            ->route('iva.fatture-attive.show', [request()->route('tenant'), $fatturaAttiva])
            ->with('flash', [
                'type'    => 'success',
                'message' => "Stato SDI aggiornato: {$data['stato']}.",
            ]);
    }
}
