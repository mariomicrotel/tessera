<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\EtsStatuto;
use App\Models\EtsStatutoClausola;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EtsStatutoController extends Controller
{
    public function __construct(private AttachmentService $attachmentService)
    {
        $this->middleware('role:admin,segreteria,contabile')->only(['index', 'show']);
        $this->middleware('role:admin,segreteria')->except(['index', 'show']);
    }

    public function index()
    {
        $statuti = EtsStatuto::withCount('clausole')
            ->with('attachments')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Ets/Statuto/Index', [
            'statuti' => $statuti,
        ]);
    }

    public function create()
    {
        $prossimaVersione = $this->prossimaVersione();

        return Inertia::render('Ets/Statuto/Create', [
            'prossimaVersione' => $prossimaVersione,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'versione'          => 'required|string|max:20',
            'titolo'            => 'required|string|max:255',
            'stato'             => ['required', Rule::in(['bozza', 'approvato', 'archiviato'])],
            'data_approvazione' => 'nullable|date',
            'data_deposito'     => 'nullable|date',
            'note'              => 'nullable|string|max:5000',
            'clausole'          => 'nullable|array',
            'clausole.*.numero_articolo' => 'required|string|max:10',
            'clausole.*.titolo'          => 'nullable|string|max:255',
            'clausole.*.testo'           => 'required|string',
            'clausole.*.articolo_cts'    => 'nullable|string|max:80',
            'clausole.*.ordine'          => 'nullable|integer|min:0',
        ]);

        $statuto = EtsStatuto::create($validated);
        $this->salvaClausole($statuto, $validated['clausole'] ?? []);

        return redirect()
            ->route('ets.statuto.show', $statuto)
            ->with('success', 'Statuto creato con successo.');
    }

    public function show(EtsStatuto $statuto)
    {
        $statuto->load(['clausole', 'attachments']);

        return Inertia::render('Ets/Statuto/Show', [
            'statuto' => $statuto,
        ]);
    }

    public function edit(EtsStatuto $statuto)
    {
        $statuto->load('clausole');

        return Inertia::render('Ets/Statuto/Edit', [
            'statuto' => $statuto,
        ]);
    }

    public function update(Request $request, EtsStatuto $statuto)
    {
        $validated = $request->validate([
            'versione'          => 'required|string|max:20',
            'titolo'            => 'required|string|max:255',
            'stato'             => ['required', Rule::in(['bozza', 'approvato', 'archiviato'])],
            'data_approvazione' => 'nullable|date',
            'data_deposito'     => 'nullable|date',
            'note'              => 'nullable|string|max:5000',
            'clausole'          => 'nullable|array',
            'clausole.*.id'              => 'nullable|integer',
            'clausole.*.numero_articolo' => 'required|string|max:10',
            'clausole.*.titolo'          => 'nullable|string|max:255',
            'clausole.*.testo'           => 'required|string',
            'clausole.*.articolo_cts'    => 'nullable|string|max:80',
            'clausole.*.compliance_ok'   => 'nullable|boolean',
            'clausole.*.note_compliance' => 'nullable|string|max:1000',
            'clausole.*.ordine'          => 'nullable|integer|min:0',
        ]);

        $statuto->update($validated);
        $this->salvaClausole($statuto, $validated['clausole'] ?? []);

        return redirect()
            ->route('ets.statuto.show', $statuto)
            ->with('success', 'Statuto aggiornato.');
    }

    public function destroy(EtsStatuto $statuto)
    {
        $this->middleware('role:admin');
        $statuto->delete();

        return redirect()
            ->route('ets.statuto.index')
            ->with('success', 'Statuto eliminato.');
    }

    public function approva(EtsStatuto $statuto)
    {
        $statuto->update([
            'stato'             => EtsStatuto::STATO_APPROVATO,
            'data_approvazione' => $statuto->data_approvazione ?? now()->toDateString(),
        ]);

        return back()->with('success', 'Statuto approvato.');
    }

    public function archivia(EtsStatuto $statuto)
    {
        $statuto->update(['stato' => EtsStatuto::STATO_ARCHIVIATO]);

        return back()->with('success', 'Statuto archiviato.');
    }

    public function storeAttachment(Request $request, EtsStatuto $statuto)
    {
        $request->validate(['file' => 'required|file|max:20480|mimes:pdf,doc,docx,odt']);

        $this->attachmentService->store($request->file('file'), $statuto, 'statuto');

        return back()->with('success', 'Allegato caricato.');
    }

    public function destroyAttachment(EtsStatuto $statuto, Attachment $attachment)
    {
        abort_if($attachment->attachable_id !== $statuto->id, 403);
        $this->attachmentService->destroy($attachment);

        return back()->with('success', 'Allegato eliminato.');
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function salvaClausole(EtsStatuto $statuto, array $clausole): void
    {
        $tenant = app('current_tenant');
        $idsDaMantenere = [];

        foreach ($clausole as $i => $dati) {
            $dati['ordine'] = $dati['ordine'] ?? $i;

            if (!empty($dati['id'])) {
                $clausola = EtsStatutoClausola::find($dati['id']);
                if ($clausola && $clausola->statuto_id === $statuto->id) {
                    $clausola->update($dati);
                    $idsDaMantenere[] = $clausola->id;
                    continue;
                }
            }

            $clausola = EtsStatutoClausola::create(array_merge($dati, [
                'statuto_id' => $statuto->id,
                'tenant_id'  => $tenant->id,
            ]));
            $idsDaMantenere[] = $clausola->id;
        }

        // Elimina clausole rimosse dall'editor
        EtsStatutoClausola::where('statuto_id', $statuto->id)
            ->whereNotIn('id', $idsDaMantenere)
            ->delete();
    }

    private function prossimaVersione(): string
    {
        $tenant = app('current_tenant');
        $count  = EtsStatuto::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count();

        return date('Y').'-v'.($count + 1);
    }
}
