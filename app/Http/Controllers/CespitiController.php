<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ContoContabile;
use App\Models\Supplier;
use App\Services\CespitiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
