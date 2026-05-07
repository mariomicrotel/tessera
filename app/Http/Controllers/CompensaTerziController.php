<?php

namespace App\Http\Controllers;

use App\Models\CompensaTerzi;
use App\Models\ContoContabile;
use App\Models\Member;
use App\Models\Settings;
use App\Models\VersamentoRitenuta;
use App\Services\CompensaTerziService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestione compensi a terzi e ritenute d'acconto.
 *
 * Middleware: role:admin,contabile (via route)
 */
class CompensaTerziController extends Controller
{
    public function __construct(private readonly CompensaTerziService $service) {}

    // ─────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $anno       = $request->integer('anno', now()->year);
        $stato      = $request->string('stato')->toString();
        $search     = $request->string('search')->toString();

        $compensi = CompensaTerzi::query()
            ->with('member')
            ->where('anno_competenza', $anno)
            ->when($stato, fn ($q) => $q->where('stato_ritenuta', $stato))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('nome_percipiente', 'like', "%{$search}%")
                    ->orWhere('codice_fiscale', 'like', "%{$search}%");
            }))
            ->orderByDesc('data_pagamento')
            ->paginate(25)
            ->withQueryString();

        // KPI
        $base = CompensaTerzi::where('tenant_id', app('current_tenant')->id)
            ->where('anno_competenza', $anno);

        $kpi = [
            'totale_compensi'   => (float) (clone $base)->sum('compenso_lordo'),
            'totale_ritenute'   => (float) (clone $base)->sum('ritenuta'),
            'ritenute_da_versare' => (float) (clone $base)
                ->where('stato_ritenuta', CompensaTerzi::STATO_DA_VERSARE)->sum('ritenuta'),
        ];

        return Inertia::render('CompensaTerzi/Index', [
            'compensi'    => $compensi,
            'kpi'         => $kpi,
            'filters'     => compact('anno', 'stato', 'search'),
            'anni'        => range(now()->year, now()->year - 5),
            'statiLabel'  => [
                CompensaTerzi::STATO_DA_VERSARE => 'Da versare',
                CompensaTerzi::STATO_VERSATA    => 'Versata',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────

    public function create(): Response
    {
        return Inertia::render('CompensaTerzi/Create', [
            'membri'         => Member::orderBy('cognome')->orderBy('nome')
                ->get(['id', 'nome', 'cognome', 'ragione_sociale', 'codice_fiscale', 'partita_iva']),
            'contiCosto'     => ContoContabile::attivi()->movimentabili()
                ->whereIn('natura', [ContoContabile::NATURA_COSTO])
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
            'contiPassivo'   => ContoContabile::attivi()->movimentabili()
                ->whereIn('natura', [ContoContabile::NATURA_PASSIVO, ContoContabile::NATURA_TRANSITORIO])
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
            'tipiRapporto'   => CompensaTerzi::TIPI_RAPPORTO,
            'causali'        => CompensaTerzi::CAUSALI,
            'annoCorrente'   => now()->year,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id'                    => 'nullable|exists:members,id',
            'nome_percipiente'             => 'required|string|max:150',
            'codice_fiscale'               => 'required|string|max:16',
            'partita_iva'                  => 'nullable|string|max:11',
            'indirizzo'                    => 'nullable|string|max:255',
            'tipo_rapporto'                => 'required|in:occasionale,professionale,provvigioni',
            'codice_causale'               => 'required|in:A,Q,R,V',
            'anno_competenza'              => 'required|integer|min:2000|max:2100',
            'data_pagamento'               => 'required|date',
            'causale_prestazione'          => 'required|string|max:500',
            'compenso_lordo'               => 'required|numeric|min:0.01',
            'base_imponibile_ritenuta'     => 'nullable|numeric|min:0',
            'aliquota_ritenuta'            => 'nullable|numeric|min:0|max:100',
            'rimborsi_spese'               => 'nullable|numeric|min:0',
            'contributo_inps_beneficiario' => 'nullable|numeric|min:0',
            'contributo_inps_committente'  => 'nullable|numeric|min:0',
            'conto_costo_id'               => 'nullable|exists:conti_contabili,id',
            'conto_ritenute_id'            => 'nullable|exists:conti_contabili,id',
            'note'                         => 'nullable|string|max:1000',
        ]);

        try {
            $compenso = $this->service->crea(app('current_tenant'), $data);

            return redirect()
                ->route('compensi-terzi.show', [request()->route('tenant'), $compenso])
                ->with('flash', ['type' => 'success', 'message' => "Compenso registrato per {$compenso->nome_percipiente}."]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────

    public function show(CompensaTerzi $compensiTerzi): Response
    {
        $compensiTerzi->load(['member', 'contoCosto', 'contoRitenute', 'versamento', 'movimento']);

        return Inertia::render('CompensaTerzi/Show', [
            'compenso' => $compensiTerzi,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────

    public function edit(CompensaTerzi $compensiTerzi): Response|RedirectResponse
    {
        if ($compensiTerzi->isVersata()) {
            return redirect()
                ->route('compensi-terzi.show', [request()->route('tenant'), $compensiTerzi])
                ->with('flash', ['type' => 'warning', 'message' => 'Ritenuta già versata: impossibile modificare.']);
        }

        return Inertia::render('CompensaTerzi/Edit', [
            'compenso'       => $compensiTerzi->load('member'),
            'membri'         => Member::orderBy('cognome')->orderBy('nome')
                ->get(['id', 'nome', 'cognome', 'ragione_sociale', 'codice_fiscale', 'partita_iva']),
            'contiCosto'     => ContoContabile::attivi()->movimentabili()
                ->whereIn('natura', [ContoContabile::NATURA_COSTO])
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
            'contiPassivo'   => ContoContabile::attivi()->movimentabili()
                ->whereIn('natura', [ContoContabile::NATURA_PASSIVO, ContoContabile::NATURA_TRANSITORIO])
                ->orderBy('codice')
                ->get(['id', 'codice', 'descrizione']),
            'tipiRapporto'   => CompensaTerzi::TIPI_RAPPORTO,
            'causali'        => CompensaTerzi::CAUSALI,
        ]);
    }

    public function update(Request $request, CompensaTerzi $compensiTerzi): RedirectResponse
    {
        $data = $request->validate([
            'member_id'                    => 'nullable|exists:members,id',
            'nome_percipiente'             => 'required|string|max:150',
            'codice_fiscale'               => 'required|string|max:16',
            'partita_iva'                  => 'nullable|string|max:11',
            'indirizzo'                    => 'nullable|string|max:255',
            'tipo_rapporto'                => 'required|in:occasionale,professionale,provvigioni',
            'codice_causale'               => 'required|in:A,Q,R,V',
            'data_pagamento'               => 'required|date',
            'causale_prestazione'          => 'required|string|max:500',
            'compenso_lordo'               => 'required|numeric|min:0.01',
            'base_imponibile_ritenuta'     => 'nullable|numeric|min:0',
            'aliquota_ritenuta'            => 'nullable|numeric|min:0|max:100',
            'rimborsi_spese'               => 'nullable|numeric|min:0',
            'contributo_inps_beneficiario' => 'nullable|numeric|min:0',
            'contributo_inps_committente'  => 'nullable|numeric|min:0',
            'conto_costo_id'               => 'nullable|exists:conti_contabili,id',
            'conto_ritenute_id'            => 'nullable|exists:conti_contabili,id',
            'note'                         => 'nullable|string|max:1000',
        ]);

        try {
            $this->service->aggiorna($compensiTerzi, $data);

            return redirect()
                ->route('compensi-terzi.show', [request()->route('tenant'), $compensiTerzi])
                ->with('flash', ['type' => 'success', 'message' => 'Compenso aggiornato.']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(CompensaTerzi $compensiTerzi): RedirectResponse
    {
        if ($compensiTerzi->isVersata()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Impossibile eliminare: la ritenuta è già stata versata.',
            ]);
        }

        $nome = $compensiTerzi->nome_percipiente;
        $compensiTerzi->delete();

        return redirect()
            ->route('compensi-terzi.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => "Compenso di {$nome} eliminato."]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Versa ritenute del mese
    // ─────────────────────────────────────────────────────────────────────

    public function versa(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mese'             => 'required|integer|min:1|max:12',
            'anno'             => 'required|integer|min:2000|max:2100',
            'data_versamento'  => 'required|date',
            'codice_tributo'   => 'nullable|string|max:10',
            'codice_ufficio'   => 'nullable|string|max:4',
            'codice_atto'      => 'nullable|string|max:10',
            'note'             => 'nullable|string|max:500',
        ]);

        try {
            $versamento = $this->service->versaRitenute(app('current_tenant'), $data);

            return redirect()
                ->route('compensi-terzi.versamento.show', [request()->route('tenant'), $versamento])
                ->with('flash', [
                    'type'    => 'success',
                    'message' => "Versamento di € " . number_format($versamento->importo_totale, 2, ',', '.') . " registrato.",
                ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Riepilogo annuale (CU)
    // ─────────────────────────────────────────────────────────────────────

    public function riepilogo(Request $request): Response
    {
        $anno = $request->integer('anno', now()->year);

        $riepilogo = $this->service->riepilogoAnnuale(app('current_tenant'), $anno);

        return Inertia::render('CompensaTerzi/Riepilogo', [
            'riepilogo' => $riepilogo,
            'anno'      => $anno,
            'anni'      => range(now()->year, now()->year - 5),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Versamento show
    // ─────────────────────────────────────────────────────────────────────

    public function versamentoShow(VersamentoRitenuta $versamento): Response
    {
        $versamento->load('compensi.member');

        return Inertia::render('CompensaTerzi/VersamentoShow', [
            'versamento' => $versamento,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────
    // Certificazione Unica
    // ─────────────────────────────────────────────────────────────────────

    public function generaCU(Request $request): HttpResponse
    {
        $anno = $request->integer('anno', now()->year);

        $tenant = app('current_tenant');

        $compensi = CompensaTerzi::where('anno_competenza', $anno)
            ->orderBy('nome_percipiente')
            ->get();

        if ($compensi->isEmpty()) {
            abort(422, "Nessun compenso trovato per l'anno {$anno}.");
        }

        // Raggruppa per percipiente (codice_fiscale)
        $grouped = $compensi->groupBy('codice_fiscale');

        $tipiLabel = [
            'occasionale'    => 'Lavoro autonomo occasionale',
            'professionale'  => 'Lavoro autonomo professionale',
            'provvigioni'    => 'Provvigioni/Agenti',
        ];

        $percipienti = $grouped->map(function ($items) use ($tipiLabel) {
            $first = $items->first();
            return [
                'nome_percipiente'      => $first->nome_percipiente,
                'codice_fiscale'        => $first->codice_fiscale,
                'partita_iva'           => $first->partita_iva,
                'indirizzo'             => $first->indirizzo,
                'codice_causale'        => $first->codice_causale,
                'tipo_rapporto_label'   => $tipiLabel[$first->tipo_rapporto] ?? $first->tipo_rapporto,
                'compenso_lordo'        => $items->sum('compenso_lordo'),
                'base_imponibile_ritenuta' => $items->sum('base_imponibile_ritenuta'),
                'aliquota_ritenuta'     => $first->aliquota_ritenuta ?? 20,
                'totale_ritenuta'       => $items->sum('ritenuta'),
                'rimborsi_spese'        => $items->sum('rimborsi_spese'),
                'netto_erogato'         => $items->sum('compenso_lordo') - $items->sum('ritenuta'),
                'prestazioni'           => $items->map(fn ($c) => [
                    'data'     => $c->data_pagamento?->format('d/m/Y') ?? '—',
                    'causale'  => $c->causale_prestazione,
                    'lordo'    => (float) $c->compenso_lordo,
                    'ritenuta' => (float) $c->ritenuta,
                ])->values()->all(),
            ];
        })->values()->all();

        $sostituto = [
            'nome'            => Settings::get('nome_associazione', config('app.name')),
            'codice_fiscale'  => Settings::get('codice_fiscale_associazione', ''),
            'indirizzo'       => Settings::get('indirizzo_associazione', ''),
            'luogo'           => Settings::get('luogo_emissione_ricevute', ''),
        ];

        $pdf = Pdf::loadView('fiscale.certificazione-unica', compact('percipienti', 'sostituto', 'anno'))
            ->setPaper('A4', 'portrait');

        return $pdf->download("certificazione_unica_{$anno}.pdf");
    }

    // Lista versamenti
    // ─────────────────────────────────────────────────────────────────────

    public function versamenti(Request $request): Response
    {
        $anno = $request->integer('anno', now()->year);

        $versamenti = VersamentoRitenuta::where('tenant_id', app('current_tenant')->id)
            ->where('anno_riferimento', $anno)
            ->withCount('compensi')
            ->orderByDesc('data_versamento')
            ->get();

        return Inertia::render('CompensaTerzi/Versamenti', [
            'versamenti' => $versamenti,
            'anno'       => $anno,
            'anni'       => range(now()->year, now()->year - 5),
        ]);
    }
}
