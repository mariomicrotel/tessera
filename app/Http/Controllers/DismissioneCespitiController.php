<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Services\DismissioneCespitiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller per la dismissione e vendita di cespiti.
 *
 * Endpoint:
 *   POST /cespiti/{asset}/dismetti         → registra la dismissione
 *   POST /cespiti/{asset}/preview-dismissione → calcola plus/minus senza salvare
 */
class DismissioneCespitiController extends Controller
{
    public function __construct(
        private readonly DismissioneCespitiService $service
    ) {
        $this->middleware('role:admin,contabile');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Preview (JSON — usato da Show.vue per calcolo in tempo reale)
    // ─────────────────────────────────────────────────────────────────────────

    public function preview(Request $request, Asset $asset): JsonResponse
    {
        $dati = $request->validate([
            'valore_realizzo'  => 'required|numeric|min:0',
            'data_dismissione' => 'required|date',
        ]);

        $result = $this->service->preview(
            $asset,
            (float) $dati['valore_realizzo'],
            $dati['data_dismissione']
        );

        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store — registra la dismissione
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $dati = $request->validate([
            'tipo'             => 'required|in:vendita,rottamazione,donazione,furto',
            'data_dismissione' => 'required|date',
            'valore_realizzo'  => 'required|numeric|min:0',
            'note'             => 'nullable|string|max:1000',
        ]);

        $tenant = app('current_tenant');

        try {
            $disposal = $this->service->dismetti($asset, $tenant, $dati);

            $tipoLabel = AssetDisposal::TIPI_LABEL[$dati['tipo']] ?? $dati['tipo'];

            return redirect()
                ->route('cespiti.show', $asset)
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Cespite '{$asset->name}' dismesso ({$tipoLabel}) con successo.",
                ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
