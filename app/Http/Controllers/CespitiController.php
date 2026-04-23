<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciationSchedule;
use App\Models\ContoContabile;
use App\Models\Supplier;
use App\Services\CespitiService;
use App\Support\PdfLetterheadData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller CRUD per il Registro Cespiti.
 *
 * Gestisce la creazione, visualizzazione, modifica e cancellazione dei cespiti.
 * Delega la logica di business a CespitiService.
 */
class CespitiController extends Controller
{
    public function __construct(
        private readonly CespitiService $service
    ) {
        $this->middleware('role:admin,contabile,segreteria');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $query = Asset::with(['category', 'supplier'])
            ->when($request->filled('stato'), fn ($q) => $q->where('stato', $request->stato))
            ->when($request->filled('categoria'), fn ($q) => $q->where('asset_category_id', $request->categoria))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q2) use ($s) {
                    $q2->where('name', 'like', "%{$s}%")
                       ->orWhere('code', 'like', "%{$s}%")
                       ->orWhere('matricola', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('data_inizio_ammortamento')
            ->orderByDesc('id');

        $assets = $query->paginate(20)->withQueryString();

        // Calcola VNC corrente per ogni cespite nella pagina
        $assets->getCollection()->transform(function (Asset $asset) {
            $asset->vnc_corrente      = $asset->valoreNetto();
            $asset->fondo_corrente    = $asset->fondoCumulato();
            return $asset;
        });

        $tenant = app('current_tenant');

        return Inertia::render('Cespiti/Index', [
            'assets'      => $assets,
            'filters'     => $request->only('stato', 'categoria', 'search'),
            'categorie'   => AssetCategory::perTenant($tenant->id)->attive()->orderBy('codice')->get(['id', 'codice', 'descrizione']),
            'statiLabel'  => Asset::STATI_LABEL,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): Response
    {
        $tenant = app('current_tenant');

        return Inertia::render('Cespiti/Create', [
            'categorie'  => AssetCategory::perTenant($tenant->id)->attive()->orderBy('codice')->get(),
            'suppliers'  => Supplier::attivi()->orderBy('name')->get(['id', 'name', 'ragione_sociale']),
            'conti'      => ContoContabile::withoutGlobalScope('tenant')
                                ->where('tenant_id', $tenant->id)
                                ->where('movimentabile', true)
                                ->orderBy('codice')
                                ->get(['id', 'codice', 'nome']),
            'metodi'     => Asset::METODI_LABEL,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dati = $request->validate([
            'name'                     => 'required|string|max:255',
            'code'                     => 'nullable|string|max:50',
            'matricola'                => 'nullable|string|max:100',
            'asset_category_id'        => 'nullable|integer|exists:asset_categories,id',
            'supplier_id'              => 'nullable|integer|exists:suppliers,id',
            'costo_storico'            => 'required|numeric|min:0.01',
            'data_inizio_ammortamento' => 'required|date',
            'purchase_date'            => 'nullable|date',
            'aliquota_custom'          => 'nullable|numeric|min:0|max:100',
            'metodo_ammortamento'      => 'required|in:ordinario,ridotto,accelerato,anticipato',
            'primo_anno_ridotto'       => 'boolean',
            'percentuale_deducibilita' => 'required|numeric|min:0|max:100',
            'conto_bene_id'            => 'nullable|integer|exists:conti_contabili,id',
            'conto_fondo_id'           => 'nullable|integer|exists:conti_contabili,id',
            'notes'                    => 'nullable|string',
            'note_fiscali'             => 'nullable|string',
        ]);

        try {
            $tenant = app('current_tenant');
            $asset  = $this->service->crea($tenant, $dati);

            return redirect()
                ->route('cespiti.show', $asset)
                ->with('flash', ['type' => 'success', 'message' => "Cespite '{$asset->name}' creato con successo."]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Asset $asset): Response
    {
        $asset->load(['category', 'supplier', 'disposal', 'depreciationSchedules.movimentoContabile']);

        $piano = $this->service->previewPianoAmmortamento($asset);

        return Inertia::render('Cespiti/Show', [
            'asset'            => $asset,
            'pianoPreview'     => $piano,
            'vnc'              => $asset->valoreNetto(),
            'fondoCumulato'    => $asset->fondoCumulato(),
            'statiLabel'       => Asset::STATI_LABEL,
            'tipiDisposalLabel'=> \App\Models\AssetDisposal::TIPI_LABEL,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(Asset $asset): Response
    {
        $tenant = app('current_tenant');

        return Inertia::render('Cespiti/Edit', [
            'asset'     => $asset->load(['category', 'supplier']),
            'categorie' => AssetCategory::perTenant($tenant->id)->attive()->orderBy('codice')->get(),
            'suppliers' => Supplier::attivi()->orderBy('name')->get(['id', 'name', 'ragione_sociale']),
            'conti'     => ContoContabile::withoutGlobalScope('tenant')
                               ->where('tenant_id', $tenant->id)
                               ->where('movimentabile', true)
                               ->orderBy('codice')
                               ->get(['id', 'codice', 'nome']),
            'metodi'    => Asset::METODI_LABEL,
        ]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $dati = $request->validate([
            'name'                     => 'required|string|max:255',
            'code'                     => 'nullable|string|max:50',
            'matricola'                => 'nullable|string|max:100',
            'asset_category_id'        => 'nullable|integer|exists:asset_categories,id',
            'supplier_id'              => 'nullable|integer|exists:suppliers,id',
            'costo_storico'            => 'required|numeric|min:0.01',
            'data_inizio_ammortamento' => 'required|date',
            'purchase_date'            => 'nullable|date',
            'aliquota_custom'          => 'nullable|numeric|min:0|max:100',
            'metodo_ammortamento'      => 'required|in:ordinario,ridotto,accelerato,anticipato',
            'primo_anno_ridotto'       => 'boolean',
            'percentuale_deducibilita' => 'required|numeric|min:0|max:100',
            'conto_bene_id'            => 'nullable|integer|exists:conti_contabili,id',
            'conto_fondo_id'           => 'nullable|integer|exists:conti_contabili,id',
            'notes'                    => 'nullable|string',
            'note_fiscali'             => 'nullable|string',
        ]);

        try {
            $asset = $this->service->aggiorna($asset, $dati);

            return redirect()
                ->route('cespiti.show', $asset)
                ->with('flash', ['type' => 'success', 'message' => "Cespite '{$asset->name}' aggiornato."]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PDF Registro Cespiti
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Genera e scarica il PDF del Registro Cespiti.
     *
     * Parametri GET accettati:
     *   esercizio  (int)  — anno di riferimento (default: anno corrente)
     *   stato      (string) — in_uso|dismesso|venduto (default: tutti)
     *   categoria  (int)  — asset_category_id (default: tutte)
     */
    public function registroPdf(Request $request): \Illuminate\Http\Response
    {
        $esercizio = (int) ($request->esercizio ?? date('Y'));
        $stato     = $request->stato ?? null;
        $catId     = $request->categoria ? (int) $request->categoria : null;

        // Query cespiti
        $query = Asset::with(['category', 'depreciationSchedules' => function ($q) use ($esercizio) {
            $q->where('esercizio', $esercizio);
        }])
        ->when($stato, fn ($q) => $q->where('stato', $stato))
        ->when($catId, fn ($q) => $q->where('asset_category_id', $catId))
        ->orderBy('asset_category_id')
        ->orderBy('data_inizio_ammortamento')
        ->orderBy('name');

        $assets = $query->get();

        // Costruisce i dati per il template, raggruppati per categoria
        $gruppi = $assets->groupBy(function (Asset $a) {
            return $a->category
                ? "{$a->category->codice} — {$a->category->descrizione}"
                : 'Senza categoria';
        })->map(function (Collection $items) use ($esercizio) {
            return $items->map(function (Asset $asset) use ($esercizio) {
                // Fondo inizio anno = schedules definitive fino all'anno precedente
                $fondoInizio = (float) $asset->depreciationSchedules()
                    ->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)
                    ->where('esercizio', '<', $esercizio)
                    ->sum('quota_registrata');

                // Quota esercizio
                $schedule    = $asset->depreciationSchedules->first();
                $quota       = $schedule ? (float) ($schedule->quota_registrata ?? $schedule->quota_calcolata ?? 0) : 0;
                $quotaStato  = $schedule ? ($schedule->stato === 'bozza' ? 'Bozza' : null) : null;

                $fondoFine = round($fondoInizio + $quota, 2);
                $vncFine   = max(0, round((float) $asset->costo_storico - $fondoFine, 2));

                return [
                    'codice'       => $asset->code,
                    'nome'         => $asset->name,
                    'matricola'    => $asset->matricola,
                    'data_acquisto'=> $asset->purchase_date,
                    'costo_storico'=> (float) $asset->costo_storico,
                    'fondo_inizio' => $fondoInizio,
                    'aliquota'     => $asset->aliquotaEffettiva() ?: null,
                    'quota'        => $quota,
                    'quota_stato'  => $quotaStato,
                    'fondo_fine'   => $fondoFine,
                    'vnc_fine'     => $vncFine,
                    'stato'        => $asset->stato,
                    'deducibilita' => (float) $asset->percentuale_deducibilita,
                ];
            });
        });

        // Filtri attivi (per intestazione PDF)
        $filtriAttivi = [];
        if ($stato) {
            $filtriAttivi[] = 'Stato: ' . (Asset::STATI_LABEL[$stato] ?? $stato);
        }
        if ($catId) {
            $cat = AssetCategory::find($catId);
            if ($cat) $filtriAttivi[] = 'Categoria: ' . $cat->codice;
        }

        $pdf = Pdf::loadView('cespiti.registro-pdf', [
            'esercizio'      => $esercizio,
            'gruppi'         => $gruppi,
            'totaleCespiti'  => $assets->count(),
            'filtriAttivi'   => $filtriAttivi,
            'letterhead'     => PdfLetterheadData::data(),
        ])
        ->setPaper('a4', 'landscape')
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('isRemoteEnabled', false);

        $filename = "registro-cespiti-{$esercizio}.pdf";

        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Asset $asset): RedirectResponse
    {
        if ($asset->depreciationSchedules()->where('stato', \App\Models\AssetDepreciationSchedule::STATO_DEFINITIVO)->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Impossibile eliminare: esistono ammortamenti definitivi registrati.']);
        }

        if ($asset->disposal()->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Impossibile eliminare: esiste una dismissione registrata.']);
        }

        $nome = $asset->name;
        $asset->delete();

        return redirect()
            ->route('cespiti.index')
            ->with('flash', ['type' => 'success', 'message' => "Cespite '{$nome}' eliminato."]);
    }
}
