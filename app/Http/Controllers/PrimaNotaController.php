<?php

namespace App\Http\Controllers;

use App\Models\Conto;
use App\Models\PrimaNotaEntry;
use App\Services\RendicontoCassaSchemaResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Prima nota: elenco movimenti e creazione manuale. Voci da schema MOD_D (hardcoded).
 */
class PrimaNotaController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,contabile');
    }

    public function index(Request $request)
    {
        $query = PrimaNotaEntry::with('conto');

        // Filtri periodo
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }
        // Filtro voce rendiconto
        if ($request->filled('rendiconto_code')) {
            $query->where('rendiconto_code', $request->rendiconto_code);
        }
        // Filtro conto
        if ($request->filled('conto_id')) {
            $query->where('conto_id', $request->conto_id);
        }
        // Ricerca libera su descrizione
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }
        // Filtro tipo: entrata (amount > 0) | uscita (amount < 0)
        if ($request->filled('tipo')) {
            if ($request->tipo === 'entrata') {
                $query->where('amount', '>', 0);
            } elseif ($request->tipo === 'uscita') {
                $query->where('amount', '<', 0);
            }
        }
        // Filtro gestione
        if ($request->filled('gestione')) {
            $query->where('gestione', $request->gestione);
        }

        // Totali del periodo filtrato (prima della paginazione)
        $totEntrate = (clone $query)->where('amount', '>', 0)->sum('amount');
        $totUscite  = (clone $query)->where('amount', '<', 0)->sum('amount');

        $entries        = $query->orderByDesc('date')->orderByDesc('id')->paginate(30)->withQueryString();
        $conti          = Conto::attivi()->ordered()->get(['id', 'name', 'code']);
        $schema         = RendicontoCassaSchemaResolver::class();
        $rendicontoVoci = $schema::getSelectableVoices();
        $macroAreas     = $schema::getMacroAreasForSelect();

        return Inertia::render('PrimaNota/Index', [
            'entries'        => $entries,
            'rendicontoVoci' => $rendicontoVoci,
            'macroAreas'     => $macroAreas,
            'conti'          => $conti,
            'totals'         => [
                'entrate' => round((float) $totEntrate, 2),
                'uscite'  => round((float) $totUscite, 2),
                'saldo'   => round((float) $totEntrate + (float) $totUscite, 2),
            ],
            'filters' => $request->only('from', 'to', 'rendiconto_code', 'conto_id', 'search', 'tipo', 'gestione'),
        ]);
    }

    public function create(Request $request)
    {
        $conti = Conto::attivi()->ordered()->get(['id', 'name', 'code', 'type']);
        if ($conti->isEmpty()) {
            return redirect()->route('prima-nota.index')
                ->with('flash', ['type' => 'error', 'message' => 'Crea almeno un conto tesoreria prima di registrare movimenti.']);
        }
        $schema = RendicontoCassaSchemaResolver::class();
        return Inertia::render('PrimaNota/Create', [
            'rendicontoVoci' => $schema::getSelectableVoices(),
            'macroAreas' => $schema::getMacroAreasForSelect(),
            'conti' => $conti,
            'oldInput' => $request->old(),
        ]);
    }

    public function store(Request $request)
    {
        $schema = RendicontoCassaSchemaResolver::class();
        $validCodes = $schema::getValidCodes();
        $request->validate([
            'conto_id' => 'required|exists:conti,id',
            'rendiconto_code' => 'required|string|in:' . implode(',', $validCodes),
            'date' => 'required|date',
            'amount' => 'required|numeric',
            'description' => 'nullable|string|max:255',
            'gestione' => 'nullable|in:istituzionale,commerciale',
            'competenza_cassa' => 'boolean',
            'confirm_anno_precedente' => 'boolean',
        ]);

        $annoMovimento = (int) \Carbon\Carbon::parse($request->date)->format('Y');
        $annoCorrente = (int) date('Y');
        if ($annoMovimento < $annoCorrente && ! $request->boolean('confirm_anno_precedente')) {
            return redirect()->route('prima-nota.create')->withInput()->with('flash', [
                'type' => 'confirm_anno_precedente_required',
                'message' => 'Operazioni su anni precedenti possono alterare i rendiconti già generati. Vuoi procedere?',
            ]);
        }

        $info = $schema::getInfoByCode($request->rendiconto_code);
        $amount = (float) $request->amount;
        if ($info) {
            if ($info['tipo'] === 'entrata' && $amount < 0) {
                return redirect()->back()->withInput()->withErrors([
                    'amount' => 'Per una voce di entrata l\'importo deve essere positivo.',
                ]);
            }
            if ($info['tipo'] === 'uscita' && $amount > 0) {
                return redirect()->back()->withInput()->withErrors([
                    'amount' => 'Per una voce di uscita l\'importo deve essere negativo.',
                ]);
            }
        }

        PrimaNotaEntry::create($request->only('conto_id', 'rendiconto_code', 'date', 'amount', 'description', 'gestione') + [
            'competenza_cassa' => $request->boolean('competenza_cassa', true),
        ]);

        return redirect()->route('prima-nota.index')->with('flash', ['type' => 'success', 'message' => 'Movimento registrato.']);
    }

    public function edit(PrimaNotaEntry $prima_nota_entry)
    {
        $conti = Conto::attivi()->ordered()->get(['id', 'name', 'code', 'type']);
        $schema = RendicontoCassaSchemaResolver::class();
        return Inertia::render('PrimaNota/Edit', [
            'entry' => $prima_nota_entry,
            'rendicontoVoci' => $schema::getSelectableVoices(),
            'macroAreas' => $schema::getMacroAreasForSelect(),
            'conti' => $conti,
        ]);
    }

    public function update(Request $request, PrimaNotaEntry $prima_nota_entry)
    {
        $schema = RendicontoCassaSchemaResolver::class();
        $validCodes = $schema::getValidCodes();
        $request->validate([
            'conto_id' => 'required|exists:conti,id',
            'rendiconto_code' => 'required|string|in:' . implode(',', $validCodes),
            'date' => 'required|date',
            'amount' => 'required|numeric',
            'description' => 'nullable|string|max:255',
            'gestione' => 'nullable|in:istituzionale,commerciale',
            'competenza_cassa' => 'boolean',
            'confirm_anno_precedente' => 'boolean',
        ]);

        $date = \Carbon\Carbon::parse($request->date);
        if ($date->year < (int) date('Y') && ! $request->boolean('confirm_anno_precedente')) {
            return redirect()->back()->withInput()->with('flash', [
                'type' => 'confirm_anno_precedente_required',
                'message' => 'Operazioni su anni precedenti possono alterare i rendiconti già generati. Vuoi procedere?',
            ]);
        }

        $info = $schema::getInfoByCode($request->rendiconto_code);
        $amount = (float) $request->amount;
        if ($info) {
            if ($info['tipo'] === 'entrata' && $amount < 0) {
                return redirect()->back()->withInput()->withErrors([
                    'amount' => 'Per una voce di entrata l\'importo deve essere positivo.',
                ]);
            }
            if ($info['tipo'] === 'uscita' && $amount > 0) {
                return redirect()->back()->withInput()->withErrors([
                    'amount' => 'Per una voce di uscita l\'importo deve essere negativo.',
                ]);
            }
        }

        $prima_nota_entry->update($request->only('conto_id', 'rendiconto_code', 'date', 'amount', 'description', 'gestione') + [
            'competenza_cassa' => $request->boolean('competenza_cassa', true),
        ]);

        return redirect()->route('prima-nota.index')->with('flash', ['type' => 'success', 'message' => 'Movimento aggiornato.']);
    }

    public function destroy(Request $request, PrimaNotaEntry $prima_nota_entry)
    {
        $request->validate(['confirm_anno_precedente' => 'boolean']);
        $entryYear = (int) $prima_nota_entry->date->format('Y');
        $currentYear = (int) date('Y');
        if ($entryYear < $currentYear && ! $request->boolean('confirm_anno_precedente')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'confirm_anno_precedente_required' => true,
                    'message' => 'Operazioni su anni precedenti possono alterare i rendiconti già generati. Vuoi procedere?',
                ], 409);
            }
            return redirect()->back()->with('flash', [
                'type' => 'confirm_anno_precedente_required',
                'message' => 'Operazioni su anni precedenti possono alterare i rendiconti già generati. Vuoi procedere?',
                'destroy_entry_id' => $prima_nota_entry->id,
            ]);
        }
        $prima_nota_entry->delete();
        return redirect()->route('prima-nota.index')->with('flash', ['type' => 'success', 'message' => 'Movimento eliminato.']);
    }

    public function createGiroconto()
    {
        $conti = Conto::attivi()->ordered()->get(['id', 'name', 'code', 'type']);
        if ($conti->count() < 2) {
            return redirect()->route('prima-nota.index')
                ->with('flash', ['type' => 'error', 'message' => 'Servono almeno due conti attivi per eseguire un giroconto.']);
        }
        return Inertia::render('PrimaNota/Giroconto', ['conti' => $conti]);
    }

    public function storeGiroconto(Request $request)
    {
        $conti = Conto::attivi()->ordered()->get(['id', 'name']);
        $validIds = $conti->pluck('id')->toArray();
        $request->validate([
            'conto_da_id' => 'required|in:' . implode(',', $validIds),
            'conto_a_id' => 'required|in:' . implode(',', $validIds),
            'date' => 'required|date',
            'importo' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);
        $contoDaId = (int) $request->conto_da_id;
        $contoAId = (int) $request->conto_a_id;
        if ($contoDaId === $contoAId) {
            return redirect()->back()->withInput()->withErrors([
                'conto_a_id' => 'Il conto di destinazione deve essere diverso dal conto di partenza.',
            ]);
        }
        $contoDa = $conti->firstWhere('id', $contoDaId);
        $contoA = $conti->firstWhere('id', $contoAId);
        $importo = (float) $request->importo;
        $data = $request->date;
        $desc = $request->filled('description')
            ? $request->description
            : 'Giroconto da ' . $contoDa->name . ' verso ' . $contoA->name;

        PrimaNotaEntry::create([
            'conto_id' => $contoDaId,
            'rendiconto_code' => 'EXP_D_1',
            'date' => $data,
            'amount' => -$importo,
            'description' => $desc,
            'gestione' => 'istituzionale',
            'competenza_cassa' => true,
        ]);
        PrimaNotaEntry::create([
            'conto_id' => $contoAId,
            'rendiconto_code' => 'INC_D_1',
            'date' => $data,
            'amount' => $importo,
            'description' => $desc,
            'gestione' => 'istituzionale',
            'competenza_cassa' => true,
        ]);

        return redirect()->route('prima-nota.index')->with('flash', ['type' => 'success', 'message' => 'Giroconto registrato.']);
    }
}
