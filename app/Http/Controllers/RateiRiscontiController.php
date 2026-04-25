<?php

namespace App\Http\Controllers;

use App\Models\ContoContabile;
use App\Models\EsercizioContabile;
use App\Models\RateoRisconto;
use App\Services\RateiRiscontiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestisce ratei e risconti — scritture di rettifica infrannuali per competenza.
 *
 * Middleware: role:admin,contabile
 */
class RateiRiscontiController extends Controller
{
    public function __construct(
        private readonly RateiRiscontiService $service,
    ) {
        $this->middleware('role:admin,contabile');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $annoCorrente = (int) ($request->query('anno') ?? date('Y'));

        $ratei = RateoRisconto::where('tenant_id', app('current_tenant')->id)
            ->perAnno($annoCorrente)
            ->with(['contoEconomico:id,codice,descrizione', 'contoRettifica:id,codice,descrizione'])
            ->orderBy('tipo')
            ->orderBy('data_inizio')
            ->get()
            ->map(fn (RateoRisconto $r) => [
                'id'                 => $r->id,
                'anno_esercizio'     => $r->anno_esercizio,
                'tipo'               => $r->tipo,
                'label_tipo'         => $r->labelTipo(),
                'descrizione'        => $r->descrizione,
                'importo_totale'     => $r->importo_totale,
                'quota_esercizio'    => $r->quota_esercizio,
                'data_inizio'        => $r->data_inizio->toDateString(),
                'data_fine'          => $r->data_fine->toDateString(),
                'conto_economico'    => $r->contoEconomico
                    ? "{$r->contoEconomico->codice} — {$r->contoEconomico->descrizione}"
                    : null,
                'conto_rettifica'    => $r->contoRettifica
                    ? "{$r->contoRettifica->codice} — {$r->contoRettifica->descrizione}"
                    : null,
                'conto_economico_id' => $r->conto_economico_id,
                'conto_rettifica_id' => $r->conto_rettifica_id,
                'stato'              => $r->stato,
                'movimento_id'       => $r->movimento_id,
                'storno_id'          => $r->storno_id,
                'note'               => $r->note,
            ]);

        // Conti CE (costi e ricavi) per il selettore
        $contiCe = ContoContabile::attivi()
            ->movimentabili()
            ->whereIn('natura', [ContoContabile::NATURA_COSTO, ContoContabile::NATURA_RICAVO])
            ->orderBy('codice')
            ->get(['id', 'codice', 'descrizione', 'natura']);

        // Conti SP di rettifica (attivo/passivo/transitorio)
        $contiSp = ContoContabile::attivi()
            ->movimentabili()
            ->whereIn('natura', [
                ContoContabile::NATURA_ATTIVO,
                ContoContabile::NATURA_PASSIVO,
                ContoContabile::NATURA_TRANSITORIO,
            ])
            ->orderBy('codice')
            ->get(['id', 'codice', 'descrizione', 'natura']);

        // Anni disponibili per il filtro
        $anni = RateoRisconto::where('tenant_id', app('current_tenant')->id)
            ->distinct()
            ->orderByDesc('anno_esercizio')
            ->pluck('anno_esercizio')
            ->toArray();

        if (! in_array($annoCorrente, $anni, true)) {
            array_unshift($anni, $annoCorrente);
        }

        return Inertia::render('Contabilita/RateiRisconti/Index', [
            'ratei'        => $ratei,
            'conti_ce'     => $contiCe,
            'conti_sp'     => $contiSp,
            'anno'         => $annoCorrente,
            'anni'         => $anni,
            'tipi'         => RateoRisconto::TIPI,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'anno_esercizio'     => 'required|integer|min:2000|max:2100',
            'tipo'               => 'required|in:' . implode(',', RateoRisconto::TIPI),
            'descrizione'        => 'required|string|max:250',
            'importo_totale'     => 'nullable|numeric|min:0',
            'quota_esercizio'    => 'nullable|numeric|min:0.01',
            'data_inizio'        => 'required|date',
            'data_fine'          => 'required|date|after_or_equal:data_inizio',
            'conto_economico_id' => 'required|exists:conti_contabili,id',
            'conto_rettifica_id' => 'required|exists:conti_contabili,id',
            'note'               => 'nullable|string|max:1000',
        ]);

        try {
            $this->service->crea(app('current_tenant'), $data);

            return redirect()
                ->route('ratei-risconti.index', [
                    request()->route('tenant'),
                    'anno' => $data['anno_esercizio'],
                ])
                ->with('flash', [
                    'type'    => 'success',
                    'message' => 'Rateo/risconto creato correttamente.',
                ]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────

    public function update(Request $request, RateoRisconto $rateoRisconto): RedirectResponse
    {
        $data = $request->validate([
            'descrizione'        => 'required|string|max:250',
            'importo_totale'     => 'nullable|numeric|min:0',
            'quota_esercizio'    => 'nullable|numeric|min:0.01',
            'data_inizio'        => 'required|date',
            'data_fine'          => 'required|date|after_or_equal:data_inizio',
            'conto_economico_id' => 'required|exists:conti_contabili,id',
            'conto_rettifica_id' => 'required|exists:conti_contabili,id',
            'note'               => 'nullable|string|max:1000',
        ]);

        try {
            $this->service->aggiorna($rateoRisconto, $data);

            return redirect()
                ->route('ratei-risconti.index', [
                    request()->route('tenant'),
                    'anno' => $rateoRisconto->anno_esercizio,
                ])
                ->with('flash', [
                    'type'    => 'success',
                    'message' => 'Rateo/risconto aggiornato.',
                ]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Registra
    // ─────────────────────────────────────────────────────────────────────

    public function registra(RateoRisconto $rateoRisconto): RedirectResponse
    {
        try {
            $this->service->registra($rateoRisconto);

            return redirect()
                ->route('ratei-risconti.index', [
                    request()->route('tenant'),
                    'anno' => $rateoRisconto->anno_esercizio,
                ])
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Rateo/risconto \"{$rateoRisconto->descrizione}\" registrato in contabilità.",
                ]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Registra batch
    // ─────────────────────────────────────────────────────────────────────

    public function registraBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'anno' => 'required|integer|min:2000|max:2100',
        ]);

        $risultato = $this->service->registraBatch(app('current_tenant'), (int) $data['anno']);

        $msg = "Registrati {$risultato['registrati']} ratei/risconti.";
        if (! empty($risultato['errori'])) {
            $msg .= ' Errori: ' . implode('; ', $risultato['errori']);
        }

        return redirect()
            ->route('ratei-risconti.index', [
                request()->route('tenant'),
                'anno' => $data['anno'],
            ])
            ->with('flash', [
                'type'    => empty($risultato['errori']) ? 'success' : 'warning',
                'message' => $msg,
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Storna
    // ─────────────────────────────────────────────────────────────────────

    public function storna(RateoRisconto $rateoRisconto): RedirectResponse
    {
        try {
            $this->service->storna($rateoRisconto);

            return redirect()
                ->route('ratei-risconti.index', [
                    request()->route('tenant'),
                    'anno' => $rateoRisconto->anno_esercizio,
                ])
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Storno \"{$rateoRisconto->descrizione}\" generato per l'anno " . ($rateoRisconto->anno_esercizio + 1) . '.',
                ]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(RateoRisconto $rateoRisconto): RedirectResponse
    {
        $anno = $rateoRisconto->anno_esercizio;

        try {
            $this->service->elimina($rateoRisconto);

            return redirect()
                ->route('ratei-risconti.index', [
                    request()->route('tenant'),
                    'anno' => $anno,
                ])
                ->with('flash', [
                    'type'    => 'success',
                    'message' => 'Rateo/risconto eliminato.',
                ]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
