<?php

namespace App\Http\Controllers;

use App\Models\LiquidazioneIva;
use App\Models\ModelloF24;
use App\Models\VersamentoRitenuta;
use App\Services\ModelloF24Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestione Modelli F24.
 *
 * Middleware: role:admin,contabile (via route)
 */
class ModelloF24Controller extends Controller
{
    public function __construct(private readonly ModelloF24Service $service) {}

    // ─────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $anno   = $request->integer('anno', now()->year);
        $stato  = $request->string('stato')->toString();

        $modelli = ModelloF24::query()
            ->with(['righe', 'liquidazioneIva', 'versamentoRitenuta'])
            ->where('anno', $anno)
            ->when($stato, fn ($q) => $q->where('stato', $stato))
            ->orderByDesc('data_compilazione')
            ->paginate(25)
            ->withQueryString();

        // KPI
        $base = ModelloF24::where('tenant_id', app('current_tenant')->id)->where('anno', $anno);

        $kpi = [
            'totale_debiti'   => (float) (clone $base)->sum('totale_debiti'),
            'totale_crediti'  => (float) (clone $base)->sum('totale_crediti'),
            'saldo_da_versare'=> (float) (clone $base)
                ->whereIn('stato', [ModelloF24::STATO_BOZZA, ModelloF24::STATO_COMPILATO])
                ->where('saldo', '>', 0)
                ->sum('saldo'),
        ];

