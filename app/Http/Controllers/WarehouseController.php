<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    public function index(Request $request)
    {
        $query = Warehouse::with('location')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $warehouses = $query->paginate(15)->withQueryString();

        return Inertia::render('Warehouses/Index', [
            'warehouses' => $warehouses,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        $locations = Location::orderBy('name')->get();
        return Inertia::render('Warehouses/Create', ['locations' => $locations]);
    }

    public function store(Request $request)
    {
        $request->merge(['location_id' => $request->input('location_id') ?: null]);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => 'nullable|exists:locations,id',
        ]);
        $warehouse = Warehouse::create($data);
        return redirect()->route('warehouses.show', $warehouse)->with('flash', ['type' => 'success', 'message' => 'Magazzino creato.']);
    }

    public function show(Warehouse $warehouse)
    {
        $warehouse->load(['location', 'stocks.item']);
        $items = Item::orderBy('name')->get();
        return Inertia::render('Warehouses/Show', ['warehouse' => $warehouse, 'items' => $items]);
    }

    public function edit(Warehouse $warehouse)
    {
        $locations = Location::orderBy('name')->get();
        return Inertia::render('Warehouses/Edit', ['warehouse' => $warehouse, 'locations' => $locations]);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $request->merge(['location_id' => $request->input('location_id') ?: null]);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => 'nullable|exists:locations,id',
        ]);
        $warehouse->update($data);
        return redirect()->route('warehouses.show', $warehouse)->with('flash', ['type' => 'success', 'message' => 'Magazzino aggiornato.']);
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();
        return redirect()->route('warehouses.index')->with('flash', ['type' => 'success', 'message' => 'Magazzino eliminato.']);
    }

    public function storeStock(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|numeric|min:0',
        ]);
        WarehouseStock::updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'item_id' => $data['item_id']],
            ['quantity' => $data['quantity']]
        );
        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Giacenza aggiunta.']);
    }

    public function updateStock(Request $request, Warehouse $warehouse, WarehouseStock $stock)
    {
        if ($stock->warehouse_id !== $warehouse->id) {
            abort(404);
        }
        $data = $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);
        $stock->update($data);
        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Quantità aggiornata.']);
    }

    public function destroyStock(Warehouse $warehouse, WarehouseStock $stock)
    {
        if ($stock->warehouse_id !== $warehouse->id) {
            abort(404);
        }
        $stock->delete();
        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Giacenza eliminata.']);
    }
}
