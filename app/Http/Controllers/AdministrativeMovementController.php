<?php

namespace App\Http\Controllers;

use App\Models\AdministrativeMovement;
use App\Models\AdministrativeMovementCategory;
use App\Models\Conto;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Gestione movimenti amministrativi semplificati.
 *
 * Include anche la gestione CRUD delle categorie (via endpoint JSON/redirect)
 * per non richiedere pagine separate.
 */
class AdministrativeMovementController extends Controller
{
    /* ── Index ─────────────────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $this->authorizeRole();

        $filters = $request->only('from', 'to', 'tipo', 'category_id', 'conto_id', 'search');

        // Default: mese corrente
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to   = $filters['to']   ?? now()->endOfMonth()->toDateString();

        $query = AdministrativeMovement::with(['category', 'conto', 'contoDestinazione'])
            ->nelPeriodo($from, $to);

        if (! empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['conto_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('conto_id', $filters['conto_id'])
                  ->orWhere('conto_destinazione_id', $filters['conto_id']);
            });
        }
        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(function ($qry) use ($q) {
                $qry->where('descrizione', 'like', "%{$q}%")
                    ->orWhere('riferimento', 'like', "%{$q}%")
                    ->orWhere('note', 'like', "%{$q}%");
            });
        }

        $movimenti = $query->orderBy('data', 'desc')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Totali nel periodo filtrato (senza paginazione)
        $totaleEntrate = AdministrativeMovement::entrate()
            ->nelPeriodo($from, $to)
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(! empty($filters['conto_id']),    fn ($q) => $q->where('conto_id', $filters['conto_id']))
            ->sum('importo');

        $totaleUscite = AdministrativeMovement::uscite()
            ->nelPeriodo($from, $to)
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(! empty($filters['conto_id']),    fn ($q) => $q->where('conto_id', $filters['conto_id']))
            ->sum('importo');

        // Saldi conti (amministrativi, distinti da prima_nota_entries)
        $conti = Conto::attivi()->ordered()->get(['id', 'name', 'type', 'code']);

        // Saldo AM per conto: entrate - uscite
        $saldiAm = [];
        foreach ($conti as $c) {
            $e = AdministrativeMovement::entrate()->where('conto_id', $c->id)->sum('importo');
            $u = AdministrativeMovement::uscite()->where('conto_id', $c->id)->sum('importo');
            // giroconto: aggiunge nel conto destinazione, sottrae dal conto sorgente
            $gin = AdministrativeMovement::where('tipo', 'giroconto')->where('conto_destinazione_id', $c->id)->sum('importo');
            $gout = AdministrativeMovement::where('tipo', 'giroconto')->where('conto_id', $c->id)->sum('importo');
            $saldiAm[$c->id] = round((float) $e - (float) $u + (float) $gin - (float) $gout, 2);
        }

        $categories = AdministrativeMovementCategory::attive()->ordered()
            ->get(['id', 'nome', 'tipo', 'colore', 'icona']);

        return Inertia::render('Amministrazione/Index', [
            'movimenti'      => $movimenti->through(fn ($m) => $this->serializeMovimento($m)),
            'filters'        => array_merge(['from' => $from, 'to' => $to], $filters),
            'totals'         => [
                'entrate' => round((float) $totaleEntrate, 2),
                'uscite'  => round((float) $totaleUscite, 2),
                'saldo'   => round((float) $totaleEntrate - (float) $totaleUscite, 2),
            ],
            'conti'          => $conti,
            'saldi_am'       => $saldiAm,
            'categories'     => $categories,
            // Tutte le categorie (anche inattive) per la gestione
            'all_categories' => AdministrativeMovementCategory::ordered()
                ->get(['id', 'nome', 'tipo', 'colore', 'icona', 'ordine', 'attiva']),
        ]);
    }

    /* ── Create / Store ─────────────────────────────────────────────────── */

