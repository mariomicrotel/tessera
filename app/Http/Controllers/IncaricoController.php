<?php

namespace App\Http\Controllers;

use App\Models\Incarico;
use App\Models\Member;
use Illuminate\Http\Request;

class IncaricoController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    public function store(Request $request, Member $member)
    {
        $this->authorize('update', $member);
        $request->validate([
            'carica_sociale_id' => 'required|exists:cariche_sociali,id',
        ]);

        Incarico::create([
            'member_id' => $member->id,
            'carica_sociale_id' => $request->carica_sociale_id,
        ]);

        if ($request->filled('redirect_to_organo_slug')) {
            return redirect()->route('organi.show', $request->redirect_to_organo_slug)
                ->with('flash', ['type' => 'success', 'message' => 'Carica assegnata.']);
        }

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Carica assegnata.']);
    }

    public function destroy(Request $request, Incarico $incarico)
    {
        $member = $incarico->member;
        $this->authorize('update', $member);
        $incarico->delete();

        if ($request->filled('redirect_to_organo_slug')) {
            return redirect()->route('organi.show', $request->redirect_to_organo_slug)
                ->with('flash', ['type' => 'success', 'message' => 'Incarico rimosso.']);
        }

        return redirect()->route('members.show', $member)->with('flash', ['type' => 'success', 'message' => 'Incarico rimosso.']);
    }
}
