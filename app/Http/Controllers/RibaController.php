<?php

namespace App\Http\Controllers;

use App\Models\FatturaAttiva;
use App\Models\Riba;
use App\Models\Settings;
use App\Services\RibaCbiExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RibaController extends Controller
{
    public function __construct(private RibaCbiExportService $cbiService)
    {
        $this->middleware('role:admin,contabile,segreteria');
    }

    public function index(Request $request): InertiaResponse
    {
        $query = Riba::query()
            ->when($request->filled('stato'), fn ($q) => $q->where('stato', $request->stato))
            ->when($request->filled('dal'),   fn ($q) => $q->whereDate('data_scadenza', '>=', $request->dal))
            ->when($request->filled('al'),    fn ($q) => $q->whereDate('data_scadenza', '<=', $request->al))
            ->orderBy('data_scadenza')
            ->orderByDesc('created_at');

        $riba = $query->paginate(25)->withQueryString();

        $totaleSelezionabile = Riba::daInviare()->sum('importo');

        return Inertia::render('Riba/Index', [
            'riba'               => $riba,
            'totaleSelezionabile' => (float) $totaleSelezionabile,
            'statiLabel'         => Riba::STATI_LABEL,
            'filters'            => $request->only('stato', 'dal', 'al'),
        ]);
    }

    public function create(): InertiaResponse
    {
        $fatture = FatturaAttiva::query()
            ->whereIn('stato', [
                FatturaAttiva::STATO_EMESSA,
                FatturaAttiva::STATO_INVIATA_SDI,
                FatturaAttiva::STATO_ACCETTATA,
            ])
            ->whereNotIn('stato_pagamento', ['incassata', 'annullata'])
            ->orderByDesc('data_fattura')
            ->get(['id', 'numero_fattura', 'data_fattura', 'totale_documento', 'cliente_id']);

        return Inertia::render('Riba/Create', [
            'fatture'    => $fatture,
            'codiceSia'  => Settings::get('codice_sia', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fattura_attiva_id' => ['nullable', 'exists:fatture_attive,id'],
            'numero_riba'       => ['nullable', 'string', 'max:10'],
            'nome_debitore'     => ['required', 'string', 'max:60'],
            'cf_piva_debitore'  => ['nullable', 'string', 'max:20'],
            'iban_debitore'     => ['nullable', 'string', 'max:34'],
            'importo'           => ['required', 'numeric', 'min:0.01'],
            'data_scadenza'     => ['required', 'date'],
            'data_emissione'    => ['required', 'date'],
            'banca_presentatrice' => ['nullable', 'string', 'max:100'],
            'note'              => ['nullable', 'string', 'max:255'],
        ]);

        Riba::create($data + ['stato' => Riba::STATO_DA_INVIARE]);

        return redirect()->route('riba.index')->with('success', 'RI.BA creata.');
    }

    public function show(Riba $riba): InertiaResponse
    {
        return Inertia::render('Riba/Show', [
            'riba'       => $riba->load('fatturaAttiva'),
            'statiLabel' => Riba::STATI_LABEL,
        ]);
    }

    public function markInviata(Riba $riba): RedirectResponse
    {
        $riba->update(['stato' => Riba::STATO_INVIATA, 'data_invio_banca' => now()]);
        return back()->with('success', 'RI.BA marcata come inviata.');
    }

    public function markPagata(Riba $riba): RedirectResponse
    {
        $riba->update(['stato' => Riba::STATO_PAGATA]);
        return back()->with('success', 'RI.BA marcata come pagata.');
    }

    public function markInsoluta(Riba $riba): RedirectResponse
    {
        $riba->update(['stato' => Riba::STATO_INSOLUTA]);
        return back()->with('success', 'RI.BA marcata insoluta.');
    }

    public function destroy(Riba $riba): RedirectResponse
    {
        $riba->delete();
        return redirect()->route('riba.index')->with('success', 'RI.BA eliminata.');
    }

    /**
     * Esporta un file CBI (.rtr) per le RI.BA selezionate (stato: da_inviare).
     * Accetta `ids[]` in query string; se assente esporta tutte quelle da_inviare.
     */
    public function exportCbi(Request $request): StreamedResponse|Response
    {
        $codiceSia    = Settings::get('codice_sia', '00000');
        $codiceCab    = Settings::get('cab_banca', '00000');
        $codiceCc     = Settings::get('cc_banca', '000000000000');
        $ragioneSociale = Settings::get('nome_associazione', config('app.name'));

        $query = Riba::daInviare()->orderBy('data_scadenza');

        if ($request->filled('ids')) {
            $ids = array_filter((array) $request->ids);
            $query->whereIn('id', $ids);
        }

        $ribaList = $query->get();

        if ($ribaList->isEmpty()) {
            return back()->with('error', 'Nessuna RI.BA selezionata o disponibile per l\'esportazione.');
        }

        $contenuto = $this->cbiService->generate($ribaList, $codiceSia, $codiceCab, $codiceCc, $ragioneSociale);
        $filename  = 'riba_' . now()->format('Ymd_His') . '.rtr';

        // Marca le RI.BA come inviate
        $ribaList->each(fn ($r) => $r->update(['stato' => Riba::STATO_INVIATA, 'data_invio_banca' => now()]));

        return response()->streamDownload(function () use ($contenuto) {
            echo $contenuto;
        }, $filename, [
            'Content-Type'        => 'text/plain; charset=ISO-8859-1',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