    public function create()
    {
        $this->authorizeRole();

        return Inertia::render('Amministrazione/Create', [
            'conti'      => Conto::attivi()->ordered()->get(['id', 'name', 'type', 'code']),
            'categories' => AdministrativeMovementCategory::attive()->ordered()
                ->get(['id', 'nome', 'tipo', 'colore', 'icona']),
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeRole();

        $validated = $this->validateMovimento($request);

        AdministrativeMovement::create($validated);

        return redirect()
            ->route('movimenti-amministrativi.index')
            ->with('flash', ['type' => 'success', 'message' => 'Movimento registrato.']);
    }

    /* ── Edit / Update ──────────────────────────────────────────────────── */

    public function edit(AdministrativeMovement $movimentiAmministrativi)
    {
        $this->authorizeRole();

        $movimentiAmministrativi->load(['category', 'conto', 'contoDestinazione']);

        return Inertia::render('Amministrazione/Edit', [
            'movimento'  => $this->serializeMovimento($movimentiAmministrativi),
            'conti'      => Conto::attivi()->ordered()->get(['id', 'name', 'type', 'code']),
            'categories' => AdministrativeMovementCategory::attive()->ordered()
                ->get(['id', 'nome', 'tipo', 'colore', 'icona']),
        ]);
    }

    public function update(Request $request, AdministrativeMovement $movimentiAmministrativi)
    {
        $this->authorizeRole();

        $validated = $this->validateMovimento($request, $movimentiAmministrativi);

        $movimentiAmministrativi->update($validated);

        return redirect()
            ->route('movimenti-amministrativi.index')
            ->with('flash', ['type' => 'success', 'message' => 'Movimento aggiornato.']);
    }

    /* ── Destroy ────────────────────────────────────────────────────────── */

    public function destroy(AdministrativeMovement $movimentiAmministrativi)
    {
        $this->authorizeRole('admin', 'contabile');

        $movimentiAmministrativi->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Movimento eliminato.']);
    }

    /* ── Categorie (CRUD inline via redirect) ───────────────────────────── */

    public function storeCategory(Request $request)
    {
        $this->authorizeRole('admin', 'contabile');

        $request->validate([
            'nome'   => 'required|string|max:100',
            'tipo'   => 'required|in:entrata,uscita,qualsiasi',
            'colore' => 'nullable|string|max:7',
            'icona'  => 'nullable|string|max:10',
        ]);

        AdministrativeMovementCategory::create([
            'nome'   => $request->nome,
            'tipo'   => $request->tipo,
            'colore' => $request->colore,
            'icona'  => $request->icona,
            'ordine' => AdministrativeMovementCategory::max('ordine') + 1,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => "Categoria \"{$request->nome}\" creata."]);
    }

    public function updateCategory(Request $request, AdministrativeMovementCategory $category)
    {
        $this->authorizeRole('admin', 'contabile');

        $request->validate([
            'nome'   => 'required|string|max:100',
            'tipo'   => 'required|in:entrata,uscita,qualsiasi',
            'colore' => 'nullable|string|max:7',
            'icona'  => 'nullable|string|max:10',
            'attiva' => 'boolean',
        ]);

        $category->update($request->only('nome', 'tipo', 'colore', 'icona', 'attiva'));

        return back()->with('flash', ['type' => 'success', 'message' => 'Categoria aggiornata.']);
    }

    public function destroyCategory(AdministrativeMovementCategory $category)
    {
        $this->authorizeRole('admin', 'contabile');

        // Non eliminare se ha movimenti collegati
        if ($category->movimenti()->exists()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Impossibile eliminare: ci sono movimenti collegati a questa categoria.',
            ]);
        }

        $category->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Categoria eliminata.']);
    }

    /* ── Helpers privati ─────────────────────────────────────────────────── */

    private function serializeMovimento(AdministrativeMovement $m): array
    {
        return [
            'id'                    => $m->id,
            'data'                  => $m->data?->toDateString(),
            'tipo'                  => $m->tipo,
            'tipo_label'            => $m->tipoLabel(),
            'tipo_badge_color'      => $m->tipoBadgeColor(),
            'importo'               => (float) $m->importo,
            'importo_con_segno'     => $m->importoConSegno(),
            'descrizione'           => $m->descrizione,
            'riferimento'           => $m->riferimento,
            'note'                  => $m->note,
            'category'              => $m->category
                ? ['id' => $m->category->id, 'nome' => $m->category->nome, 'colore' => $m->category->colore, 'icona' => $m->category->icona]
                : null,
            'conto'                 => $m->conto
                ? ['id' => $m->conto->id, 'name' => $m->conto->name, 'type' => $m->conto->type]
                : null,
            'conto_destinazione'    => $m->contoDestinazione
                ? ['id' => $m->contoDestinazione->id, 'name' => $m->contoDestinazione->name]
                : null,
            'category_id'           => $m->category_id,
            'conto_id'              => $m->conto_id,
            'conto_destinazione_id' => $m->conto_destinazione_id,
        ];
    }

    private function validateMovimento(Request $request, ?AdministrativeMovement $existing = null): array
    {
        return $request->validate([
            'data'                   => 'required|date',
            'tipo'                   => 'required|in:entrata,uscita,giroconto',
            'importo'                => 'required|numeric|min:0.01|max:9999999.99',
            'descrizione'            => 'required|string|max:255',
            'category_id'            => 'nullable|integer|exists:administrative_movement_categories,id',
            'conto_id'               => 'nullable|integer|exists:conti,id',
            'conto_destinazione_id'  => 'nullable|integer|exists:conti,id|different:conto_id',
            'riferimento'            => 'nullable|string|max:100',
            'note'                   => 'nullable|string|max:5000',
        ]);
    }

    private function authorizeRole(string ...$roles): void
    {
        if (empty($roles)) {
            $roles = ['admin', 'segreteria', 'contabile'];
        }
        $user = auth()->user();
        if (! $user || (! $user->is_super_admin && ! $user->hasRole(...$roles))) {
            abort(403);
        }
    }
}
