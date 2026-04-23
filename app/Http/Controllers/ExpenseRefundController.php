<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ExpenseRefund;
use App\Models\Member;
use App\Models\PrimaNotaEntry;
use App\Services\AttachmentService;
use App\Services\ReceiptService;
use App\Services\RendicontoCassaSchemaResolver;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rimborsi spese: richiesta (socio) → approvazione (admin/contabile) con contabilizzazione automatica (una voce prima nota per riga).
 */
class ExpenseRefundController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria,contabile,socio');
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $authMember = $user->member;
        $isSocioOnly = $authMember && $user->hasRole('socio') && ! $user->hasRole('admin') && ! $user->hasRole('segreteria') && ! $user->hasRole('contabile');

        if ($isSocioOnly && $authMember->stato !== 'attivo') {
            abort(403, 'L\'accesso all\'area soci non è disponibile per il tuo stato attuale.');
        }

        $query = ExpenseRefund::with('member')->orderByDesc('refund_date');
        if ($isSocioOnly) {
            $query->where('member_id', $authMember->id);
        } else {
            if ($request->filled('member_id')) {
                $query->where('member_id', $request->member_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('refund_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('refund_date', '<=', $request->to);
        }

        $refunds = $query->paginate(20)->withQueryString();

        $canApprove = $user->hasRole('admin') || $user->hasRole('contabile');

        $payload = [
            'refunds' => $refunds,
            'requestForSelf' => $isSocioOnly,
            'canApprove' => $canApprove,
            'filters' => $request->only('from', 'to', 'member_id', 'status'),
        ];
        if (! $isSocioOnly) {
            $payload['members'] = Member::orderBy('cognome')->orderBy('nome')->get(['id', 'nome', 'cognome']);
        }

        return Inertia::render('ExpenseRefunds/Index', $payload);
    }

    public function create()
    {
        $user = auth()->user();
        $authMember = $user->member;
        $requestForSelf = $authMember && $user->hasRole('socio') && ! $user->hasRole('admin') && ! $user->hasRole('segreteria') && ! $user->hasRole('contabile');

        if ($requestForSelf && $authMember->stato !== 'attivo') {
            abort(403, 'L\'accesso all\'area soci non è disponibile per il tuo stato attuale.');
        }

        if ($requestForSelf) {
            return Inertia::render('ExpenseRefunds/Create', [
                'members' => [],
                'requestForSelf' => true,
                'authMember' => ['id' => $authMember->id, 'full_name' => $authMember->full_name],
            ]);
        }

        return Inertia::render('ExpenseRefunds/Create', [
            'members' => Member::orderBy('cognome')->orderBy('nome')->get(['id', 'nome', 'cognome']),
            'requestForSelf' => false,
            'authMember' => null,
        ]);
    }

    /**
     * Step 1-2: Crea rimborso (richiesta socio o bozza staff).
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $authMember = $user->member;
        $requestForSelf = $authMember && $user->hasRole('socio') && ! $user->hasRole('admin') && ! $user->hasRole('segreteria') && ! $user->hasRole('contabile');

        if ($requestForSelf && $authMember->stato !== 'attivo') {
            abort(403, 'L\'accesso all\'area soci non è disponibile per il tuo stato attuale.');
        }

        $rules = [
            'member_id' => 'required|exists:members,id',
            'refund_date' => 'required|date',
            'items' => $requestForSelf ? 'required|array|min:1' : 'nullable|array',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.amount' => 'required|numeric|min:'.($requestForSelf ? '0.01' : '0'),
        ];
        $messages = [
            'items.required' => 'Inserisci almeno una voce di rimborso prima di inviare la richiesta.',
            'items.min' => 'Inserisci almeno una voce di rimborso con importo maggiore di zero.',
        ];
        $request->validate($rules, $messages);

        if ($requestForSelf && (int) $request->member_id !== (int) $authMember->id) {
            return redirect()->back()->withInput()->withErrors(['member_id' => 'Puoi richiedere un rimborso solo per te stesso.']);
        }

        $status = $requestForSelf ? 'richiesta' : 'bozza';

        $refund = ExpenseRefund::create([
            'member_id' => $request->member_id,
            'refund_date' => $request->refund_date,
            'status' => $status,
        ]);

        if ($request->filled('items')) {
            foreach ($request->items as $item) {
                $refund->refundItems()->create([
                    'description' => $item['description'] ?? '',
                    'amount' => $item['amount'],
                ]);
            }
            $refund->recalculateTotal();
        }

        $message = $requestForSelf ? 'Richiesta di rimborso inviata. In attesa di approvazione.' : 'Rimborso creato. Aggiungi le voci e stampa la ricevuta.';

        return redirect()->route('expense-refunds.show', $refund)->with('flash', ['type' => 'success', 'message' => $message]);
    }

    public function show(ExpenseRefund $expenseRefund)
    {
        $user = auth()->user();
        if ($user->member && ! $user->hasRole('admin') && ! $user->hasRole('segreteria') && ! $user->hasRole('contabile')) {
            if ($user->member->stato !== 'attivo') {
                abort(403, 'L\'accesso all\'area soci non è disponibile per il tuo stato attuale.');
            }
            if ((int) $expenseRefund->member_id !== (int) $user->member->id) {
                abort(403, 'Non autorizzato a visualizzare questo rimborso.');
            }
        }

        $expenseRefund->load(['member', 'refundItems', 'receipt', 'primaNotaEntries', 'attachments']);
        $canApprove = $user->hasRole('admin') || $user->hasRole('contabile');

        $schema = RendicontoCassaSchemaResolver::class();
        $rendicontoVociUscita = [];
        if ($canApprove) {
            $voci = $schema::getSelectableVoicesUscita();
            foreach ($voci as $v) {
                $rendicontoVociUscita[] = [
                    'code' => $v['code'],
                    'label' => $schema::getLabelForCode($v['code']),
                ];
            }
        }

        return Inertia::render('ExpenseRefunds/Show', [
            'refund' => $expenseRefund,
            'canApprove' => $canApprove,
            'rendicontoVociUscita' => $rendicontoVociUscita,
            'defaultRendicontoCode' => $schema::CODE_RIMBORSI,
            'uploadMaxFileSizeHuman' => self::uploadMaxFileSizeHuman(),
        ]);
    }

    /** Limite reale PHP (upload_max_filesize / post_max_size) in formato leggibile (es. "2 MB"). */
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

    /**
     * Step 2: Aggiorna voci rimborso (bozza o richiesta).
     */
    public function update(Request $request, ExpenseRefund $expenseRefund)
    {
        if (! in_array($expenseRefund->status, ['bozza', 'richiesta'], true)) {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Rimborso già stampato o contabilizzato.']);
        }

        $user = auth()->user();
        if ($user->member && ! $user->hasRole('admin') && ! $user->hasRole('segreteria') && ! $user->hasRole('contabile')) {
            if ($user->member->stato !== 'attivo') {
                abort(403, 'L\'accesso all\'area soci non è disponibile per il tuo stato attuale.');
            }
            if ((int) $expenseRefund->member_id !== (int) $user->member->id) {
                abort(403, 'Non autorizzato a modificare questo rimborso.');
            }
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        $expenseRefund->refundItems()->delete();
        foreach ($request->items as $item) {
            $expenseRefund->refundItems()->create([
                'description' => $item['description'] ?? '',
                'amount' => $item['amount'],
            ]);
        }
        $expenseRefund->recalculateTotal();

        return redirect()->route('expense-refunds.show', $expenseRefund)->with('flash', ['type' => 'success', 'message' => 'Voci aggiornate.']);
    }

    /**
     * Carica un allegato sul rimborso (solo se bozza o richiesta, con autorizzazione come update).
     */
    public function storeAttachment(Request $request, ExpenseRefund $expenseRefund, AttachmentService $attachmentService)
    {
        if (! in_array($expenseRefund->status, ['bozza', 'richiesta'], true)) {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Non è possibile aggiungere allegati a questo rimborso.']);
        }

        $user = auth()->user();
        if ($user->member && ! $user->hasRole('admin') && ! $user->hasRole('segreteria') && ! $user->hasRole('contabile')) {
            if ((int) $expenseRefund->member_id !== (int) $user->member->id) {
                abort(403, 'Non autorizzato a modificare questo rimborso.');
            }
        }

        $maxKb = (int) floor(UploadedFile::getMaxFilesize() / 1024);
        $limitKb = $maxKb > 0 ? $maxKb : 51200; // solo limite PHP; fallback 50 MB se getMaxFilesize() restituisce 0

        $request->validate([
            'file' => 'required|file|max:'.$limitKb.'|mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx',
        ], [
            'file.required' => 'Seleziona un file da caricare.',
            'file.max' => 'Il file non deve superare '.self::uploadMaxFileSizeHuman().' (limite del server).',
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
            $attachmentService->store($file, $expenseRefund);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Upload allegato rimborso fallito', [
                'expense_refund_id' => $expenseRefund->id,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Caricamento non riuscito. Riprova o contatta l\'assistenza.']);
        }

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Allegato caricato.']);
    }

    /**
     * Rimuove un allegato dal rimborso (solo se bozza o richiesta, con autorizzazione come update).
     */
    public function destroyAttachment(ExpenseRefund $expenseRefund, Attachment $attachment)
    {
        if (! in_array($expenseRefund->status, ['bozza', 'richiesta'], true)) {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Non è possibile rimuovere allegati da questo rimborso.']);
        }

        $user = auth()->user();
        if ($user->member && ! $user->hasRole('admin') && ! $user->hasRole('segreteria') && ! $user->hasRole('contabile')) {
            if ((int) $expenseRefund->member_id !== (int) $user->member->id) {
                abort(403, 'Non autorizzato a modificare questo rimborso.');
            }
        }

        if ($attachment->attachable_type !== ExpenseRefund::class || (int) $attachment->attachable_id !== (int) $expenseRefund->id) {
            abort(404, 'Allegato non trovato su questo rimborso.');
        }

        $attachment->delete();

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Allegato rimosso.']);
    }

    /**
     * Approva/contabilizza un rimborso: crea una voce prima nota per ogni riga di spesa e imposta stato contabilizzata.
     * Accetta sia richieste (socio) sia bozze (create da staff).
     */
    public function approva(Request $request, ExpenseRefund $expenseRefund)
    {
        if (! in_array($expenseRefund->status, ['richiesta', 'bozza'], true)) {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Solo richieste in attesa o rimborsi in bozza possono essere contabilizzati.']);
        }

        $expenseRefund->load(['refundItems', 'member']);

        if ($expenseRefund->refundItems->isEmpty()) {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Aggiungere almeno una voce di rimborso prima di contabilizzare.']);
        }

        if ($expenseRefund->primaNotaEntries()->exists()) {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Già contabilizzato.']);
        }

        $refundDate = \Carbon\Carbon::parse($expenseRefund->refund_date);
        $annoPrecedente = $refundDate->year < (int) date('Y');
        if ($annoPrecedente && ! $request->boolean('confirm_anno_precedente')) {
            return redirect()->back()->with('flash', [
                'type' => 'confirm_anno_precedente_required',
                'message' => 'Operazioni su anni precedenti possono alterare i rendiconti già generati. Vuoi procedere?',
            ]);
        }

        $schema = RendicontoCassaSchemaResolver::class();
        $validCodesUscita = $schema::getValidCodesUscita();
        $rendicontoCode = $request->input('rendiconto_code', $schema::CODE_RIMBORSI);
        if (! in_array($rendicontoCode, $validCodesUscita, true)) {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Voce contabile non valida.']);
        }

        $ref = 'Rimborso spese #' . $expenseRefund->id;
        foreach ($expenseRefund->refundItems as $item) {
            $description = trim((string) $item->description) !== ''
                ? trim($item->description) . ' – ' . $ref
                : $ref;

            $contoId = \App\Models\Conto::attivi()->ordered()->value('id');
            if (! $contoId) {
                throw new \RuntimeException('Nessun conto tesoreria attivo. Creare almeno un conto prima di contabilizzare.');
            }
            PrimaNotaEntry::create([
                'conto_id' => $contoId,
                'rendiconto_code' => $rendicontoCode,
                'entryable_type' => ExpenseRefund::class,
                'entryable_id' => $expenseRefund->id,
                'date' => $expenseRefund->refund_date,
                'amount' => -abs((float) $item->amount),
                'description' => $description,
                'gestione' => 'istituzionale',
                'competenza_cassa' => true,
            ]);
        }

        $wasRichiesta = $expenseRefund->status === 'richiesta';
        $expenseRefund->update(['status' => 'contabilizzata']);

        $message = $wasRichiesta
            ? 'Rimborso approvato e contabilizzato.'
            : 'Rimborso contabilizzato. I movimenti sono stati registrati in prima nota.';

        return redirect()->route('expense-refunds.show', $expenseRefund)->with('flash', ['type' => 'success', 'message' => $message]);
    }

    /**
     * Genera (se necessario) o restituisce il PDF della ricevuta. Sempre disponibile al download quando la ricevuta esiste o è generabile.
     */
    public function print(ExpenseRefund $expenseRefund, ReceiptService $receiptService): StreamedResponse
    {
        $user = auth()->user();
        if ($user->member && ! $user->hasRole('admin') && ! $user->hasRole('segreteria') && ! $user->hasRole('contabile')) {
            if ($user->member->stato !== 'attivo') {
                abort(403, 'L\'accesso all\'area soci non è disponibile per il tuo stato attuale.');
            }
            if ((int) $expenseRefund->member_id !== (int) $user->member->id) {
                abort(403, 'Non autorizzato.');
            }
        }

        $status = $expenseRefund->status;

        if ($expenseRefund->receipt) {
            $receipt = $expenseRefund->receipt;
        } elseif (in_array($status, ['bozza', 'approvato', 'contabilizzata'], true)) {
            if ($expenseRefund->refundItems()->count() === 0) {
                return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Aggiungere almeno una voce prima di stampare.']);
            }
            $receipt = $receiptService->generateForExpenseRefund($expenseRefund);
        } else {
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Stato non valido per la stampa.']);
        }

        if (! $receipt->file_path || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($receipt->file_path)) {
            abort(404, 'File ricevuta non trovato.');
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $receipt->file_path,
            'ricevuta-rimborso-' . $expenseRefund->id . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

}
