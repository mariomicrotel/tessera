<?php

namespace App\Http\Controllers;

use App\Models\Conto;
use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\Scadenza;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scadenzario completo: generiche, fornitori, clienti, dashboard.
 *
 * Middleware: role:admin,contabile
 */
class ScadenzaController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,contabile');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Dashboard unificata
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Panoramica: scadenze in scadenza entro 30 gg + scadute + totali per tipo.
     */
    public function dashboard(): Response
    {
        $inScadenza = Scadenza::inScadenza(30)
            ->orderBy('data_scadenza')
            ->get();

        $scadute = Scadenza::scadute()
            ->orderBy('data_scadenza')
            ->get();

        $fornitoriInScadenza = FatturaPassiva::daPagare()
            ->whereNotNull('data_scadenza')
            ->where('data_scadenza', '<=', now()->addDays(30)->toDateString())
            ->with('supplier')
            ->orderBy('data_scadenza')
            ->get();

        $fornitoriScadute = FatturaPassiva::daPagare()
            ->whereNotNull('data_scadenza')
            ->where('data_scadenza', '<', now()->toDateString())
            ->with('supplier')
            ->orderBy('data_scadenza')
            ->get();

        return Inertia::render('Scadenze/Dashboard', [
            'inScadenza'          => $inScadenza,
            'scadute'             => $scadute,
            'fornitoriInScadenza' => $fornitoriInScadenza,
            'fornitoriScadute'    => $fornitoriScadute,
            'totaleScaduto'       => $scadute->sum('importo') + $fornitoriScadute->sum('totale_documento'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scadenze Generiche — CRUD
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $query = Scadenza::query()->orderBy('data_scadenza');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('stato')) {
            $query->where('stato', $request->stato);
        }
        if ($request->filled('dal')) {
            $query->where('data_scadenza', '>=', $request->dal);
        }
        if ($request->filled('al')) {
            $query->where('data_scadenza', '<=', $request->al);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('descrizione', 'like', $term)
                  ->orWhere('riferimento', 'like', $term);
            });
        }

        $scadenze = $query->paginate(25)->withQueryString();

        return Inertia::render('Scadenze/Index', [
            'scadenze' => $scadenze,
            'tipi'     => Scadenza::TIPI,
            'stati'    => Scadenza::STATI,
            'filters'  => $request->only('tipo', 'stato', 'dal', 'al', 'search'),
            'conti'    => Conto::attivi()->orderBy('ordine')->get(['id', 'name', 'code']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Scadenze/Create', [
            'tipi'  => Scadenza::TIPI,
            'conti' => Conto::attivi()->orderBy('ordine')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tipo'          => 'required|in:' . implode(',', array_keys(Scadenza::TIPI)),
            'descrizione'   => 'required|string|max:255',
            'importo'       => 'nullable|numeric|min:0',
            'data_scadenza' => 'required|date',
            'riferimento'   => 'nullable|string|max:255',
            'conto_id'      => 'nullable|exists:conti,id',
            'ricorrente'    => 'nullable|boolean',
            'note'          => 'nullable|string',
        ]);

        $data['stato']      = Scadenza::STATO_APERTA;
        $data['ricorrente'] = (bool) ($data['ricorrente'] ?? false);

        Scadenza::create($data);

        return redirect()
            ->route('scadenze.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => 'Scadenza creata.']);
    }

    public function edit(Scadenza $scadenza): Response
    {
        return Inertia::render('Scadenze/Edit', [
            'scadenza' => $scadenza,
            'tipi'     => Scadenza::TIPI,
            'stati'    => Scadenza::STATI,
            'conti'    => Conto::attivi()->orderBy('ordine')->get(['id', 'name', 'code']),
        ]);
    }

    public function update(Request $request, Scadenza $scadenza): RedirectResponse
    {
        $data = $request->validate([
            'tipo'          => 'required|in:' . implode(',', array_keys(Scadenza::TIPI)),
            'descrizione'   => 'required|string|max:255',
            'importo'       => 'nullable|numeric|min:0',
            'data_scadenza' => 'required|date',
            'stato'         => 'required|in:' . implode(',', array_keys(Scadenza::STATI)),
            'riferimento'   => 'nullable|string|max:255',
            'conto_id'      => 'nullable|exists:conti,id',
            'ricorrente'    => 'nullable|boolean',
            'note'          => 'nullable|string',
        ]);

        $data['ricorrente'] = (bool) ($data['ricorrente'] ?? false);

        $scadenza->update($data);

        return redirect()
            ->route('scadenze.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => 'Scadenza aggiornata.']);
    }

    public function destroy(Scadenza $scadenza): RedirectResponse
    {
        $scadenza->delete();

        return redirect()
            ->route('scadenze.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => 'Scadenza eliminata.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Azioni di stato
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Marca una scadenza generica come pagata.
     */
    public function markPagata(Request $request, Scadenza $scadenza): RedirectResponse
    {
        $data = $request->validate([
            'data_pagamento' => 'required|date',
            'conto_id'       => 'nullable|exists:conti,id',
        ]);

        if ($scadenza->stato === Scadenza::STATO_PAGATA) {
            return redirect()->back()->with('flash', [
                'type'    => 'warning',
                'message' => 'Scadenza già marcata come pagata.',
            ]);
        }

        $scadenza->update([
            'stato'          => Scadenza::STATO_PAGATA,
            'data_pagamento' => $data['data_pagamento'],
            'conto_id'       => $data['conto_id'] ?? $scadenza->conto_id,
        ]);

        return redirect()->back()->with('flash', [
            'type'    => 'success',
            'message' => 'Scadenza marcata come pagata.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Ripresa anno precedente
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Riprende le scadenze ricorrenti dell'anno precedente
     * aggiornando la data al nuovo anno (stesso mese/giorno, anno+1).
     * Restituisce il numero di scadenze create.
     */
    public function riprendi(Request $request): RedirectResponse
    {
        $annoCorrente    = now()->year;
        $annoPrecedente  = $annoCorrente - 1;

        // Scadenze ricorrenti dell'anno precedente non già riprese
        $daRiprendere = Scadenza::where('ricorrente', true)
            ->whereYear('data_scadenza', $annoPrecedente)
            ->whereNull('origine_anno_precedente')
            ->get();

        $create = 0;

        foreach ($daRiprendere as $vecchia) {
            $nuovaData = $vecchia->data_scadenza->copy()->addYear();

            // Evita duplicati (stessa descrizione + stessa nuova data)
            $esiste = Scadenza::where('descrizione', $vecchia->descrizione)
                ->whereDate('data_scadenza', $nuovaData)
                ->exists();

            if (! $esiste) {
                Scadenza::create([
                    'tipo'                    => $vecchia->tipo,
                    'descrizione'             => $vecchia->descrizione,
                    'importo'                 => $vecchia->importo,
                    'data_scadenza'           => $nuovaData,
                    'stato'                   => Scadenza::STATO_APERTA,
                    'riferimento'             => $vecchia->riferimento,
                    'conto_id'                => $vecchia->conto_id,
                    'ricorrente'              => true,
                    'origine_anno_precedente' => $vecchia->data_scadenza,
                    'note'                    => $vecchia->note,
                ]);
                $create++;
            }
        }

        return redirect()->back()->with('flash', [
            'type'    => 'success',
            'message' => $create > 0
                ? "Riprese {$create} scadenze dall'anno {$annoPrecedente}."
                : "Nessuna scadenza ricorrente da riprendere.",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Viste dedicate: Fornitori e Clienti
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Scadenzario fornitori: fatture passive con data_scadenza.
     */
    public function fornitori(Request $request): Response
    {
        $query = FatturaPassiva::query()
            ->whereNotNull('data_scadenza')
            ->with('supplier')
            ->orderBy('data_scadenza');

        if ($request->filled('stato')) {
            $query->where('stato_pagamento', $request->stato);
        } else {
            $query->where('stato_pagamento', FatturaPassiva::STATO_DA_PAGARE);
        }
        if ($request->filled('dal')) {
            $query->where('data_scadenza', '>=', $request->dal);
        }
        if ($request->filled('al')) {
            $query->where('data_scadenza', '<=', $request->al);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('numero_fattura', 'like', $term)
                  ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', $term));
            });
        }

        $fatture = $query->paginate(25)->withQueryString();

        $totaleScaduto = FatturaPassiva::daPagare()
            ->whereNotNull('data_scadenza')
            ->where('data_scadenza', '<', now()->toDateString())
            ->sum('totale_documento');

        return Inertia::render('Scadenze/Fornitori', [
            'fatture'       => $fatture,
            'totaleScaduto' => (float) $totaleScaduto,
            'stati'         => [
                FatturaPassiva::STATO_DA_PAGARE           => 'Da pagare',
                FatturaPassiva::STATO_PARZIALMENTE_PAGATA => 'Parzialmente pagata',
                FatturaPassiva::STATO_PAGATA              => 'Pagata',
                FatturaPassiva::STATO_ANNULLATA           => 'Annullata',
            ],
            'filters'       => $request->only('stato', 'dal', 'al', 'search'),
        ]);
    }

    /**
     * Scadenzario clienti: fatture attive con data_scadenza.
     */
    public function clienti(Request $request): Response
    {
        $query = FatturaAttiva::query()
            ->whereNotNull('data_scadenza')
            ->orderBy('data_scadenza');

        if ($request->filled('stato')) {
            $query->where('stato_pagamento', $request->stato);
        } else {
            $query->whereIn('stato_pagamento', ['da_incassare', 'parzialmente_incassata']);
        }
        if ($request->filled('dal')) {
            $query->where('data_scadenza', '>=', $request->dal);
        }
        if ($request->filled('al')) {
            $query->where('data_scadenza', '<=', $request->al);
        }

        $fatture = $query->paginate(25)->withQueryString();

        $totaleScaduto = FatturaAttiva::whereNotNull('data_scadenza')
            ->where('data_scadenza', '<', now()->toDateString())
            ->whereIn('stato_pagamento', ['da_incassare', 'parzialmente_incassata'])
            ->sum('totale_documento');

        return Inertia::render('Scadenze/Clienti', [
            'fatture'       => $fatture,
            'totaleScaduto' => (float) $totaleScaduto,
            'filters'       => $request->only('stato', 'dal', 'al'),
        ]);
    }
}
