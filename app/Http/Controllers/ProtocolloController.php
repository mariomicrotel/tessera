<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Protocollo;
use App\Models\Receipt;
use App\Services\AttachmentService;
use App\Services\ProtocolloService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProtocolloController extends Controller
{
    public function __construct(
        private AttachmentService $attachmentService,
        private ProtocolloService $protocolloService,
    ) {
        $this->middleware('role:admin,segreteria,contabile');
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $annoCorrente = (int) now()->year;
        $anno = (int) ($request->input('anno', $annoCorrente));

        $query = Protocollo::query()
            ->where('anno', $anno)
            ->withCount('attachments')
            ->orderByDesc('numero');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('oggetto', 'like', $search)
                  ->orWhere('mittente', 'like', $search)
                  ->orWhere('destinatario', 'like', $search);
            });
        }

        $protocolli = $query->paginate(20)->withQueryString();

        // Range di anni disponibili: dal primo anno presente fino a corrente+1
        $primoAnno = (int) (Protocollo::withoutGlobalScope('tenant')->min('anno') ?? $annoCorrente);
        $anniDisponibili = range($primoAnno, $annoCorrente + 1);

        return Inertia::render('Protocolli/Index', [
            'protocolli'       => $protocolli,
            'filters'          => $request->only('tipo', 'anno', 'search'),
            'anni_disponibili' => $anniDisponibili,
            'tipi'             => [Protocollo::TIPO_ENTRATA, Protocollo::TIPO_USCITA],
            'receipts'         => Receipt::orderByDesc('issued_at')
                                    ->get(['id', 'number', 'issued_at']),
        ]);
    }

    // -------------------------------------------------------------------------
    // Create / Store
    // -------------------------------------------------------------------------

    public function create()
    {
        return Inertia::render('Protocolli/Create', [
            'tipi'     => [Protocollo::TIPO_ENTRATA, Protocollo::TIPO_USCITA],
            'receipts' => Receipt::orderByDesc('issued_at')
                            ->take(100)
                            ->get(['id', 'number', 'issued_at']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo'               => 'required|in:entrata,uscita',
            'data_registrazione' => 'required|date',
            'oggetto'            => 'required|string|max:500',
            'mittente'           => 'nullable|string|max:300',
            'destinatario'       => 'nullable|string|max:300',
            'note'               => 'nullable|string|max:5000',
            'receipt_id'         => 'nullable|exists:receipts,id',
        ]);

        $data = [
            'tipo'               => $validated['tipo'],
            'data_registrazione' => $validated['data_registrazione'],
            'oggetto'            => $validated['oggetto'],
            'mittente'           => $validated['mittente'] ?? null,
            'destinatario'       => $validated['destinatario'] ?? null,
            'note'               => $validated['note'] ?? null,
        ];

        if (!empty($validated['receipt_id'])) {
            $data['linked_type'] = Receipt::class;
            $data['linked_id']   = (int) $validated['receipt_id'];
        }

        $protocollo = $this->protocolloService->crea($data);

        return redirect()->route('protocolli.show', $protocollo)
            ->with('flash', ['type' => 'success', 'message' => 'Protocollo ' . $protocollo->numero_formattato . ' registrato.']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function show(Protocollo $protocollo)
    {
        $protocollo->load(['attachments', 'createdBy']);

        // Carica l'entità collegata con eventuali relazioni annidate
        if ($protocollo->linked_type && $protocollo->linked_id) {
            $protocollo->loadMorph('linked', [
                Receipt::class => ['member'],
            ]);
        }

        return Inertia::render('Protocolli/Show', [
            'protocollo'             => $protocollo,
            'uploadMaxFileSizeHuman' => self::uploadMaxFileSizeHuman(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Edit / Update
    // -------------------------------------------------------------------------

    public function edit(Protocollo $protocollo)
    {
        $protocollo->load(['attachments', 'createdBy']);

        if ($protocollo->linked_type && $protocollo->linked_id) {
            $protocollo->loadMorph('linked', [
                Receipt::class => ['member'],
            ]);
        }

        return Inertia::render('Protocolli/Edit', [
            'protocollo'             => $protocollo,
            'tipi'                   => [Protocollo::TIPO_ENTRATA, Protocollo::TIPO_USCITA],
            'receipts'               => Receipt::orderByDesc('issued_at')
                                            ->take(100)
                                            ->get(['id', 'number', 'issued_at']),
            'uploadMaxFileSizeHuman' => self::uploadMaxFileSizeHuman(),
        ]);
    }

    public function update(Request $request, Protocollo $protocollo)
    {
        $validated = $request->validate([
            'tipo'               => 'required|in:entrata,uscita',
            'data_registrazione' => 'required|date',
            'oggetto'            => 'required|string|max:500',
            'mittente'           => 'nullable|string|max:300',
            'destinatario'       => 'nullable|string|max:300',
            'note'               => 'nullable|string|max:5000',
            'receipt_id'         => 'nullable|exists:receipts,id',
        ]);

        $data = [
            'tipo'               => $validated['tipo'],
            'data_registrazione' => $validated['data_registrazione'],
            'oggetto'            => $validated['oggetto'],
            'mittente'           => $validated['mittente'] ?? null,
            'destinatario'       => $validated['destinatario'] ?? null,
            'note'               => $validated['note'] ?? null,
        ];

        if (!empty($validated['receipt_id'])) {
            $data['linked_type'] = Receipt::class;
            $data['linked_id']   = (int) $validated['receipt_id'];
        } else {
            $data['linked_type'] = null;
            $data['linked_id']   = null;
        }

        $protocollo->update($data);

        return redirect()->route('protocolli.show', $protocollo)
            ->with('flash', ['type' => 'success', 'message' => 'Protocollo ' . $protocollo->numero_formattato . ' aggiornato.']);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function destroy(Protocollo $protocollo)
    {
        $protocollo->delete();

        return redirect()->route('protocolli.index')
            ->with('flash', ['type' => 'success', 'message' => 'Protocollo eliminato.']);
    }

    // -------------------------------------------------------------------------
    // Allegati
    // -------------------------------------------------------------------------

    public function storeAttachment(Request $request, Protocollo $protocollo)
    {
        $maxKb = (int) floor(UploadedFile::getMaxFilesize() / 1024);
        $limitKb = $maxKb > 0 ? $maxKb : 51200; // fallback 50 MB se getMaxFilesize() restituisce 0

        $request->validate([
            'file' => 'required|file|max:' . $limitKb . '|mimes:pdf,jpg,jpeg,png,doc,docx',
        ], [
            'file.required' => 'Seleziona un file da caricare.',
            'file.max'      => 'Il file non deve superare ' . self::uploadMaxFileSizeHuman() . ' (limite del server).',
            'file.mimes'    => 'Formato non consentito. Usa PDF, immagini JPG/PNG, Word.',
        ]);

        $file = $request->file('file');

        if ($file->getError() !== \UPLOAD_ERR_OK) {
            $message = match ($file->getError()) {
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Il file è troppo grande per le impostazioni del server.',
                \UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo in parte. Riprova.',
                default => 'Errore durante l\'upload del file. Riprova.',
            };

            return redirect()->back()->with('flash', ['type' => 'error', 'message' => $message]);
        }

        try {
            $this->attachmentService->store($file, $protocollo);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Upload allegato protocollo fallito', [
                'protocollo_id' => $protocollo->id,
                'exception'     => $e->getMessage(),
            ]);

            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Caricamento non riuscito. Riprova o contatta l\'assistenza.']);
        }

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Allegato caricato.']);
    }

    public function destroyAttachment(Protocollo $protocollo, Attachment $attachment)
    {
        if ($attachment->attachable_type !== Protocollo::class || (int) $attachment->attachable_id !== (int) $protocollo->id) {
            abort(404, 'Allegato non trovato su questo protocollo.');
        }

        $attachment->delete();

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Allegato rimosso.']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function uploadMaxFileSizeHuman(): string
    {
        $bytes = (int) UploadedFile::getMaxFilesize();
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
