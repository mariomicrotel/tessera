<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Conto;
use App\Models\PrimaNotaEntry;
use App\Models\Spesa;
use App\Services\AttachmentService;
use App\Services\RendicontoCassaSchemaResolver;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Spese: registrazione uscite di cassa con opzione prima nota e scelta voce rendiconto.
 */
class SpesaController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria,contabile');
    }

    public function index(Request $request)
    {
        $query = Spesa::with(['conto', 'primaNotaEntries']);

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }

        $spese = $query->orderByDesc('date')->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('Spese/Index', [
            'spese' => $spese,
            'filters' => $request->only('from', 'to'),
        ]);
    }

    public function create(Request $request)
    {
        $conti = Conto::attivi()->ordered()->get(['id', 'name', 'code']);
        if ($conti->isEmpty()) {
            return redirect()->route('spese.index')->with('flash', [
                'type' => 'warning',
                'message' => 'Nessun conto tesoreria attivo. Creare almeno un conto prima di registrare spese.',
            ]);
        }

        $schema = RendicontoCassaSchemaResolver::class();

        return Inertia::render('Spese/Create', [
            'conti' => $conti,
            'rendicontoVociUscita' => $schema::getSelectableVoicesUscita(),
            'macroAreasUscita' => $schema::getMacroAreasForSelectUscita(),
            'oldInput' => $request->old(),
        ]);
    }

    public function store(Request $request)
    {
        $schema = RendicontoCassaSchemaResolver::class();
        $validCodesUscita = $schema::getValidCodesUscita();
        $rules = [
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'conto_id' => 'required|exists:conti,id',
            'genera_prima_nota' => 'boolean',
            'rendiconto_code' => 'nullable|string|in:' . implode(',', $validCodesUscita),
            'gestione' => 'nullable|in:istituzionale,commerciale',
            'competenza_cassa' => 'boolean',
        ];
        if ($request->boolean('genera_prima_nota')) {
            $rules['rendiconto_code'] = 'required|string|in:' . implode(',', $validCodesUscita);
        }
        $request->validate($rules);

        $spesa = Spesa::create([
            'date' => $request->date,
            'amount' => $request->amount,
            'description' => $request->description,
            'conto_id' => $request->conto_id,
            'genera_prima_nota' => $request->boolean('genera_prima_nota', true),
            'rendiconto_code' => $request->rendiconto_code,
            'gestione' => $request->gestione,
            'competenza_cassa' => $request->boolean('competenza_cassa', true),
        ]);

        if ($spesa->genera_prima_nota && $spesa->rendiconto_code) {
            PrimaNotaEntry::create([
                'conto_id' => $spesa->conto_id,
                'rendiconto_code' => $spesa->rendiconto_code,
                'entryable_type' => Spesa::class,
                'entryable_id' => $spesa->id,
                'date' => $spesa->date->toDateString(),
                'amount' => -abs((float) $spesa->amount),
                'description' => $spesa->description ?? 'Spesa',
                'gestione' => $spesa->gestione ?? 'istituzionale',
                'competenza_cassa' => $spesa->competenza_cassa,
            ]);
        }

        return redirect()->route('spese.index')->with('flash', ['type' => 'success', 'message' => 'Spesa registrata.']);
    }

    public function edit(Spesa $spesa)
    {
        $spesa->load(['conto', 'primaNotaEntries']);

        $conti = Conto::attivi()->ordered()->get(['id', 'name', 'code']);

        $schema = RendicontoCassaSchemaResolver::class();

        return Inertia::render('Spese/Edit', [
            'spesa' => $spesa,
            'conti' => $conti,
            'rendicontoVociUscita' => $schema::getSelectableVoicesUscita(),
            'macroAreasUscita' => $schema::getMacroAreasForSelectUscita(),
        ]);
    }

    public function update(Request $request, Spesa $spesa)
    {
        $schema = RendicontoCassaSchemaResolver::class();
        $validCodesUscita = $schema::getValidCodesUscita();

        $rules = [
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'conto_id' => 'required|exists:conti,id',
            'rendiconto_code' => 'nullable|string|in:' . implode(',', $validCodesUscita),
            'gestione' => 'nullable|in:istituzionale,commerciale',
            'competenza_cassa' => 'boolean',
        ];

        // Se la spesa ha prima nota, rendiconto_code è obbligatorio
        if ($spesa->genera_prima_nota) {
            $rules['rendiconto_code'] = 'required|string|in:' . implode(',', $validCodesUscita);
        }

        $validated = $request->validate($rules);

        $spesa->update([
            'date' => $validated['date'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'conto_id' => $validated['conto_id'],
            'rendiconto_code' => $validated['rendiconto_code'] ?? $spesa->rendiconto_code,
            'gestione' => $validated['gestione'] ?? $spesa->gestione,
            'competenza_cassa' => $request->boolean('competenza_cassa', $spesa->competenza_cassa),
        ]);

        // Aggiorna le primaNotaEntries automatiche collegate (MorphMany)
        $spesa->primaNotaEntries()->each(function (PrimaNotaEntry $entry) use ($spesa) {
            $entry->update([
                'conto_id' => $spesa->conto_id,
                'rendiconto_code' => $spesa->rendiconto_code,
                'date' => $spesa->date->toDateString(),
                'amount' => -abs((float) $spesa->amount),
                'description' => $spesa->description ?? 'Spesa',
                'gestione' => $spesa->gestione ?? 'istituzionale',
                'competenza_cassa' => $spesa->competenza_cassa,
            ]);
        });

        return redirect()->route('spese.show', $spesa)
            ->with('flash', ['type' => 'success', 'message' => 'Spesa aggiornata.']);
    }

    public function show(Spesa $spesa)
    {
        $spesa->load(['conto', 'primaNotaEntries', 'attachments']);

        return Inertia::render('Spese/Show', [
            'spesa' => $spesa,
            'uploadMaxFileSizeHuman' => self::uploadMaxFileSizeHuman(),
        ]);
    }

    /**
     * Carica un allegato sulla spesa.
     */
    public function storeAttachment(Request $request, Spesa $spesa, AttachmentService $attachmentService)
    {
        $maxKb = (int) floor(UploadedFile::getMaxFilesize() / 1024);
        $limitKb = $maxKb > 0 ? $maxKb : 51200; // solo limite PHP; fallback 50 MB se getMaxFilesize() restituisce 0

        $request->validate([
            'file' => 'required|file|max:' . $limitKb . '|mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx',
        ], [
            'file.required' => 'Seleziona un file da caricare.',
            'file.max' => 'Il file non deve superare ' . self::uploadMaxFileSizeHuman() . ' (limite del server).',
            'file.mimes' => 'Formato non consentito. Usa PDF, immagini, Word o Excel.',
        ]);

        $file = $request->file('file');
        if ($file->getError() !== \UPLOAD_ERR_OK) {
            $message = match ($file->getError()) {
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Il file è troppo grande per le impostazioni del server. Prova con un file più piccolo o chiedi all\'amministratore di aumentare upload_max_filesize e post_max_size in PHP.',
                \UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo in parte. Riprova.',
                default => 'Errore durante l\'upload del file. Riprova.',
            };

            return redirect()->back()->with('flash', ['type' => 'error', 'message' => $message]);
        }

        try {
            $attachmentService->store($file, $spesa);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Upload allegato spesa fallito', [
                'spesa_id' => $spesa->id,
                'exception' => $e->getMessage(),
            ]);

            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Caricamento non riuscito. Riprova o contatta l\'assistenza.']);
        }

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Allegato caricato.']);
    }

    /**
     * Rimuove un allegato dalla spesa.
     */
    public function destroyAttachment(Spesa $spesa, Attachment $attachment)
    {
        if ($attachment->attachable_type !== Spesa::class || (int) $attachment->attachable_id !== (int) $spesa->id) {
            abort(404, 'Allegato non trovato su questa spesa.');
        }

        $attachment->delete();

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Allegato rimosso.']);
    }

    private static function uploadMaxFileSizeHuman(): string
    {
        $val = trim(ini_get('upload_max_filesize') ?: '2M');
        return $val;
    }

    public function destroy(Request $request, Spesa $spesa)
    {
        $spesa->primaNotaEntries()->each(fn (PrimaNotaEntry $entry) => $entry->delete());
        $spesa->delete();

        return redirect()->route('spese.index')->with('flash', ['type' => 'success', 'message' => 'Spesa eliminata.']);
    }
}
