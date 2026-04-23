<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD anagrafica fornitori.
 *
 * Accesso limitato ai ruoli: admin, segreteria, contabile.
 */
class SupplierController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria,contabile');
    }

    // ─────────────────────────────────────────────────────────────────────
    // index — lista paginata con filtri
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $query = Supplier::query()->withCount('fatturePassive');

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('categoria')) {
            $query->byCategoria($request->categoria);
        }

        if ($request->boolean('solo_attivi', true)) {
            $query->attivi();
        }

        $suppliers = $query
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers'  => $suppliers,
            'filters'    => $request->only('search', 'categoria', 'solo_attivi'),
            'categorie'  => Supplier::CATEGORIE,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // create
    // ─────────────────────────────────────────────────────────────────────

    public function create(): Response
    {
        return Inertia::render('Suppliers/Create', [
            'condizioniPagamento' => Supplier::CONDIZIONI_PAGAMENTO,
            'categorie'           => Supplier::CATEGORIE,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // store
    // ─────────────────────────────────────────────────────────────────────

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('flash', ['type' => 'success', 'message' => 'Fornitore creato con successo.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // show
    // ─────────────────────────────────────────────────────────────────────

    public function show(Supplier $supplier): Response
    {
        $fatturePassive = $supplier
            ->fatturePassive()
            ->orderByDesc('data_fattura')
            ->paginate(10, ['*'], 'fp_page');

        return Inertia::render('Suppliers/Show', [
            'supplier'           => $supplier->append(['nome_completo', 'indirizzo_completo']),
            'fatturePassive'     => $fatturePassive,
            'condizioniLabel'    => Supplier::CONDIZIONI_PAGAMENTO[$supplier->condizioni_pagamento] ?? $supplier->condizioni_pagamento,
            'categoriaLabel'     => Supplier::CATEGORIE[$supplier->categoria] ?? $supplier->categoria,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // edit
    // ─────────────────────────────────────────────────────────────────────

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('Suppliers/Edit', [
            'supplier'            => $supplier,
            'condizioniPagamento' => Supplier::CONDIZIONI_PAGAMENTO,
            'categorie'           => Supplier::CATEGORIE,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────────────────

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('flash', ['type' => 'success', 'message' => 'Fornitore aggiornato.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // destroy — soft delete
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(Supplier $supplier): RedirectResponse
    {
        // Impedisce l'eliminazione se ci sono fatture passive collegate
        if ($supplier->fatturePassive()->exists()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Impossibile eliminare: esistono fatture passive collegate a questo fornitore.',
            ]);
        }

        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('flash', ['type' => 'success', 'message' => 'Fornitore eliminato.']);
    }
}
