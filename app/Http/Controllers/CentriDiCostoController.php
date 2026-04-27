<?php

namespace App\Http\Controllers;

use App\Models\CentroCosto;
use App\Services\BilancioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestione Centri di Costo per contabilità analitica.
 * Admin/contabile → scrittura; segreteria → lettura.
 */
class CentriDiCostoController extends Controller
{
    public function __construct(private readonly BilancioService $bilancio)
    {
        $this->middleware('role:admin,contabile,segreteria');
    }

    // ── Elenco ───────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $tenant  = app('current_tenant');
        $anno    = (int) $request->input('anno', now()->year);

        $centri = CentroCosto::when($request->boolean('solo_attivi', true),
                fn ($q) => $q->attivi())
            ->orderBy('codice')
            ->get();

        // Arricchisci ogni centro con saldi dell'anno
        $centri->each(function (CentroCosto $c) use ($anno) {
            $c->saldo = $c->saldoAnno($anno);
        });

        return Inertia::render('Contabilita/CentriDiCosto/Index', [
            'centri'      => $centri,
            'anno'        => $anno,
            'solo_attivi' => $request->boolean('solo_attivi', true),
        ]);
    }

    // ── Dettaglio / report CE per CDC ─────────────────────────────────────────

    public function show(Request $request, CentroCosto $centroCosto): Response
    {
        $anno    = (int) $request->input('anno', now()->year);
        $annoPre = $anno - 1;

        $saldo   = $centroCosto->saldoAnno($anno);
        $saldoP  = $centroCosto->saldoAnno($annoPre);

        // Righe associate nell'anno (per dettaglio movimenti)
        $righe = $centroCosto->righeMovimento()
            ->with(['movimento', 'contoContabile'])
            ->whereHas('movimento', fn ($q) => $q->where('anno_esercizio', $anno))
            ->get();

        return Inertia::render('Contabilita/CentriDiCosto/Show', [
            'centro'  => $centroCosto,
            'saldo'   => $saldo,
            'saldoP'  => $saldoP,
            'righe'   => $righe,
            'anno'    => $anno,
            'annoPre' => $annoPre,
        ]);
    }

    // ── Crea ─────────────────────────────────────────────────────────────────

    public function create(): Response
    {
        $this->denyUnlessRole('admin', 'contabile');

        return Inertia::render('Contabilita/CentriDiCosto/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->denyUnlessRole('admin', 'contabile');

        $data = $request->validate([
            'codice'      => 'required|string|max:20',
            'descrizione' => 'required|string|max:200',
            'note'        => 'nullable|string|max:1000',
            'attivo'      => 'boolean',
        ]);

        // Unicità codice per tenant
        $exists = CentroCosto::where('codice', $data['codice'])->exists();
        if ($exists) {
            return back()->withInput()->with('flash', [
                'type'    => 'error',
                'message' => "Esiste già un centro di costo con codice '{$data['codice']}'.",
            ]);
        }

        $centro = CentroCosto::create($data);

        return redirect()
            ->route('centri-di-costo.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => "Centro '{$centro->descrizione}' creato."]);
    }

    // ── Modifica ─────────────────────────────────────────────────────────────

    public function edit(CentroCosto $centroCosto): Response
    {
        $this->denyUnlessRole('admin', 'contabile');

        return Inertia::render('Contabilita/CentriDiCosto/Edit', [
            'centro' => $centroCosto,
        ]);
    }

    public function update(Request $request, CentroCosto $centroCosto): RedirectResponse
    {
        $this->denyUnlessRole('admin', 'contabile');

        $data = $request->validate([
            'codice'      => 'required|string|max:20',
            'descrizione' => 'required|string|max:200',
            'note'        => 'nullable|string|max:1000',
            'attivo'      => 'boolean',
        ]);

        // Unicità codice (escludi se stessa)
        $exists = CentroCosto::where('codice', $data['codice'])
            ->where('id', '!=', $centroCosto->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('flash', [
                'type'    => 'error',
                'message' => "Esiste già un centro di costo con codice '{$data['codice']}'.",
            ]);
        }

        $centroCosto->update($data);

        return redirect()
            ->route('centri-di-costo.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => "Centro '{$centroCosto->descrizione}' aggiornato."]);
    }

    // ── Elimina (solo admin) ──────────────────────────────────────────────────

    public function destroy(CentroCosto $centroCosto): RedirectResponse
    {
        $this->denyUnlessRole('admin');

        // Blocca se ci sono righe associate
        if ($centroCosto->righeMovimento()->exists()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Impossibile eliminare: esistono movimenti contabili associati a questo centro.',
            ]);
        }

        $descr = $centroCosto->descrizione;
        $centroCosto->delete();

        return redirect()
            ->route('centri-di-costo.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => "Centro '{$descr}' eliminato."]);
    }

    // ── Attiva/Disattiva ──────────────────────────────────────────────────────

    public function toggleAttivo(CentroCosto $centroCosto): RedirectResponse
    {
        $this->denyUnlessRole('admin', 'contabile');

        $centroCosto->update(['attivo' => ! $centroCosto->attivo]);

        $stato = $centroCosto->fresh()->attivo ? 'attivato' : 'disattivato';

        return back()->with('flash', [
            'type'    => 'success',
            'message' => "Centro '{$centroCosto->descrizione}' {$stato}.",
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function denyUnlessRole(string ...$roles): void
    {
        $user = auth()->user();
        if (! $user?->hasRole(...$roles)) {
            abort(403);
        }
    }
}
