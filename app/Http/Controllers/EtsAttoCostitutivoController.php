<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\EtsAttoCostituivo;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EtsAttoCostitutivoController extends Controller
{
    public function __construct(private AttachmentService $attachmentService)
    {
        $this->middleware('role:admin,segreteria,contabile')->only(['index', 'show']);
        $this->middleware('role:admin,segreteria')->except(['index', 'show']);
    }

    public function index()
    {
        $atti = EtsAttoCostituivo::with('attachments')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Ets/AttoCostituivo/Index', [
            'atti' => $atti,
        ]);
    }

    public function create()
    {
        return Inertia::render('Ets/AttoCostituivo/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'notaio'                => 'nullable|string|max:255',
            'repertorio'            => 'nullable|string|max:50',
            'data_atto'             => 'nullable|date',
            'data_registrazione_ae' => 'nullable|date',
            'ufficio_registro'      => 'nullable|string|max:100',
            'numero_registro'       => 'nullable|string|max:50',
            'stato'                 => ['required', Rule::in(['bozza', 'registrato'])],
            'note'                  => 'nullable|string|max:5000',
        ]);

        $atto = EtsAttoCostituivo::create($validated);

        return redirect()
            ->route('ets.atto-costitutivo.show', $atto)
            ->with('success', 'Atto costitutivo creato.');
    }

    public function show(EtsAttoCostituivo $attoCostituivo)
    {
        $attoCostituivo->load('attachments');

        return Inertia::render('Ets/AttoCostituivo/Show', [
            'atto' => $attoCostituivo,
        ]);
    }

    public function edit(EtsAttoCostituivo $attoCostituivo)
    {
        return Inertia::render('Ets/AttoCostituivo/Edit', [
            'atto' => $attoCostituivo,
        ]);
    }

    public function update(Request $request, EtsAttoCostituivo $attoCostituivo)
    {
        $validated = $request->validate([
            'notaio'                => 'nullable|string|max:255',
            'repertorio'            => 'nullable|string|max:50',
            'data_atto'             => 'nullable|date',
            'data_registrazione_ae' => 'nullable|date',
            'ufficio_registro'      => 'nullable|string|max:100',
            'numero_registro'       => 'nullable|string|max:50',
            'stato'                 => ['required', Rule::in(['bozza', 'registrato'])],
            'note'                  => 'nullable|string|max:5000',
        ]);

        $attoCostituivo->update($validated);

        return redirect()
            ->route('ets.atto-costitutivo.show', $attoCostituivo)
            ->with('success', 'Atto costitutivo aggiornato.');
    }

    public function destroy(EtsAttoCostituivo $attoCostituivo)
    {
        $this->middleware('role:admin');
        $attoCostituivo->delete();

        return redirect()
            ->route('ets.atto-costitutivo.index')
            ->with('success', 'Atto costitutivo eliminato.');
    }

    public function registra(EtsAttoCostituivo $attoCostituivo)
    {
        $attoCostituivo->update(['stato' => EtsAttoCostituivo::STATO_REGISTRATO]);

        return back()->with('success', 'Atto marcato come registrato.');
    }

    public function storeAttachment(Request $request, EtsAttoCostituivo $attoCostituivo)
    {
        $request->validate(['file' => 'required|file|max:20480|mimes:pdf,doc,docx,odt']);

        $this->attachmentService->store($request->file('file'), $attoCostituivo, 'atto_costitutivo');

        return back()->with('success', 'Allegato caricato.');
    }

    public function destroyAttachment(EtsAttoCostituivo $attoCostituivo, Attachment $attachment)
    {
        abort_if($attachment->attachable_id !== $attoCostituivo->id, 403);
        $this->attachmentService->destroy($attachment);

        return back()->with('success', 'Allegato eliminato.');
    }
}
