<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    public function index(Request $request)
    {
        $query = Location::withCount('warehouses')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $locations = $query->paginate(15)->withQueryString();

        return Inertia::render('Locations/Index', [
            'locations' => $locations,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        $hasLegalLocation = Location::where('tipo', Location::TIPO_LEGALE)->exists();
        return Inertia::render('Locations/Create', ['hasLegalLocation' => $hasLegalLocation]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'tipo' => 'required|in:legale,operativa',
        ]);
        if ($data['tipo'] === Location::TIPO_LEGALE && Location::where('tipo', Location::TIPO_LEGALE)->exists()) {
            throw ValidationException::withMessages(['tipo' => 'Esiste già una sede legale. Può esserci solo una sede legale.']);
        }
        Location::create($data);
        return redirect()->route('locations.index')->with('flash', ['type' => 'success', 'message' => 'Sede creata.']);
    }

    public function edit(Location $location)
    {
        $hasOtherLegalLocation = Location::where('tipo', Location::TIPO_LEGALE)->where('id', '!=', $location->id)->exists();
        return Inertia::render('Locations/Edit', [
            'location' => $location,
            'hasOtherLegalLocation' => $hasOtherLegalLocation,
        ]);
    }

    public function update(Request $request, Location $location)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'tipo' => 'required|in:legale,operativa',
        ]);
        if ($data['tipo'] === Location::TIPO_LEGALE) {
            $otherLegal = Location::where('tipo', Location::TIPO_LEGALE)->where('id', '!=', $location->id)->exists();
            if ($otherLegal) {
                throw ValidationException::withMessages(['tipo' => 'Esiste già una sede legale. Può esserci solo una sede legale.']);
            }
        }
        $location->update($data);
        return redirect()->route('locations.index')->with('flash', ['type' => 'success', 'message' => 'Sede aggiornata.']);
    }

    public function destroy(Location $location)
    {
        $location->delete();
        return redirect()->route('locations.index')->with('flash', ['type' => 'success', 'message' => 'Sede eliminata.']);
    }
}
