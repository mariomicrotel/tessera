<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Organo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganoController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    public function index(Request $request)
    {
        $query = Organo::query()->orderBy('nome');

        if ($request->filled('search')) {
            $query->where('nome', 'like', '%' . $request->search . '%');
        }

        $organi = $query->paginate(15)->withQueryString();

        return Inertia::render('Organi/Index', [
            'organi' => $organi,
            'filters' => $request->only('search'),
        ]);
    }

    public function show(Organo $organo)
    {
        $organo->load(['caricheSociali' => ['incarichi' => ['member:id,cognome,nome']]]);
        $organo->loadCount('caricheSociali');
        $organo->setAttribute('mandato_da', $organo->dataInizioMandato()?->toDateString());
        $organo->setAttribute('mandato_scadenza', $organo->mandatoScadenza()?->toDateString());
        $members = Member::nonCessati()->orderBy('cognome')->orderBy('nome')->get(['id', 'cognome', 'nome']);

        return Inertia::render('Organi/Show', ['organo' => $organo, 'members' => $members]);
    }

    public function update(Request $request, Organo $organo)
    {
        $validated = $request->validate([
            'durata_mesi' => ['nullable', 'integer', 'min:1', 'max:120'],
            'richiedi_elezioni_fine_mandato' => ['boolean'],
        ]);
        $organo->update($validated);

        return redirect()->route('organi.show', $organo)->with('flash', ['type' => 'success', 'message' => 'Impostazioni aggiornate.']);
    }
}
