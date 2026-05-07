<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Tessera;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class TesseraController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Tessera::class);

        $anno   = (int) $request->input('anno', now()->year);
        $stato  = $request->input('stato', '');
        $search = trim($request->input('search', ''));

        $query = Tessera::with('member')
            ->where('anno', $anno)
            ->when($stato, fn ($q) => $q->where('stato', $stato))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('member', function ($m) use ($search) {
                    $m->where(fn ($s) =>
                        $s->where('cognome', 'like', "%$search%")
                          ->orWhere('nome', 'like', "%$search%")
                          ->orWhere('codice_fiscale', 'like', "%$search%")
                    );
                })->orWhere('numero', 'like', "%$search%");
            })
            ->orderBy('numero');

        $tessere = $query->paginate(50)->withQueryString();

        return Inertia::render('Tessere/Index', [
            'tessere' => $tessere,
            'filters' => compact('anno', 'stato', 'search'),
            'anni'    => $this->anniDisponibili(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Tessera::class);

        $data = $request->validate([
            'member_ids'    => 'required|array|min:1',
            'member_ids.*'  => 'integer|exists:members,id',
            'anno'          => 'required|integer|min:2000|max:2100',
            'data_emissione'=> 'nullable|date',
            'data_scadenza' => 'nullable|date|after_or_equal:data_emissione',
            'stato'         => 'required|in:bozza,emessa',
            'note'          => 'nullable|string|max:500',
        ]);

        $created = 0;
        foreach ($data['member_ids'] as $memberId) {
            $exists = Tessera::where('member_id', $memberId)->where('anno', $data['anno'])->exists();
            if ($exists) continue;

            Tessera::create([
                'member_id'     => $memberId,
                'numero'        => Tessera::nextNumero($data['anno']),
                'anno'          => $data['anno'],
                'data_emissione'=> $data['data_emissione'] ?? null,
                'data_scadenza' => $data['data_scadenza'] ?? null,
                'stato'         => $data['stato'],
                'note'          => $data['note'] ?? null,
            ]);
            $created++;
        }

        return back()->with('success', "Tessere create: $created.");
    }

    public function storeSingola(Request $request)
    {
        $this->authorize('create', Tessera::class);

        $data = $request->validate([
            'member_id'     => 'required|integer|exists:members,id',
            'anno'          => 'required|integer|min:2000|max:2100',
            'data_emissione'=> 'nullable|date',
            'data_scadenza' => 'nullable|date',
            'stato'         => 'required|in:bozza,emessa',
            'note'          => 'nullable|string|max:500',
        ]);

        $exists = Tessera::where('member_id', $data['member_id'])->where('anno', $data['anno'])->exists();
        if ($exists) {
            return back()->withErrors(['member_id' => 'Tessera già emessa per questo anno.']);
        }

        Tessera::create([
            'member_id'     => $data['member_id'],
            'numero'        => Tessera::nextNumero($data['anno']),
            'anno'          => $data['anno'],
            'data_emissione'=> $data['data_emissione'] ?? null,
            'data_scadenza' => $data['data_scadenza'] ?? null,
            'stato'         => $data['stato'],
            'note'          => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Tessera creata.');
    }

    public function update(Request $request, Tessera $tessera)
    {
        $this->authorize('update', $tessera);

        $data = $request->validate([
            'data_emissione'=> 'nullable|date',
            'data_scadenza' => 'nullable|date',
            'stato'         => 'required|in:bozza,emessa,scaduta,revocata',
            'note'          => 'nullable|string|max:500',
        ]);

        $tessera->update($data);

        return back()->with('success', 'Tessera aggiornata.');
    }

    public function destroy(Tessera $tessera)
    {
        $this->authorize('delete', $tessera);

        $tessera->delete();

        return back()->with('success', 'Tessera eliminata.');
    }

    public function emettiBulk(Request $request)
    {
        $this->authorize('create', Tessera::class);

        $data = $request->validate([
            'anno'          => 'required|integer|min:2000|max:2100',
            'data_emissione'=> 'required|date',
            'data_scadenza' => 'nullable|date|after_or_equal:data_emissione',
        ]);

        $aggiornate = Tessera::where('anno', $data['anno'])
            ->where('stato', Tessera::STATO_BOZZA)
            ->update([
                'stato'          => Tessera::STATO_EMESSA,
                'data_emissione' => $data['data_emissione'],
                'data_scadenza'  => $data['data_scadenza'] ?? null,
            ]);

        return back()->with('success', "Tessere emesse: $aggiornate.");
    }

    public function aggiornaScadute()
    {
        $this->authorize('create', Tessera::class);

        $aggiornate = Tessera::where('stato', Tessera::STATO_EMESSA)
            ->whereNotNull('data_scadenza')
            ->where('data_scadenza', '<', Carbon::today())
            ->update(['stato' => Tessera::STATO_SCADUTA]);

        return back()->with('success', "Tessere marcate come scadute: $aggiornate.");
    }

    private function anniDisponibili(): array
    {
        $anni = Tessera::selectRaw('anno')->groupBy('anno')->orderByDesc('anno')->pluck('anno')->toArray();
        $current = now()->year;
        if (!in_array($current, $anni)) {
            array_unshift($anni, $current);
        }
        return $anni;
    }
}
