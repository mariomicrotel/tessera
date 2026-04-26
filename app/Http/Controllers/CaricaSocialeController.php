<?php

namespace App\Http\Controllers;

use App\Models\CaricaSociale;
use App\Models\Organo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestione cariche sociali (CDA, Presidenza, Collegio Sindacale, ecc.).
 * Ogni carica appartiene a un organo e può essere multi-assegnabile.
 */
class CaricaSocialeController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    // ── Elenco ───────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $cariche = CaricaSociale::with('organo')
            ->when($request->integer('organo_id'),
                fn ($q) => $q->where('organo_id', $request->integer('organo_id')))
            ->orderBy('organo_id')
            ->orderBy('ordine')
            ->get();

        return Inertia::render('Amministrazione/CaricaSociale/Index', [
            'cariche' => $cariche,
            'organi'  => Organo::orderBy('nome')->get(['id', 'slug', 'nome']),
            'filters' => $request->only('organo_id'),
        ]);
    }

    // ── Creazione ─────────────────────────────────────────────────────────────

    public function create(): Response
    {
        $this->middleware('role:admin');

        return Inertia::render('Amministrazione/CaricaSociale/Create', [
            'organi' => Organo::orderBy('nome')->get(['id', 'slug', 'nome']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\Member::class); // stesso livello di access

        $data = $request->validate([
            'organo_id' => 'required|exists:organi,id',
            'nome'      => 'required|string|max:100',
            'ordine'    => 'required|integer|min:0',
            'multiplo'  => 'boolean',
        ]);

        // Verifica unicità nome nell'organo
        $exists = CaricaSociale::where('organo_id', $data['organo_id'])
            ->where('nome', $data['nome'])
            ->exists();

        if ($exists) {
            return back()->withInput()->with('flash', [
                'type'    => 'error',
                'message' => "Esiste già una carica '{$data['nome']}' in questo organo.",
            ]);
        }

        $carica = CaricaSociale::create($data);

        return redirect()
            ->route('cariche-sociali.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => "Carica '{$carica->nome}' creata."]);
    }

    // ── Dettaglio ─────────────────────────────────────────────────────────────

    public function show(CaricaSociale $caricaSociale): Response
    {
        $caricaSociale->load(['organo', 'incarichi.member']);

        return Inertia::render('Amministrazione/CaricaSociale/Show', [
            'carica' => $caricaSociale,
        ]);
    }

    // ── Modifica ─────────────────────────────────────────────────────────────

    public function edit(CaricaSociale $caricaSociale): Response
    {
        $caricaSociale->load('organo');

        return Inertia::render('Amministrazione/CaricaSociale/Edit', [
            'carica' => $caricaSociale,
            'organi' => Organo::orderBy('nome')->get(['id', 'slug', 'nome']),
        ]);
    }

    public function update(Request $request, CaricaSociale $caricaSociale): RedirectResponse
    {
        $data = $request->validate([
            'organo_id' => 'required|exists:organi,id',
            'nome'      => 'required|string|max:100',
            'ordine'    => 'required|integer|min:0',
            'multiplo'  => 'boolean',
        ]);

        // Verifica unicità nome nell'organo (escludi se stessa)
        $exists = CaricaSociale::where('organo_id', $data['organo_id'])
            ->where('nome', $data['nome'])
            ->where('id', '!=', $caricaSociale->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('flash', [
                'type'    => 'error',
                'message' => "Esiste già una carica '{$data['nome']}' in questo organo.",
            ]);
        }

        $caricaSociale->update($data);

        return redirect()
            ->route('cariche-sociali.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => "Carica '{$caricaSociale->nome}' aggiornata."]);
    }

    // ── Elimina ───────────────────────────────────────────────────────────────

    public function destroy(CaricaSociale $caricaSociale): RedirectResponse
    {
        // Blocca se ci sono incarichi attivi collegati
        if ($caricaSociale->incarichi()->exists()) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => "Impossibile eliminare: esistono incarichi associati a questa carica.",
            ]);
        }

        $nome = $caricaSociale->nome;
        $caricaSociale->delete();

        return redirect()
            ->route('cariche-sociali.index', request()->route('tenant'))
            ->with('flash', ['type' => 'success', 'message' => "Carica '{$nome}' eliminata."]);
    }
}
