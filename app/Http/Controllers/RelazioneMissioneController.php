<?php

namespace App\Http\Controllers;

use App\Models\RelazioneMissione;
use App\Services\RelazioneMissioneService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestione Relazione di Missione ETS.
 *
 * Art. 13 D.Lgs. 117/2017 — documento obbligatorio per tutti gli ETS.
 * Middleware: role:admin,contabile (via route)
 */
class RelazioneMissioneController extends Controller
{
    public function __construct(private readonly RelazioneMissioneService $service) {}

    // ─────────────────────────────────────────────────────────────────────
    // Index (lista relazioni per anno)
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $relazioni = RelazioneMissione::where('tenant_id', app('current_tenant')->id)
            ->orderByDesc('anno')
            ->get();

        return Inertia::render('Bilancio/RelazioneMissione/Index', [
            'relazioni'  => $relazioni,
            'statiLabel' => RelazioneMissione::STATI,
            'anniRange'  => range(now()->year, now()->year - 5),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────

    public function create(Request $request): Response
    {
        $anno      = $request->integer('anno', now()->year - 1); // default anno precedente
        $tenant    = app('current_tenant');
        $variabili = $this->service->raccogliVariabili($tenant, $anno);

        // Controlla se esiste già
        $esistente = RelazioneMissione::where('tenant_id', $tenant->id)
            ->where('anno', $anno)
            ->first();

        return Inertia::render('Bilancio/RelazioneMissione/Create', [
            'anno'        => $anno,
            'anniRange'   => range(now()->year, now()->year - 5),
            'variabili'   => $variabili,
            'sezioniDefault' => RelazioneMissione::SEZIONI_DEFAULT,
            'statiLabel'  => RelazioneMissione::STATI,
            'esistente'   => $esistente ? $esistente->id : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'anno'               => 'required|integer|min:2000|max:2100',
            'organo_approvante'  => 'nullable|string|max:200',
            'data_approvazione'  => 'nullable|date',
            'luogo_approvazione' => 'nullable|string|max:200',
            'note_interne'       => 'nullable|string|max:2000',
        ]);

        try {
            $relazione = $this->service->crea(app('current_tenant'), $data['anno'], $data);

            return redirect()
                ->route('relazione-missione.edit', [request()->route('tenant'), $relazione])
                ->with('flash', ['type' => 'success', 'message' => "Relazione di missione {$data['anno']} creata. Compila le sezioni."]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────

    public function show(RelazioneMissione $relazioneMissione): Response
    {
        $tenant    = app('current_tenant');
        $variabili = $relazioneMissione->variabili_snapshot
            ?? $this->service->raccogliVariabili($tenant, $relazioneMissione->anno);

        // Interpola variabili nel testo di ogni sezione per la preview
        $sezioniInterpolate = collect($relazioneMissione->sezioni ?? [])->map(function ($sez) use ($variabili) {
            return [
                ...$sez,
                'testo_interpolato' => $this->service->interpolaVariabili($sez['testo'] ?? '', $variabili),
            ];
        })->all();

        return Inertia::render('Bilancio/RelazioneMissione/Show', [
            'relazione'           => $relazioneMissione,
            'sezioniInterpolate'  => $sezioniInterpolate,
            'variabili'           => $variabili,
            'statiLabel'          => RelazioneMissione::STATI,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────

    public function edit(RelazioneMissione $relazioneMissione): Response|RedirectResponse
    {
        if ($relazioneMissione->isApprovata()) {
            return redirect()
                ->route('relazione-missione.show', [request()->route('tenant'), $relazioneMissione])
                ->with('flash', ['type' => 'warning', 'message' => 'La relazione è già approvata: non modificabile.']);
        }

        $tenant    = app('current_tenant');
        $variabili = $this->service->raccogliVariabili($tenant, $relazioneMissione->anno);

        return Inertia::render('Bilancio/RelazioneMissione/Edit', [
            'relazione'  => $relazioneMissione,
            'variabili'  => $variabili,
            'statiLabel' => RelazioneMissione::STATI,
            'placeholder_list' => $this->placeholderList(),
        ]);
    }

    public function update(Request $request, RelazioneMissione $relazioneMissione): RedirectResponse
    {
        $data = $request->validate([
            'stato'               => 'required|in:bozza,definitiva,approvata',
            'organo_approvante'   => 'nullable|string|max:200',
            'data_approvazione'   => 'nullable|date',
            'luogo_approvazione'  => 'nullable|string|max:200',
            'note_interne'        => 'nullable|string|max:2000',
            'sezioni'             => 'nullable|array',
            'sezioni.*.id'        => 'nullable|string|max:50',
            'sezioni.*.titolo'    => 'required_with:sezioni|string|max:300',
            'sezioni.*.testo'     => 'nullable|string',
        ]);

        try {
            $this->service->aggiorna($relazioneMissione, $data);

            return redirect()
                ->route('relazione-missione.show', [request()->route('tenant'), $relazioneMissione])
                ->with('flash', ['type' => 'success', 'message' => 'Relazione di missione salvata.']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Approva
    // ─────────────────────────────────────────────────────────────────────

    public function approva(Request $request, RelazioneMissione $relazioneMissione): RedirectResponse
    {
        $data = $request->validate([
            'data_approvazione'  => 'required|date',
            'organo_approvante'  => 'required|string|max:200',
            'luogo_approvazione' => 'nullable|string|max:200',
        ]);

        try {
            $this->service->approva(
                $relazioneMissione,
                $data['data_approvazione'],
                $data['organo_approvante'],
                $data['luogo_approvazione'] ?? null,
            );

            return redirect()
                ->route('relazione-missione.show', [request()->route('tenant'), $relazioneMissione])
                ->with('flash', ['type' => 'success', 'message' => 'Relazione approvata.']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export PDF
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdf(RelazioneMissione $relazioneMissione): HttpResponse
    {
        $tenant    = app('current_tenant');
        $variabili = $relazioneMissione->variabili_snapshot
            ?? $this->service->raccogliVariabili($tenant, $relazioneMissione->anno);

        $sezioniInterpolate = collect($relazioneMissione->sezioni ?? [])->map(function ($sez) use ($variabili) {
            return [
                ...$sez,
                'testo' => $this->service->interpolaVariabili($sez['testo'] ?? '', $variabili),
            ];
        })->all();

        $pdf = Pdf::loadView('pdf.relazione-missione', [
            'relazione'          => $relazioneMissione,
            'sezioniInterpolate' => $sezioniInterpolate,
            'variabili'          => $variabili,
            'tenant'             => $tenant,
        ])->setPaper('A4', 'portrait');

        $filename = "RelazioneMissione_{$relazioneMissione->anno}.pdf";

        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(RelazioneMissione $relazioneMissione): RedirectResponse
    {
        if ($relazioneMissione->isApprovata()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Impossibile eliminare una relazione già approvata.',
            ]);
        }

        $anno = $relazioneMissione->anno;
        $relazioneMissione->delete();

        return redirect()
            ->route('relazione-missione.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => "Relazione di missione {$anno} eliminata."]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /** Lista dei placeholder disponibili per l'editor. */
    private function placeholderList(): array
    {
        return [
            ['key' => 'anno',                'label' => 'Anno di riferimento'],
            ['key' => 'nome_ente',           'label' => "Nome dell'ente"],
            ['key' => 'codice_fiscale_ente', 'label' => "Codice fiscale"],
            ['key' => 'indirizzo_ente',      'label' => 'Indirizzo sede'],
            ['key' => 'totale_soci',         'label' => 'Totale soci attivi'],
            ['key' => 'nuovi_soci',          'label' => 'Nuovi soci anno'],
            ['key' => 'soci_cessati',        'label' => 'Soci cessati anno'],
            ['key' => 'totale_entrate',      'label' => 'Totale entrate (€)'],
            ['key' => 'totale_uscite',       'label' => 'Totale uscite (€)'],
            ['key' => 'risultato_esercizio', 'label' => 'Risultato di esercizio (€)'],
            ['key' => 'quote_associative',   'label' => 'Quote associative riscosse (€)'],
            ['key' => 'donazioni_ricevute',  'label' => 'Donazioni/liberalità ricevute (€)'],
            ['key' => 'compensi_terzi',      'label' => 'Compensi a terzi corrisposti (€)'],
            ['key' => 'ritenute_versate',    'label' => 'Ritenute d\'acconto versate (€)'],
            ['key' => 'numero_eventi',       'label' => 'Numero eventi/iniziative'],
        ];
    }
}
