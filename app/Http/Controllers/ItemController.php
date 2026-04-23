<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    public function index(Request $request)
    {
        $query = Item::orderBy('name');

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('code', 'like', $term);
            });
        }

        $items = $query->paginate(15)->withQueryString();

        return Inertia::render('Items/Index', [
            'items' => $items,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Items/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100|unique:items,code',
            'unit' => 'nullable|string|max:20',
        ]);
        $data['unit'] = $data['unit'] ?? 'pz';
        Item::create($data);
        return redirect()->route('items.index')->with('flash', ['type' => 'success', 'message' => 'Articolo creato.']);
    }

    public function edit(Item $item)
    {
        return Inertia::render('Items/Edit', ['item' => $item]);
    }

    public function update(Request $request, Item $item)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100|unique:items,code,' . $item->id,
            'unit' => 'nullable|string|max:20',
        ]);
        $data['unit'] = $data['unit'] ?? 'pz';
        $item->update($data);
        return redirect()->route('items.index')->with('flash', ['type' => 'success', 'message' => 'Articolo aggiornato.']);
    }

    public function destroy(Item $item)
    {
        $item->delete();
        return redirect()->route('items.index')->with('flash', ['type' => 'success', 'message' => 'Articolo eliminato.']);
    }
}
