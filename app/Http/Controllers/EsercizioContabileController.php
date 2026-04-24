<?php

namespace App\Http\Controllers;

use App\Models\ContoContabile;
use App\Models\EsercizioContabile;
use App\Models\MovimentoContabile;
use App\Services\AperturaChiusuraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestisce il ciclo di vita degli esercizi contabili.
 *
 * Operazioni disponibili:
 *  - index: elenco esercizi con stato e riepilogo movimenti
 *  - store: apre/registra un nuovo esercizio
 *  - update: aggiorna configurazione conti di chiusura
 *  - close: esegue la procedura di chiusura (lock + scritture)
 *  - reopen: riapre un esercizio chiuso (solo il più recente)
 *  - destroy: elimina un esercizio vuoto (nessun movimento)
 *
 * Middleware: role:admin,contabile
 */
class EsercizioContabileController extends Controller
{
    public function __construct(
        private readonly AperturaChiusuraService $service,
    ) {
        $this->middleware('role:admin,contabile');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────

    public function index(): Response
    {
        $esercizi = EsercizioContabile::orderBy('anno', 'desc')
            ->with(['contoChiusuraCe:id,codice,descrizione', 'contoApertura:id,codice,descrizione'])
            ->get()
            ->map(function (EsercizioContabile $e) {
                $numMovimenti = MovimentoContabile::where('anno_esercizio', $e->anno)
                    ->count();

                return [
                    'id'                   => $e->id,
                    'anno'                 => $e->anno,
                    'stato'                => $e->stato,
                    'data_apertura'        => $e->data_apertura?->toDateString(),
                    'data_chiusura'        => $e->data_chiusura?->toDateString(),
                    'locked_at'            => $e->locked_at?->toDateTimeString(),
                    'conto_chiusura_ce'    => $e->contoChiusuraCe
                        ? "{$e->contoChiusuraCe->codice} — {$e->contoChiusuraCe->descrizione}"
                        : null,
                    'conto_apertura'       => $e->contoApertura
                        ? "{$e->contoApertura->codice} — {$e->contoApertura->descrizione}"
                        : null,
                    'num_movimenti'        => $numMovimenti,
                    'configurato'          => $e->isConfiguratoPerChiusura(),
                    'note'                 => $e->note,
                ];
            });

        // Conti movimentabili per la selezione dei conti di chiusura
        $conti = ContoContabile::attivi()
            ->movimentabili()
            ->orderBy('codice')
            ->get(['id', 'codice', 'descrizione', 'natura']);

        $annoCorrente = (int) date('Y');
        $esercizioCorrente = EsercizioContabile::perAnno($annoCorrente)->first();

        return Inertia::render('EsercizioContabile/Index', [
            'esercizi'          => $esercizi,
            'conti'             => $conti,
            'anno_corrente'     => $annoCorrente,
            'esercizio_aperto'  => $esercizioCorrente?->anno,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Store (apri esercizio)
    // ─────────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'anno' => 'required|integer|min:2000|max:2100',
        ]);

        $tenant = app('current_tenant');

        $esercizio = $this->service->apriEsercizio($tenant, (int) $data['anno']);

        return redirect()
            ->route('esercizi.index', $tenant)
            ->with('flash', [
                'type'    => 'success',
                'message' => "Esercizio {$esercizio->anno} registrato.",
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Update (configura conti di chiusura)
    // ─────────────────────────────────────────────────────────────────────

    public function update(Request $request, EsercizioContabile $esercizio): RedirectResponse
    {
        if ($esercizio->isChiuso()) {
            return redirect()->back()->with('flash', [
                'type'    => 'warning',
                'message' => "L'esercizio {$esercizio->anno} è già chiuso e non può essere modificato.",
            ]);
        }

        $data = $request->validate([
            'conto_chiusura_ce_id' => 'nullable|exists:conti_contabili,id',
            'conto_apertura_id'    => 'nullable|exists:conti_contabili,id',
            'note'                 => 'nullable|string|max:1000',
        ]);

        $esercizio->update($data);

        return redirect()
            ->route('esercizi.index', request()->route('tenant'))
            ->with('flash', [
                'type'    => 'success',
                'message' => "Configurazione esercizio {$esercizio->anno} aggiornata.",
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Close (chiudi esercizio)
    // ─────────────────────────────────────────────────────────────────────

    public function close(Request $request, EsercizioContabile $esercizio): RedirectResponse
    {
        try {
            $this->service->chiudiEsercizio($esercizio);

            return redirect()
                ->route('esercizi.index', request()->route('tenant'))
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Esercizio {$esercizio->anno} chiuso correttamente. Movimenti bloccati.",
                ]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reopen (riapri esercizio)
    // ─────────────────────────────────────────────────────────────────────

    public function reopen(EsercizioContabile $esercizio): RedirectResponse
    {
        try {
            $this->service->riaperiEsercizio($esercizio);

            return redirect()
                ->route('esercizi.index', request()->route('tenant'))
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Esercizio {$esercizio->anno} riaperto. Movimenti sbloccati.",
                ]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Destroy (elimina esercizio senza movimenti)
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(EsercizioContabile $esercizio): RedirectResponse
    {
        if ($esercizio->isChiuso()) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => "Impossibile eliminare un esercizio già chiuso.",
            ]);
        }

        $numMovimenti = MovimentoContabile::where('anno_esercizio', $esercizio->anno)->count();
        if ($numMovimenti > 0) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => "Impossibile eliminare l'esercizio {$esercizio->anno}: ha {$numMovimenti} movimenti.",
            ]);
        }

        $anno = $esercizio->anno;
        $esercizio->delete();

        return redirect()
            ->route('esercizi.index', request()->route('tenant'))
            ->with('flash', [
                'type'    => 'success',
                'message' => "Esercizio {$anno} eliminato.",
            ]);
    }
}
