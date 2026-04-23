<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\ContoContabile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller per la gestione delle categorie fiscali di cespiti.
 *
 * Le categorie di sistema (di_sistema = true) possono essere visualizzate
 * da tutti ma modificate solo da admin.
 * I tenant possono creare categorie personalizzate (tenant_id valorizzato).
 */
class AssetCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,contabile,segreteria')->only('index');
        $this->middleware('role:admin')->except('index');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): Response
    {
        $tenant = app('current_tenant');

        $categorie = AssetCategory::perTenant($tenant->id)
            ->withCount('assets')
            ->orderBy('di_sistema', 'desc')
            ->orderBy('codice')
            ->get();

        $conti = ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('movimentabile', true)
            ->orderBy('codice')
            ->get(['id', 'codice', 'nome']);

        return Inertia::render('Cespiti/Categorie/Index', [
            'categorie' => $categorie,
            'conti'     => $conti,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $dati = $request->validate([
            'codice'                          => 'required|string|max:20',
            'descrizione'                     => 'required|string|max:255',
            'coefficiente_ministeriale'       => 'required|numeric|min:0|max:100',
            'percentuale_deducibilita_default' => 'required|numeric|min:0|max:100',
            'primo_anno_ridotto_default'      => 'boolean',
            'conto_bene_default_id'           => 'nullable|integer|exists:conti_contabili,id',
            'conto_fondo_default_id'          => 'nullable|integer|exists:conti_contabili,id',
            'conto_ammortamento_default_id'   => 'nullable|integer|exists:conti_contabili,id',
        ]);

        $tenant = app('current_tenant');

        // Verifica univocità codice per il tenant
        $exists = AssetCategory::where('tenant_id', $tenant->id)
            ->where('codice', $dati['codice'])
            ->exists();

        if ($exists) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => "Esiste già una categoria con codice '{$dati['codice']}'."]);
        }

        AssetCategory::create(array_merge($dati, [
            'tenant_id'  => $tenant->id,
            'di_sistema' => false,
            'attivo'     => true,
        ]));

        return redirect()
            ->route('cespiti.categorie.index')
            ->with('flash', ['type' => 'success', 'message' => "Categoria '{$dati['codice']}' creata."]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, AssetCategory $categoria): RedirectResponse
    {
        // Impedisce la modifica delle categorie di sistema
        if ($categoria->di_sistema) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Le categorie di sistema non possono essere modificate.']);
        }

        $dati = $request->validate([
            'descrizione'                     => 'required|string|max:255',
            'coefficiente_ministeriale'       => 'required|numeric|min:0|max:100',
            'percentuale_deducibilita_default' => 'required|numeric|min:0|max:100',
            'primo_anno_ridotto_default'      => 'boolean',
            'conto_bene_default_id'           => 'nullable|integer|exists:conti_contabili,id',
            'conto_fondo_default_id'          => 'nullable|integer|exists:conti_contabili,id',
            'conto_ammortamento_default_id'   => 'nullable|integer|exists:conti_contabili,id',
            'attivo'                          => 'boolean',
        ]);

        $categoria->update($dati);

        return redirect()
            ->route('cespiti.categorie.index')
            ->with('flash', ['type' => 'success', 'message' => "Categoria aggiornata."]);
    }
}
