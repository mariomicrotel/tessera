<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDepreciationSchedule;
use App\Services\AmortizzamentoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller per il wizard di ammortamento annuale.
 *
 * Flusso:
 *   1. Dashboard: visualizza riepilogo esercizio corrente
 *   2. Genera: crea schedules in bozza per tutti i cespiti attivi
 *   3. Registra (opzionale, singolo): registra una singola quota in prima nota
 *   4. Conferma esercizio: batch — registra tutte le bozze in prima nota
 */
class AmmortamentoController extends Controller
{
    public function __construct(
        private readonly AmortizzamentoService $service
    ) {
        $this->middleware('role:admin,contabile');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard esercizio
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $tenant   = app('current_tenant');
        $esercizio = (int) ($request->esercizio ?? date('Y'));

        $riepilogo = $this->service->riepilogoEsercizio($esercizio, $tenant);

        // Schedules bozza paginate
        $bozze = AssetDepreciationSchedule::with(['asset.category'])
            ->where('esercizio', $esercizio)
            ->where('stato', AssetDepreciationSchedule::STATO_BOZZA)
            ->orderBy('asset_id')
            ->paginate(30)
            ->withQueryString();

        // Schedules definitive
        $definitive = AssetDepreciationSchedule::with(['asset.category'])
            ->where('esercizio', $esercizio)
            ->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)
            ->orderBy('asset_id')
            ->get();

        return Inertia::render('Cespiti/Ammortamento/Index', [
            'esercizio'   => $esercizio,
            'riepilogo'   => $riepilogo,
            'bozze'       => $bozze,
            'definitive'  => $definitive,
            'anni'        => range(date('Y'), (int) date('Y') - 5),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Genera bozze per l'esercizio
    // ─────────────────────────────────────────────────────────────────────────

    public function genera(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'esercizio' => 'required|integer|min:2000|max:2100',
        ]);

        $tenant    = app('current_tenant');
        $esercizio = (int) $data['esercizio'];

        try {
            $schedules = $this->service->generaRigheAnno($esercizio, $tenant);

            return redirect()
                ->route('cespiti.ammortamento.index', ['esercizio' => $esercizio])
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Generate {$schedules->count()} righe di ammortamento in bozza per l'esercizio {$esercizio}.",
                ]);
        } catch (\RuntimeException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Registra singola quota in prima nota
    // ─────────────────────────────────────────────────────────────────────────

    public function registra(Request $request, AssetDepreciationSchedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'data_registrazione' => 'nullable|date',
        ]);

        $tenant = app('current_tenant');

        try {
            $this->service->registraQuotaInPrimaNota(
                $schedule,
                $tenant,
                $data['data_registrazione'] ?? null
            );

            return back()->with('flash', [
                'type'    => 'success',
                'message' => "Quota di ammortamento {$schedule->esercizio} registrata in prima nota.",
            ]);
        } catch (\RuntimeException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Conferma batch esercizio
    // ─────────────────────────────────────────────────────────────────────────

    public function confermaEsercizio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'esercizio'          => 'required|integer|min:2000|max:2100',
            'data_registrazione' => 'nullable|date',
        ]);

        $tenant    = app('current_tenant');
        $esercizio = (int) $data['esercizio'];

        $risultato = $this->service->confermaEsercizio(
            $esercizio,
            $tenant,
            $data['data_registrazione'] ?? null
        );

        $msg = "Esercizio {$esercizio}: {$risultato['ok']} quote registrate.";
        if (! empty($risultato['errori'])) {
            $msg .= ' Errori: ' . count($risultato['errori']) . '. Controlla i log.';
        }

        return redirect()
            ->route('cespiti.ammortamento.index', ['esercizio' => $esercizio])
            ->with('flash', [
                'type'    => empty($risultato['errori']) ? 'success' : 'warning',
                'message' => $msg,
            ]);
    }
}
