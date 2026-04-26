<?php

namespace App\Http\Controllers;

use App\Models\ErogazioneLiberale;
use App\Services\ErogazioneLiberaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Gestione Erogazioni Liberali ETS.
 *
 * Art. 83 D.Lgs. 117/2017 — Le erogazioni liberali verso ETS danno diritto
 * a detrazione del 26% (persone fisiche) o 30% (enti) in sede di dichiarazione.
 * Middleware: role:admin,contabile (via route)
 */
class ErogazioneLiberaleController extends Controller
{
    public function __construct(private readonly ErogazioneLiberaleService $service) {}

    // ─────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): InertiaResponse
    {
        $tenant = app('current_tenant');
        $anno   = $request->integer('anno', now()->year - 1);

        $erogazioni = ErogazioneLiberale::where('tenant_id', $tenant->id)
            ->where('anno', $anno)
            ->orderBy('data_erogazione', 'desc')
            ->orderBy('donante_cf')
            ->paginate(25)
            ->withQueryString();

        $kpi = [
            'totale'          => (float) ErogazioneLiberale::where('tenant_id', $tenant->id)->where('anno', $anno)->sum('importo'),
            'detraibili'      => (float) ErogazioneLiberale::where('tenant_id', $tenant->id)->where('anno', $anno)->where('is_detraibile', true)->sum('importo'),
            'count'           => ErogazioneLiberale::where('tenant_id', $tenant->id)->where('anno', $anno)->count(),
            'count_detraibili' => ErogazioneLiberale::where('tenant_id', $tenant->id)->where('anno', $anno)->where('is_detraibile', true)->count(),
        ];

        return Inertia::render('Bilancio/ErogazioniLiberali/Index', [
            'erogazioni'   => $erogazioni,
            'kpi'          => $kpi,
            'annoFiltro'   => $anno,
            'anniRange'    => range(now()->year, now()->year - 5),
            'modalita'     => ErogazioneLiberale::MODALITA,
            'tipiDonante'  => ErogazioneLiberale::TIPI_DONANTE,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────

    public function create(Request $request): InertiaResponse
    {
        return Inertia::render('Bilancio/ErogazioniLiberali/Create', [
            'annoDefault'  => $request->integer('anno', now()->year - 1),
            'anniRange'    => range(now()->year, now()->year - 5),
            'modalita'     => ErogazioneLiberale::MODALITA,
            'tipiDonante'  => ErogazioneLiberale::TIPI_DONANTE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'anno'                    => 'required|integer|min:2000|max:2100',
            'donante_tipo'            => 'required|in:persona_fisica,ente',
            'donante_cf'              => 'required|string|min:11|max:16',
            'donante_piva'            => 'nullable|string|max:11',
            'donante_cognome'         => 'nullable|string|max:100',
            'donante_nome'            => 'nullable|string|max:100',
            'donante_ragione_sociale' => 'nullable|string|max:200',
            'donante_indirizzo'       => 'nullable|string|max:200',
            'donante_cap'             => 'nullable|string|max:10',
            'donante_comune'          => 'nullable|string|max:100',
            'donante_provincia'       => 'nullable|string|max:2',
            'importo'                 => 'required|numeric|min:0.01',
            'data_erogazione'         => 'required|date',
            'modalita_pagamento'      => 'required|in:' . implode(',', array_keys(ErogazioneLiberale::MODALITA)),
            'note'                    => 'nullable|string|max:1000',
        ]);

        try {
            $erogazione = $this->service->crea(app('current_tenant'), $data);

            return redirect()
                ->route('erogazioni-liberali.show', [request()->route('tenant'), $erogazione])
                ->with('flash', ['type' => 'success', 'message' => 'Erogazione liberale registrata.']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────

    public function show(ErogazioneLiberale $erogazioneLiberale): InertiaResponse
    {
        return Inertia::render('Bilancio/ErogazioniLiberali/Show', [
            'erogazione'  => $erogazioneLiberale->load('incasso'),
            'modalita'    => ErogazioneLiberale::MODALITA,
            'tipiDonante' => ErogazioneLiberale::TIPI_DONANTE,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────

    public function edit(ErogazioneLiberale $erogazioneLiberale): InertiaResponse
    {
        return Inertia::render('Bilancio/ErogazioniLiberali/Edit', [
            'erogazione'  => $erogazioneLiberale,
            'anniRange'   => range(now()->year, now()->year - 5),
            'modalita'    => ErogazioneLiberale::MODALITA,
            'tipiDonante' => ErogazioneLiberale::TIPI_DONANTE,
        ]);
    }

    public function update(Request $request, ErogazioneLiberale $erogazioneLiberale): RedirectResponse
    {
        $data = $request->validate([
            'anno'                    => 'required|integer|min:2000|max:2100',
            'donante_tipo'            => 'required|in:persona_fisica,ente',
            'donante_cf'              => 'required|string|min:11|max:16',
            'donante_piva'            => 'nullable|string|max:11',
            'donante_cognome'         => 'nullable|string|max:100',
            'donante_nome'            => 'nullable|string|max:100',
            'donante_ragione_sociale' => 'nullable|string|max:200',
            'donante_indirizzo'       => 'nullable|string|max:200',
            'donante_cap'             => 'nullable|string|max:10',
            'donante_comune'          => 'nullable|string|max:100',
            'donante_provincia'       => 'nullable|string|max:2',
            'importo'                 => 'required|numeric|min:0.01',
            'data_erogazione'         => 'required|date',
            'modalita_pagamento'      => 'required|in:' . implode(',', array_keys(ErogazioneLiberale::MODALITA)),
            'note'                    => 'nullable|string|max:1000',
        ]);

        try {
            $this->service->aggiorna($erogazioneLiberale, $data);

            return redirect()
                ->route('erogazioni-liberali.show', [request()->route('tenant'), $erogazioneLiberale])
                ->with('flash', ['type' => 'success', 'message' => 'Erogazione liberale aggiornata.']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(ErogazioneLiberale $erogazioneLiberale): RedirectResponse
    {
        $anno = $erogazioneLiberale->anno;
        $erogazioneLiberale->delete();

        return redirect()
            ->route('erogazioni-liberali.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => 'Erogazione liberale eliminata.'])
            ->with('annoRedirect', $anno);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Riepilogo annuale
    // ─────────────────────────────────────────────────────────────────────

    public function riepilogo(Request $request): InertiaResponse
    {
        $tenant = app('current_tenant');
        $anno   = $request->integer('anno', now()->year - 1);

        $riepilogo = $this->service->riepilogoAnno($tenant, $anno);

        return Inertia::render('Bilancio/ErogazioniLiberali/Riepilogo', [
            'riepilogo'  => $riepilogo,
            'anno'       => $anno,
            'anniRange'  => range(now()->year, now()->year - 5),
            'modalita'   => ErogazioneLiberale::MODALITA,
            'tipiDonante' => ErogazioneLiberale::TIPI_DONANTE,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export CSV
    // ─────────────────────────────────────────────────────────────────────

    public function exportCsv(Request $request): HttpResponse
    {
        $tenant = app('current_tenant');
        $anno   = $request->integer('anno', now()->year - 1);

        $csv      = $this->service->generaCsv($tenant, $anno);
        $filename = "ErogazioniLiberali_{$anno}.csv";

        return Response::make($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export XML
    // ─────────────────────────────────────────────────────────────────────

    public function exportXml(Request $request): HttpResponse
    {
        $tenant = app('current_tenant');
        $anno   = $request->integer('anno', now()->year - 1);

        $xml      = $this->service->generaXml($tenant, $anno);
        $filename = "ErogazioniLiberali_{$anno}.xml";

        return Response::make($xml, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Import da Incassi
    // ─────────────────────────────────────────────────────────────────────

    public function importaDaIncassi(Request $request): RedirectResponse
    {
        $anno   = $request->integer('anno', now()->year - 1);
        $tenant = app('current_tenant');

        $n = $this->service->importaDaIncassi($tenant, $anno);

        return redirect()
            ->route('erogazioni-liberali.index', [$tenant, 'anno' => $anno])
            ->with('flash', [
                'type'    => 'success',
                'message' => $n > 0
                    ? "{$n} donazioni importate da Incassi. Completa i dati fiscali."
                    : 'Nessuna nuova donazione da importare.',
            ]);
    }
}
