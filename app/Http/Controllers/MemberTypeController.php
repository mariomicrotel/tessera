<?php

namespace App\Http\Controllers;

use App\Models\MemberType;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * CRUD tipologie socio (socio, volontario, collaboratore).
 */
class MemberTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    public function index(Request $request)
    {
        $query = MemberType::withCount('members')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $types = $query->paginate(15)->withQueryString();

        return Inertia::render('MemberTypes/Index', [
            'memberTypes' => $types,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('MemberTypes/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:member_types,name',
            'display_name' => 'required|string|max:100',
        ]);
        MemberType::create($request->only('name', 'display_name'));
        return redirect()->route('member-types.index')->with('flash', ['type' => 'success', 'message' => 'Tipologia creata.']);
    }

    public function edit(MemberType $member_type)
    {
        return Inertia::render('MemberTypes/Edit', ['memberType' => $member_type]);
    }

    public function update(Request $request, MemberType $member_type)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:member_types,name,' . $member_type->id,
            'display_name' => 'required|string|max:100',
        ]);
        $member_type->update($request->only('name', 'display_name'));
        return redirect()->route('member-types.index')->with('flash', ['type' => 'success', 'message' => 'Tipologia aggiornata.']);
    }

    public function destroy(MemberType $member_type)
    {
        if ($member_type->members()->exists()) {
            return redirect()->route('member-types.index')->with('flash', [
                'type' => 'error',
                'message' => 'Impossibile eliminare: ci sono soci associati a questa tipologia.',
            ]);
        }
        $member_type->delete();
        return redirect()->route('member-types.index')->with('flash', ['type' => 'success', 'message' => 'Tipologia eliminata.']);
    }
}