        return Inertia::render('F24/Index', [
            'modelli'    => $modelli,
            'kpi'        => $kpi,
            'filters'    => compact('anno', 'stato'),
            'anni'       => range(now()->year, now()->year - 5),
            'statiLabel' => ModelloF24::STATI,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Create / Store — Manuale
    // ─────────────────────────────────────────────────────────────────────

    public function create(): Response
    {
        $tenant = app('current_tenant');

        // Liquidazioni IVA definitive non ancora versate, con saldo > 0
        $liquidazioni = LiquidazioneIva::where('tenant_id', $tenant->id)
            ->where('status', LiquidazioneIva::STATUS_DEFINITIVA)
            ->orderByDesc('anno')
            ->orderByDesc('periodo')
            ->get(['id', 'anno', 'periodo', 'tipo_periodo', 'saldo_finale']);

        // Versamenti ritenute senza F24 collegato
        $versamenti = VersamentoRitenuta::where('tenant_id', $tenant->id)
            ->whereDoesntHave('modelloF24')
            ->orderByDesc('data_versamento')
            ->get(['id', 'mese_riferimento', 'anno_riferimento', 'importo_totale', 'codice_tributo']);

        return Inertia::render('F24/Create', [
            'anni'           => range(now()->year, now()->year - 5),
            'annoCorrente'   => now()->year,
            'mesiLabel'      => ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                                 'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'],
            'sezioni'        => ModelloF24::SEZIONI,
            'statiLabel'     => ModelloF24::STATI,
            'liquidazioni'   => $liquidazioni,
            'versamenti'     => $versamenti,
            'codiciFrecuenti'=> $this->codiciFrecuenti(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'modalita'                  => 'required|in:manuale,da_liquidazione,da_ritenute',
            'anno'                      => 'required|integer|min:2000|max:2100',
            'mese'                      => 'nullable|integer|min:1|max:12',
            'data_compilazione'         => 'required|date',
            'data_versamento'           => 'nullable|date',
            'stato'                     => 'required|in:bozza,compilato',
            'note'                      => 'nullable|string|max:1000',
            'liquidazione_iva_id'       => 'nullable|exists:liquidazioni_iva,id',
            'versamento_ritenuta_id'    => 'nullable|exists:versamenti_ritenute,id',
            'righe'                     => 'nullable|array',
            'righe.*.sezione'           => 'required_with:righe|string|in:erario,inps,regioni,altri_enti,accise',
            'righe.*.codice_tributo'    => 'required_with:righe|string|max:10',
            'righe.*.descrizione'       => 'nullable|string|max:200',
            'righe.*.rateazione'        => 'nullable|string|max:6',
            'righe.*.anno_riferimento'  => 'nullable|integer|min:2000|max:2100',
            'righe.*.importo_debito'    => 'nullable|numeric|min:0',
            'righe.*.importo_credito'   => 'nullable|numeric|min:0',
        ]);

        try {
            $tenant   = app('current_tenant');
            $modello  = match ($data['modalita']) {
                'da_liquidazione' => $this->storeFromLiquidazione($tenant, $data),
                'da_ritenute'     => $this->storeFromRitenute($tenant, $data),
                default           => $this->service->crea($tenant, $data, $data['righe'] ?? []),
            };

            return redirect()
                ->route('f24.show', [request()->route('tenant'), $modello])
                ->with('flash', ['type' => 'success', 'message' => "F24 {$modello->periodoLabel()} creato."]);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────

    public function show(ModelloF24 $f24): Response
    {
        $f24->load(['righe', 'liquidazioneIva', 'versamentoRitenuta.compensi']);

        return Inertia::render('F24/Show', [
            'modello'  => $f24,
            'sezioni'  => ModelloF24::SEZIONI,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────

    public function edit(ModelloF24 $f24): Response|RedirectResponse
    {
        if ($f24->isVersionato()) {
            return redirect()
                ->route('f24.show', [request()->route('tenant'), $f24])
                ->with('flash', ['type' => 'warning', 'message' => 'F24 già versato: impossibile modificare.']);
        }

        $f24->load('righe');

        return Inertia::render('F24/Edit', [
            'modello'        => $f24,
            'sezioni'        => ModelloF24::SEZIONI,
            'statiLabel'     => ModelloF24::STATI,
            'codiciFrecuenti'=> $this->codiciFrecuenti(),
        ]);
    }

    public function update(Request $request, ModelloF24 $f24): RedirectResponse
    {
        $data = $request->validate([
            'anno'                      => 'required|integer|min:2000|max:2100',
            'mese'                      => 'nullable|integer|min:1|max:12',
            'data_compilazione'         => 'required|date',
            'data_versamento'           => 'nullable|date',
            'stato'                     => 'required|in:bozza,compilato,versato',
            'note'                      => 'nullable|string|max:1000',
            'righe'                     => 'nullable|array',
            'righe.*.sezione'           => 'required_with:righe|in:erario,inps,regioni,altri_enti,accise',
            'righe.*.codice_tributo'    => 'required_with:righe|string|max:10',
            'righe.*.descrizione'       => 'nullable|string|max:200',
            'righe.*.rateazione'        => 'nullable|string|max:6',
            'righe.*.anno_riferimento'  => 'nullable|integer|min:2000|max:2100',
            'righe.*.importo_debito'    => 'nullable|numeric|min:0',
            'righe.*.importo_credito'   => 'nullable|numeric|min:0',
        ]);

        try {
            $this->service->aggiorna($f24, $data, $data['righe'] ?? []);

            return redirect()
                ->route('f24.show', [request()->route('tenant'), $f24])
                ->with('flash', ['type' => 'success', 'message' => 'F24 aggiornato.']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Segna versato
    // ─────────────────────────────────────────────────────────────────────

    public function segnaVersato(Request $request, ModelloF24 $f24): RedirectResponse
    {
        $data = $request->validate([
            'data_versamento' => 'required|date',
        ]);

        try {
            $this->service->segnaVersato($f24, $data['data_versamento']);

            return redirect()
                ->route('f24.show', [request()->route('tenant'), $f24])
                ->with('flash', ['type' => 'success', 'message' => 'F24 marcato come versato.']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(ModelloF24 $f24): RedirectResponse
    {
        if ($f24->isVersionato()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Impossibile eliminare un F24 già versato.']);
        }

        $periodo = $f24->periodoLabel();
        $f24->delete();

        return redirect()
            ->route('f24.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => "F24 {$periodo} eliminato."]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export PDF
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdf(ModelloF24 $f24): HttpResponse
    {
        $f24->load(['righe', 'liquidazioneIva', 'versamentoRitenuta']);
        $tenant = app('current_tenant');

        $pdf = Pdf::loadView('pdf.f24', [
            'modello' => $f24,
            'tenant'  => $tenant,
            'sezioni' => ModelloF24::SEZIONI,
        ])->setPaper('A4', 'portrait');

        $filename = 'F24_' . $f24->anno . ($f24->mese ? '_' . str_pad($f24->mese, 2, '0', STR_PAD_LEFT) : '') . '.pdf';

        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export XML
    // ─────────────────────────────────────────────────────────────────────

    public function exportXml(ModelloF24 $f24): StreamedResponse
    {
        $xml = $this->service->generaXml($f24);
        $filename = 'F24_' . $f24->anno . ($f24->mese ? '_' . str_pad($f24->mese, 2, '0', STR_PAD_LEFT) : '') . '.xml';

        return response()->streamDownload(
            fn () => print($xml),
            $filename,
            ['Content-Type' => 'application/xml']
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    private function storeFromLiquidazione(object $tenant, array $data): ModelloF24
    {
        if (empty($data['liquidazione_iva_id'])) {
            throw new \InvalidArgumentException('Seleziona una liquidazione IVA.');
        }
        $liq = LiquidazioneIva::findOrFail($data['liquidazione_iva_id']);

        return $this->service->generaDaLiquidazioneIva($tenant, $liq, [
            'data_compilazione' => $data['data_compilazione'],
            'data_versamento'   => $data['data_versamento'] ?? null,
            'note'              => $data['note'] ?? null,
        ]);
    }

    private function storeFromRitenute(object $tenant, array $data): ModelloF24
    {
        if (empty($data['versamento_ritenuta_id'])) {
            throw new \InvalidArgumentException('Seleziona un versamento ritenute.');
        }
        $vers = VersamentoRitenuta::findOrFail($data['versamento_ritenuta_id']);

        return $this->service->generaDaRitenute($tenant, $vers, [
            'data_compilazione' => $data['data_compilazione'],
            'data_versamento'   => $data['data_versamento'] ?? null,
            'note'              => $data['note'] ?? null,
        ]);
    }

    /** Codici tributo più comuni con descrizione per la UI. */
    private function codiciFrecuenti(): array
    {
        return [
            // Erario — IVA mensile
            ['sezione' => 'erario', 'codice' => '6001', 'descrizione' => 'IVA gennaio'],
            ['sezione' => 'erario', 'codice' => '6002', 'descrizione' => 'IVA febbraio'],
            ['sezione' => 'erario', 'codice' => '6003', 'descrizione' => 'IVA marzo'],
            ['sezione' => 'erario', 'codice' => '6004', 'descrizione' => 'IVA aprile'],
            ['sezione' => 'erario', 'codice' => '6005', 'descrizione' => 'IVA maggio'],
            ['sezione' => 'erario', 'codice' => '6006', 'descrizione' => 'IVA giugno'],
            ['sezione' => 'erario', 'codice' => '6007', 'descrizione' => 'IVA luglio'],
            ['sezione' => 'erario', 'codice' => '6008', 'descrizione' => 'IVA agosto'],
            ['sezione' => 'erario', 'codice' => '6009', 'descrizione' => 'IVA settembre'],
            ['sezione' => 'erario', 'codice' => '6010', 'descrizione' => 'IVA ottobre'],
            ['sezione' => 'erario', 'codice' => '6011', 'descrizione' => 'IVA novembre'],
            ['sezione' => 'erario', 'codice' => '6012', 'descrizione' => 'IVA dicembre'],
            // IVA trimestrale
            ['sezione' => 'erario', 'codice' => '6031', 'descrizione' => 'IVA I trimestre'],
            ['sezione' => 'erario', 'codice' => '6032', 'descrizione' => 'IVA II trimestre'],
            ['sezione' => 'erario', 'codice' => '6033', 'descrizione' => 'IVA III trimestre'],
            ['sezione' => 'erario', 'codice' => '6099', 'descrizione' => 'IVA saldo annuale'],
            // Ritenute
            ['sezione' => 'erario', 'codice' => '1040', 'descrizione' => 'Ritenute lavoro autonomo/occasionale'],
            ['sezione' => 'erario', 'codice' => '1038', 'descrizione' => 'Ritenute provvigioni agenti'],
            // IRAP
            ['sezione' => 'regioni', 'codice' => '3800', 'descrizione' => 'IRAP saldo'],
            ['sezione' => 'regioni', 'codice' => '3812', 'descrizione' => 'IRAP I acconto'],
            ['sezione' => 'regioni', 'codice' => '3813', 'descrizione' => 'IRAP II acconto'],
            // IRPEF
            ['sezione' => 'erario', 'codice' => '4001', 'descrizione' => 'IRPEF saldo'],
            ['sezione' => 'erario', 'codice' => '4033', 'descrizione' => 'IRPEF I acconto'],
            ['sezione' => 'erario', 'codice' => '4034', 'descrizione' => 'IRPEF II acconto'],
        ];
    }
}
