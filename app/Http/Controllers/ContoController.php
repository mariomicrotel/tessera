<?php

namespace App\Http\Controllers;

use App\Models\Conto;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContoController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,contabile');
    }

    public function index(Request $request)
    {
        $anno = $request->filled('anno') ? (int) $request->anno : now()->year;

        $query = Conto::query()
            ->withCount(['movimenti', 'incassi'])
            // saldo totale (tutti gli anni) – una sola subquery, nessun N+1
            ->withSum('movimenti as saldo_totale', 'amount')
            // saldo dell'anno selezionato
            ->withSum(['movimenti as saldo_anno' => fn ($q) => $q->whereYear('date', $anno)], 'amount')
            ->ordered();

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('code', 'like', $term);
            });
        }

        $conti = $query->paginate(15)->withQueryString();

        // Totale liquidità (cassa + banca) su TUTTI gli anni — una query aggregata
        $liquiditaTotale = Conto::query()
            ->whereIn('type', ['cassa', 'banca'])
            ->withSum('movimenti as saldo', 'amount')
            ->get()
            ->sum(fn ($c) => (float) ($c->saldo ?? 0));

        return Inertia::render('Conti/Index', [
            'conti'           => $conti,
            'filters'         => $request->only('search', 'anno'),
            'anno'            => $anno,
            'anniDisponibili' => range(now()->year, max(now()->year - 4, 2020)),
            'liquiditaTotale' => round($liquiditaTotale, 2),
        ]);
    }

    public function create()
    {
        return Inertia::render('Conti/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'required|in:cassa,banca,altro',
            'iban' => 'nullable|string|max:34',
            'ordine' => 'nullable|integer',
            'attivo' => 'boolean',
        ]);
        $data['attivo'] = $request->boolean('attivo', true);
        $data['ordine'] = $data['ordine'] ?? 0;
        if (($data['type'] ?? '') !== 'banca') {
            $data['iban'] = null;
        }
        Conto::create($data);
        return redirect()->route('conti.index')->with('flash', ['type' => 'success', 'message' => 'Conto creato.']);
    }

    public function edit(Conto $conti)
    {
        return Inertia::render('Conti/Edit', ['conto' => $conti]);
    }

    public function update(Request $request, Conto $conti)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'required|in:cassa,banca,altro',
            'iban' => 'nullable|string|max:34',
            'ordine' => 'nullable|integer',
            'attivo' => 'boolean',
        ]);
        $data['attivo'] = $request->boolean('attivo', true);
        $data['ordine'] = $data['ordine'] ?? 0;
        if (($data['type'] ?? '') !== 'banca') {
            $data['iban'] = null;
        }
        $conti->update($data);
        return redirect()->route('conti.index')->with('flash', ['type' => 'success', 'message' => 'Conto aggiornato.']);
    }

    public function destroy(Conto $conti)
    {
        if ($conti->movimenti()->exists()) {
            return redirect()->route('conti.index')
                ->with('flash', ['type' => 'error', 'message' => 'Impossibile eliminare il conto: ha movimenti collegati.']);
        }
        if ($conti->incassi()->exists()) {
            return redirect()->route('conti.index')
                ->with('flash', ['type' => 'error', 'message' => 'Impossibile eliminare il conto: è usato da incassi.']);
        }
        $conti->delete();
        return redirect()->route('conti.index')->with('flash', ['type' => 'success', 'message' => 'Conto eliminato.']);
    }
}
