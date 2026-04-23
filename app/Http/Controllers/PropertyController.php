<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Property;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PropertyController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    public function index(Request $request)
    {
        $query = Property::withCount('assets')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $properties = $query->paginate(15)->withQueryString();

        return Inertia::render('Properties/Index', [
            'properties' => $properties,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Properties/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);
        $property = Property::create($data);
        return redirect()->route('properties.show', $property)->with('flash', ['type' => 'success', 'message' => 'Immobile creato.']);
    }

    public function show(Property $property)
    {
        $property->load('assets');
        return Inertia::render('Properties/Show', ['property' => $property]);
    }

    public function edit(Property $property)
    {
        return Inertia::render('Properties/Edit', ['property' => $property]);
    }

    public function update(Request $request, Property $property)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);
        $property->update($data);
        return redirect()->route('properties.show', $property)->with('flash', ['type' => 'success', 'message' => 'Immobile aggiornato.']);
    }

    public function destroy(Property $property)
    {
        $property->assets()->delete();
        $property->delete();
        return redirect()->route('properties.index')->with('flash', ['type' => 'success', 'message' => 'Immobile eliminato.']);
    }

    public function storeAsset(Request $request, Property $property)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'purchase_date' => 'nullable|date',
            'value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        $data['property_id'] = $property->id;
        Asset::create($data);
        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Cespite aggiunto.']);
    }

    public function destroyAsset(Property $property, Asset $asset)
    {
        if ($asset->property_id !== $property->id) {
            abort(404);
        }
        $asset->delete();
        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Cespite eliminato.']);
    }
}
