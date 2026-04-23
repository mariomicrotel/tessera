<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\Elezione;
use App\Models\Member;
use App\Models\Organo;
use App\Models\PartecipazioneVoto;
use App\Models\Voto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ElezioneController extends Controller
{
    public function index(Request $request)
    {
        $query = Elezione::query()->with('organo');
        if ($request->filled('stato')) {
            $query->where('stato', $request->stato);
        }
        if ($request->filled('organo_id')) {
            $query->where('organo_id', $request->organo_id);
        }
        $elezioni = $query->orderByDesc('data_elezione')->paginate(15)->withQueryString();
        $organi = Organo::orderBy('nome')->get(['id', 'slug', 'nome']);

        return Inertia::render('Elezioni/Index', [
            'elezioni' => $elezioni,
            'organi' => $organi,
            'filters' => $request->only('stato', 'organo_id'),
        ]);
    }

    public function create()
    {
        $organi = Organo::orderBy('nome')->get(['id', 'slug', 'nome']);

        return Inertia::render('Elezioni/Create', [
            'organi' => $organi,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'organo_id' => 'nullable|exists:organi,id',
            'titolo' => 'required|string|max:255',
            'data_elezione' => 'required|date',
            'permetti_astenuti' => 'boolean',
        ]);
        $organoId = $request->organo_id ?: Organo::where('slug', 'consiglio_direttivo')->first()?->id;
        $elezione = Elezione::create([
            'organo_id' => $organoId,
            'titolo' => $request->titolo,
            'data_elezione' => $request->data_elezione,
            'stato' => Elezione::STATO_BOZZA,
            'permetti_astenuti' => $request->boolean('permetti_astenuti'),
        ]);

        return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'success', 'message' => 'Elezione creata.']);
    }

    public function show(Elezione $elezione)
    {
        $elezione->load(['organo', 'candidature.member']);
        $elezione->loadCount(['candidature', 'partecipazioni', 'voti']);
        $membersForCandidati = Member::nonCessati()->orderBy('cognome')->orderBy('nome')->get(['id', 'nome', 'cognome']);
        $aventiDirittoCount = Member::nonCessati()->count();

        return Inertia::render('Elezioni/Show', [
            'elezione' => $elezione,
            'membersForCandidati' => $membersForCandidati,
            'aventiDirittoCount' => $aventiDirittoCount,
        ]);
    }

    public function edit(Elezione $elezione)
    {
        if ($elezione->stato !== Elezione::STATO_BOZZA) {
            return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'error', 'message' => 'Solo le elezioni in bozza possono essere modificate.']);
        }
        $elezione->load(['organo', 'candidature.member']);
        $organi = Organo::orderBy('nome')->get(['id', 'slug', 'nome']);
        $members = Member::nonCessati()->orderBy('cognome')->orderBy('nome')->get(['id', 'nome', 'cognome']);

        return Inertia::render('Elezioni/Edit', [
            'elezione' => $elezione,
            'organi' => $organi,
            'members' => $members,
        ]);
    }

    public function update(Request $request, Elezione $elezione)
    {
        if ($elezione->stato !== Elezione::STATO_BOZZA) {
            return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'error', 'message' => 'Solo le elezioni in bozza possono essere modificate.']);
        }
        $request->validate([
            'organo_id' => 'nullable|exists:organi,id',
            'titolo' => 'required|string|max:255',
            'data_elezione' => 'required|date',
            'permetti_astenuti' => 'boolean',
        ]);
        $data = $request->only(['organo_id', 'titolo', 'data_elezione', 'permetti_astenuti']);
        if (empty($data['organo_id'])) {
            $data['organo_id'] = Organo::where('slug', 'consiglio_direttivo')->first()?->id;
        }
        $elezione->update($data);

        return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'success', 'message' => 'Elezione aggiornata.']);
    }

    public function destroy(Elezione $elezione)
    {
        if ($elezione->stato !== Elezione::STATO_BOZZA) {
            return redirect()->route('elezioni.index')->with('flash', ['type' => 'error', 'message' => 'Solo le elezioni in bozza possono essere eliminate.']);
        }
        $elezione->delete();

        return redirect()->route('elezioni.index')->with('flash', ['type' => 'success', 'message' => 'Elezione eliminata.']);
    }

    /** Apri votazione (bozza -> aperta). */
    public function open(Elezione $elezione)
    {
        if ($elezione->stato !== Elezione::STATO_BOZZA) {
            return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'error', 'message' => 'Stato non valido.']);
        }
        if ($elezione->candidature()->count() < 1) {
            return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'error', 'message' => 'Aggiungi almeno un candidato prima di aprire.']);
        }
        $elezione->update(['stato' => Elezione::STATO_APERTA]);

        return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'success', 'message' => 'Votazione aperta.']);
    }

    /** Chiudi votazione (aperta -> chiusa). */
    public function close(Elezione $elezione)
    {
        if ($elezione->stato !== Elezione::STATO_APERTA) {
            return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'error', 'message' => 'Solo una votazione aperta può essere chiusa.']);
        }
        $elezione->update(['stato' => Elezione::STATO_CHIUSA]);

        return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'success', 'message' => 'Votazione chiusa.']);
    }

    /** Invalida votazione chiusa (con motivazione obbligatoria). */
    public function invalida(Request $request, Elezione $elezione)
    {
        if ($elezione->stato !== Elezione::STATO_CHIUSA) {
            return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'error', 'message' => 'Solo una votazione chiusa può essere invalidata.']);
        }
        if ($elezione->isInvalidata()) {
            return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'error', 'message' => 'Questa votazione è già stata invalidata.']);
        }
        $request->validate([
            'motivazione' => 'required|string|max:2000',
        ]);
        $elezione->update([
            'invalidata_at' => now(),
            'motivazione_invalidazione' => $request->motivazione,
        ]);

        return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'success', 'message' => 'Votazione invalidata.']);
    }

    /** Aggiungi candidato (solo bozza). */
    public function addCandidato(Request $request, Elezione $elezione)
    {
        if ($elezione->stato !== Elezione::STATO_BOZZA) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Non è possibile modificare i candidati.']);
        }
        $request->validate(['member_id' => 'required|exists:members,id']);
        if ($elezione->candidature()->where('member_id', $request->member_id)->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Già candidato.']);
        }
        Candidatura::create(['elezione_id' => $elezione->id, 'member_id' => $request->member_id]);

        return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'success', 'message' => 'Candidato aggiunto.']);
    }

    /** Rimuovi candidato (solo bozza). */
    public function removeCandidato(Elezione $elezione, Candidatura $candidatura)
    {
        if ($elezione->stato !== Elezione::STATO_BOZZA || $candidatura->elezione_id != $elezione->id) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Operazione non consentita.']);
        }
        $candidatura->delete();

        return redirect()->route('elezioni.show', $elezione)->with('flash', ['type' => 'success', 'message' => 'Candidato rimosso.']);
    }

    /** Pagina "Vota" per il socio. */
    public function vota(Elezione $elezione)
    {
        $user = request()->user();
        $member = $user->member;
        if (! $member) {
            return redirect()->route('dashboard', ['tenant' => request()->route('tenant')])->with('flash', ['type' => 'error', 'message' => 'Solo i soci possono votare.']);
        }
        if ($member->stato !== 'attivo') {
            return redirect()->route('dashboard', ['tenant' => request()->route('tenant')])->with('flash', ['type' => 'error', 'message' => 'Solo i soci con stato Attivo possono accedere all\'area soci e votare.']);
        }
        if ($elezione->stato !== Elezione::STATO_APERTA || ! $elezione->isAperta()) {
            return redirect()->route('dashboard', ['tenant' => request()->route('tenant')])->with('flash', ['type' => 'error', 'message' => 'Questa votazione non è aperta o non è nel periodo di voto.']);
        }
        if (PartecipazioneVoto::where('member_id', $member->id)->where('elezione_id', $elezione->id)->exists()) {
            return redirect()->route('dashboard', ['tenant' => request()->route('tenant')])->with('flash', ['type' => 'error', 'message' => 'Hai già votato.']);
        }
        $elezione->load(['candidature.member']);

        return Inertia::render('Elezioni/Vota', [
            'elezione' => $elezione,
        ]);
    }

    /** Submit voto anonimo. */
    public function storeVoto(Request $request, Elezione $elezione)
    {
        $user = $request->user();
        $member = $user->member;
        if (! $member) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Solo i soci possono votare.']);
        }
        if ($member->stato !== 'attivo') {
            return back()->with('flash', ['type' => 'error', 'message' => 'Solo i soci con stato Attivo possono votare.']);
        }
        if ($elezione->stato !== Elezione::STATO_APERTA || ! $elezione->isAperta()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Votazione non aperta.']);
        }
        if (PartecipazioneVoto::where('member_id', $member->id)->where('elezione_id', $elezione->id)->exists()) {
            return redirect()->route('dashboard', ['tenant' => request()->route('tenant')])->with('flash', ['type' => 'error', 'message' => 'Hai già votato.']);
        }
        $candidaturaIds = array_unique($request->input('candidatura_ids', []));
        if ($elezione->permetti_astenuti) {
            $request->validate([
                'candidatura_ids' => 'nullable|array',
                'candidatura_ids.*' => 'required_with:candidatura_ids|integer|exists:candidature,id',
            ]);
        } else {
            $request->validate([
                'candidatura_ids' => 'required|array',
                'candidatura_ids.*' => 'required|integer|exists:candidature,id',
            ]);
        }
        $valide = $candidaturaIds === []
            ? []
            : Candidatura::where('elezione_id', $elezione->id)->whereIn('id', $candidaturaIds)->pluck('id')->all();
        if ($candidaturaIds !== [] && count($valide) !== count($candidaturaIds)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Uno o più candidati non sono validi per questa votazione.']);
        }

        DB::transaction(function () use ($member, $elezione, $valide) {
            PartecipazioneVoto::create(['member_id' => $member->id, 'elezione_id' => $elezione->id]);
            foreach ($valide as $candidaturaId) {
                Voto::create(['elezione_id' => $elezione->id, 'candidatura_id' => $candidaturaId]);
            }
        });

        return redirect()->route('dashboard', ['tenant' => request()->route('tenant')])->with('flash', ['type' => 'success', 'message' => 'Voto registrato. Grazie per aver votato.']);
    }

    /** Pagina risultati (solo staff). */
    public function risultati(Elezione $elezione)
    {
        $elezione->load(['organo', 'candidature.member']);
        $conteggi = $elezione->candidature()->withCount('voti')->get()->map(function ($c) {
            return ['id' => $c->id, 'member' => $c->member ? ['id' => $c->member->id, 'nome' => $c->member->nome, 'cognome' => $c->member->cognome] : null, 'voti_count' => $c->voti_count];
        });
        $totaleVotanti = $elezione->partecipazioni()->count();

        return Inertia::render('Elezioni/Risultati', [
            'elezione' => $elezione,
            'conteggi' => $conteggi,
            'totaleVotanti' => $totaleVotanti,
        ]);
    }
}
