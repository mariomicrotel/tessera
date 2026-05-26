<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Conto;
use App\Models\EmailTemplate;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\PrimaNotaEntry;
use App\Models\ReceiptTemplate;
use App\Models\Settings;
use App\Models\Subscription;
use App\Services\AttachmentService;
use App\Services\ReceiptService;
use App\Services\RendicontoCassaSchema;
use App\Services\RendicontoCassaSchemaCooperativa;
use App\Services\RendicontoCassaSchemaResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Gestione incassi (quote e donazioni). Opzione emissione ricevuta e prima nota.
 */
class IncassoController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria,contabile');
    }

    /**
     * Redirect per compatibilità: vecchi link incassi → quote sociali.
     */
    public function index(Request $request)
    {
        return redirect()->route('quote-sociali.index', $request->query());
    }

    /**
     * Elenco quote sociali (solo type = quota).
     */
    public function indexQuote(Request $request)
    {
        $query = Incasso::with(['member', 'subscription', 'conto', 'receipt'])
            ->where('type', Incasso::TYPE_QUOTA);

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->member_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', $request->to);
        }

        $incassi = $query->orderByDesc('paid_at')->paginate(20)->withQueryString();
        return Inertia::render('QuoteSociali/Index', [
            'incassi' => $incassi,
            'filters' => $request->only('member_id', 'from', 'to'),
            'members' => Member::orderBy('cognome')->orderBy('nome')->get(['id', 'nome', 'cognome']),
        ]);
    }

    /**
     * Elenco erogazioni liberali (solo type = donazione).
     */
    public function indexDonazioni(Request $request)
    {
        $query = Incasso::with(['member', 'conto', 'receipt'])
            ->where('type', Incasso::TYPE_DONAZIONE);

        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', $request->to);
        }

        $incassi = $query->orderByDesc('paid_at')->paginate(20)->withQueryString();
        return Inertia::render('Donazioni/Index', [
            'incassi' => $incassi,
            'filters' => $request->only('from', 'to'),
        ]);
    }

    /**
     * Elenco incassi generici (solo type = altro).
     */
    public function indexIncassiGenerici(Request $request)
    {
        $query = Incasso::with(['member', 'conto', 'receipt'])
            ->where('type', Incasso::TYPE_ALTRO);

        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', $request->to);
        }

        $incassi = $query->orderByDesc('paid_at')->paginate(20)->withQueryString();
        return Inertia::render('IncassiGenerici/Index', [
            'incassi' => $incassi,
            'filters' => $request->only('from', 'to'),
        ]);
    }

    public function create(Request $request)
    {
        $preselectedType = $request->get('type', 'quota');
        if (! in_array($preselectedType, ['quota', 'donazione', 'altro', 'capitale', 'prestito_sociale'], true)) {
            $preselectedType = 'quota';
        }
        $memberId = $request->get('member_id');
        $subscriptionId = $request->get('subscription_id');
        $member = $memberId ? Member::with('subscriptions')->find($memberId) : null;
        $preselectedSubscriptionId = null;
        $preselectedAmount = null;
        $preselectedDescription = null;
        $quotaAmount   = Settings::get('quota_annuale', 0);
        $quotaMensile  = Settings::get('quota_mensile', 0);
        $quotaIngresso = Settings::get('quota_ingresso', 0);
        $causaleDefaultQuota = Settings::get('causale_default_quota', 'Quota associativa');
        $causaleDefaultDonazione = Settings::get('causale_default_donazione', 'Erogazione liberale');
        if ($subscriptionId) {
            $sub = Subscription::find($subscriptionId);
            if ($sub) {
                $preselectedSubscriptionId = (int) $sub->id;
                $preselectedAmount = (string) number_format((float) $quotaAmount, 2, '.', '');
                $preselectedDescription = $causaleDefaultQuota . ' ' . $sub->year;
            }
        }
        if ($preselectedDescription === null && $preselectedType === 'quota') {
            $preselectedDescription = $causaleDefaultQuota;
        }
        if ($preselectedType === 'altro') {
            $preselectedDescription = '';
        }
        $conti = Conto::attivi()->ordered()->get(['id', 'name', 'code']);
        if ($conti->isEmpty()) {
            return redirect()->route('quote-sociali.index')->with('flash', [
                'type' => 'warning',
                'message' => 'Nessun conto tesoreria attivo. Creare almeno un conto prima di registrare incassi.',
            ]);
        }
        $schema = RendicontoCassaSchemaResolver::class();
        $rendicontoVociEntrata = [];
        foreach ($schema::getSelectableVoicesEntrata() as $v) {
            $rendicontoVociEntrata[] = [
                'code' => $v['code'],
                'label' => $schema::getLabelForCode($v['code']),
            ];
        }
        return Inertia::render('Incassi/Create', [
            'members' => Member::with('subscriptions')->orderBy('cognome')->orderBy('nome')->get(['id', 'nome', 'cognome']),
            'conti' => $conti,
            'preselectedType' => $preselectedType,
            'preselectedMember' => $member,
            'preselectedSubscriptionId' => $preselectedSubscriptionId,
            'preselectedAmount' => $preselectedAmount,
            'preselectedDescription' => $preselectedDescription,
            'quota_annuale'  => $quotaAmount,
            'quota_mensile'  => $quotaMensile,
            'quota_ingresso' => $quotaIngresso,
            'causale_default_quota' => $causaleDefaultQuota,
            'causale_default_donazione' => $causaleDefaultDonazione,
            'rendicontoVociEntrata' => $rendicontoVociEntrata,
            'receiptTemplateTexts' => [
                Incasso::TYPE_QUOTA => ReceiptTemplate::getBodyForTipo('incasso_quota'),
                Incasso::TYPE_DONAZIONE => ReceiptTemplate::getBodyForTipo('incasso_donazione'),
                Incasso::TYPE_ALTRO => ReceiptTemplate::getBodyForTipo('incasso_altro'),
            ],
        ]);
    }

    public function store(Request $request, ReceiptService $receiptService)
    {
        $rules = [
            'type' => 'required|in:quota,donazione,altro,capitale,prestito_sociale',
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'required|date',
            'conto_id' => 'required|exists:conti,id',
            'description' => 'nullable|string|max:255',
            'issue_receipt' => 'boolean',
            'genera_prima_nota' => 'boolean',
            'confirm_anno_precedente' => 'boolean',
            'receipt_text_override' => 'nullable|string|max:50000',
        ];
        if (in_array($request->input('type'), ['quota', 'capitale'], true)) {
            $rules['member_id'] = 'required|exists:members,id';
        } elseif ($request->input('type') === 'prestito_sociale') {
            $rules['member_id'] = 'required|exists:members,id';
            // libretto_id is stored in description or handled in service
        } else {
            $rules['member_id'] = 'nullable|exists:members,id';
            $rules['donor_name'] = 'nullable|string|max:255';
        }
        if ($request->input('type') === 'altro') {
            $schema = RendicontoCassaSchemaResolver::class();
            $rules['rendiconto_code'] = 'required|string|in:' . implode(',', $schema::getValidCodesEntrata());
            $rules['description'] = 'required|string|max:255';
        }
        $request->validate($rules);

        $paidAt = Carbon::parse($request->paid_at);
        $annoPrecedente = $paidAt->year < (int) date('Y');
        $sensibile = $annoPrecedente && ($request->boolean('genera_prima_nota', true) || $request->boolean('issue_receipt'));
        if ($sensibile && ! $request->boolean('confirm_anno_precedente')) {
            return redirect()->back()->withInput()->with('flash', [
                'type' => 'confirm_anno_precedente_required',
                'message' => 'Operazioni su anni precedenti possono alterare i rendiconti già generati. Vuoi procedere?',
            ]);
        }

        $type = $request->input('type');
        $subscriptionId = $type === 'quota' ? $request->subscription_id : null;
        $donorName = in_array($type, ['donazione', 'altro'], true) && $request->filled('donor_name')
            ? trim($request->donor_name)
            : null;

        $issueReceipt = $request->boolean('issue_receipt');
        $incasso = Incasso::create([
            'member_id' => $request->member_id ?: null,
            'donor_name' => $donorName,
            'subscription_id' => $subscriptionId,
            'amount' => $request->amount,
            'paid_at' => $request->paid_at,
            'conto_id' => $request->conto_id,
            'description' => $request->description,
            'genera_prima_nota' => $request->boolean('genera_prima_nota', true),
            'type' => $type,
            'receipt_text_override' => $issueReceipt ? $request->input('receipt_text_override') : null,
        ]);

        if ($incasso->genera_prima_nota) {
            if ($incasso->type === Incasso::TYPE_ALTRO) {
                $rendicontoCode = $request->input('rendiconto_code');
                $member = $incasso->member;
                $desc = $incasso->description;
                if ($member) {
                    $desc = $desc . ' – ' . trim($member->cognome . ' ' . $member->nome);
                } elseif ($incasso->donor_name) {
                    $desc = $desc . ' – ' . $incasso->donor_name;
                }
            } elseif ($incasso->type === Incasso::TYPE_CAPITALE) {
                $rendicontoCode = 'capitale_sociale';
                $member = $incasso->member;
                $desc = $incasso->description ?: 'Versamento quota capitale sociale';
                if ($member) {
                    $desc = $desc . ' – ' . trim($member->cognome . ' ' . $member->nome);
                }
            } elseif ($incasso->type === Incasso::TYPE_PRESTITO_SOCIALE) {
                $rendicontoCode = 'prestito_sociale_depositi';
                $member = $incasso->member;
                $desc = $incasso->description ?: 'Deposito in libretto prestito sociale';
                if ($member) {
                    $desc = $desc . ' – ' . trim($member->cognome . ' ' . $member->nome);
                }
            } else {
                $schema = RendicontoCassaSchemaResolver::class();
                $rendicontoCode = $incasso->type === Incasso::TYPE_QUOTA
                    ? $schema::CODE_QUOTA
                    : $schema::CODE_DONAZIONE;
                $member = $incasso->member;
                $desc = $incasso->description;
                if ($incasso->type === Incasso::TYPE_DONAZIONE) {
                    $desc = $desc ?: ($incasso->donor_name ? 'Erogazione liberale - ' . $incasso->donor_name : 'Erogazione liberale');
                } else {
                    $baseDesc = $desc ?: 'Quota associativa';
                    $desc = $member
                        ? $baseDesc . ' – ' . trim($member->cognome . ' ' . $member->nome)
                        : $baseDesc;
                }
            }
            PrimaNotaEntry::create([
                'conto_id' => $incasso->conto_id,
                'rendiconto_code' => $rendicontoCode,
                'entryable_type' => Incasso::class,
                'entryable_id' => $incasso->id,
                'date' => $incasso->paid_at->toDateString(),
                'amount' => abs((float) $incasso->amount),
                'description' => $desc,
                'gestione' => 'istituzionale',
                'competenza_cassa' => true,
            ]);
        }

        if ($issueReceipt && ($incasso->member_id || $incasso->donor_name)) {
            try {
                $receiptService->generateForIncasso($incasso, $request->input('receipt_text_override'));
            } catch (\Throwable $e) {
            }
        }

        $route = match ($incasso->type) {
            Incasso::TYPE_DONAZIONE => 'donazioni.index',
            Incasso::TYPE_ALTRO => 'incassi-generici.index',
            Incasso::TYPE_CAPITALE, Incasso::TYPE_PRESTITO_SOCIALE => 'quote-sociali.index',
            default => 'quote-sociali.index',
        };
        return redirect()->route($route)->with('flash', ['type' => 'success', 'message' => 'Incasso registrato.']);
    }

    public function show(Incasso $incasso)
    {
        $incasso->load(['member', 'subscription', 'conto', 'receipt', 'primaNotaEntry', 'attachments']);

        $receiptTemplateText = null;
        if (! $incasso->receipt && ($incasso->member_id || $incasso->donor_name)) {
            $tipo = match ($incasso->type) {
                Incasso::TYPE_DONAZIONE => 'incasso_donazione',
                Incasso::TYPE_ALTRO     => 'incasso_altro',
                default                 => 'incasso_quota',
            };
            $receiptTemplateText = ReceiptTemplate::getBodyForTipo($tipo);
        }

        return Inertia::render('Incassi/Show', [
            'incasso'                => $incasso,
            'uploadMaxFileSizeHuman' => self::uploadMaxFileSizeHuman(),
            'receiptTemplateText'    => $receiptTemplateText,
            'memberEmail'            => $incasso->member?->email,
        ]);
    }

    /**
     * Carica un allegato sull'incasso.
     */
    public function storeAttachment(Request $request, Incasso $incasso, AttachmentService $attachmentService)
    {
        $maxKb   = (int) floor(UploadedFile::getMaxFilesize() / 1024);
        $limitKb = $maxKb > 0 ? $maxKb : 51200;

        $request->validate([
            'file' => 'required|file|max:' . $limitKb . '|mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx',
        ], [
            'file.required' => 'Seleziona un file da caricare.',
            'file.max'      => 'Il file non deve superare ' . self::uploadMaxFileSizeHuman() . ' (limite del server).',
            'file.mimes'    => 'Formato non consentito. Usa PDF, immagini, Word o Excel.',
        ]);

        $file = $request->file('file');
        if ($file->getError() !== \UPLOAD_ERR_OK) {
            $message = match ($file->getError()) {
                \UPLOAD_ERR_INI_SIZE,
                \UPLOAD_ERR_FORM_SIZE => 'Il file è troppo grande. Prova con un file più piccolo.',
                \UPLOAD_ERR_PARTIAL  => 'Il file è stato caricato solo in parte. Riprova.',
                default              => 'Errore durante l\'upload del file. Riprova.',
            };
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => $message]);
        }

        try {
            $attachmentService->store($file, $incasso);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Upload allegato incasso fallito', [
                'incasso_id' => $incasso->id,
                'exception'  => $e->getMessage(),
            ]);
            return redirect()->back()->with('flash', ['type' => 'error', 'message' => 'Caricamento non riuscito. Riprova o contatta l\'assistenza.']);
        }

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Allegato caricato.']);
    }

    /**
     * Rimuove un allegato dall'incasso.
     */
    public function destroyAttachment(Incasso $incasso, Attachment $attachment)
    {
        if ($attachment->attachable_type !== Incasso::class || (int) $attachment->attachable_id !== (int) $incasso->id) {
            abort(404, 'Allegato non trovato su questo incasso.');
        }

        $attachment->delete();

        return redirect()->back()->with('flash', ['type' => 'success', 'message' => 'Allegato rimosso.']);
    }

    private static function uploadMaxFileSizeHuman(): string
    {
        return trim(ini_get('upload_max_filesize') ?: '2M');
    }

    public function edit(Incasso $incasso)
    {
        $incasso->load(['member', 'subscription', 'conto', 'receipt', 'primaNotaEntry']);

        $conti = Conto::attivi()->ordered()->get(['id', 'name', 'code']);

        return Inertia::render('Incassi/Edit', [
            'incasso' => $incasso,
            'conti' => $conti,
        ]);
    }

    public function update(Request $request, Incasso $incasso)
    {
        $incasso->load('receipt');

        // Blocca la modifica se la ricevuta è già stata inviata per email
        if ($incasso->receipt?->sent_at !== null) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Impossibile modificare: la ricevuta è già stata inviata per email il ' .
                    $incasso->receipt->sent_at->format('d/m/Y H:i') . '.',
            ]);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'required|date',
            'conto_id' => 'required|exists:conti,id',
            'description' => 'nullable|string|max:255',
            'donor_name' => 'nullable|string|max:255',
        ]);

        $incasso->update([
            'amount' => $validated['amount'],
            'paid_at' => $validated['paid_at'],
            'conto_id' => $validated['conto_id'],
            'description' => $validated['description'] ?? null,
            'donor_name' => in_array($incasso->type, [Incasso::TYPE_DONAZIONE, Incasso::TYPE_ALTRO])
                ? ($validated['donor_name'] ?? $incasso->donor_name)
                : $incasso->donor_name,
        ]);

        // Aggiorna la prima nota collegata, se esiste
        $primaNotaEntry = $incasso->primaNotaEntry;
        if ($primaNotaEntry) {
            $member = $incasso->member;
            if ($incasso->type === Incasso::TYPE_ALTRO) {
                $desc = $incasso->description;
                if ($member) {
                    $desc = $desc . ' – ' . trim($member->cognome . ' ' . $member->nome);
                } elseif ($incasso->donor_name) {
                    $desc = $desc . ' – ' . $incasso->donor_name;
                }
            } elseif ($incasso->type === Incasso::TYPE_DONAZIONE) {
                $desc = $incasso->description
                    ?: ($incasso->donor_name ? 'Erogazione liberale - ' . $incasso->donor_name : 'Erogazione liberale');
            } else {
                $baseDesc = $incasso->description ?: 'Quota associativa';
                $desc = $member
                    ? $baseDesc . ' – ' . trim($member->cognome . ' ' . $member->nome)
                    : $baseDesc;
            }

            $primaNotaEntry->update([
                'conto_id' => $incasso->conto_id,
                'date' => $incasso->paid_at->toDateString(),
                'amount' => abs((float) $incasso->amount),
                'description' => $desc,
            ]);
        }

        $route = $incasso->type === Incasso::TYPE_DONAZIONE ? 'donazioni.index'
            : ($incasso->type === Incasso::TYPE_ALTRO ? 'incassi-generici.index' : 'quote-sociali.index');

        return redirect()->route($route)->with('flash', ['type' => 'success', 'message' => 'Incasso aggiornato.']);
    }

    /**
     * Emette una ricevuta per un incasso esistente che non ne ha ancora una.
     */
    public function issueReceipt(Request $request, Incasso $incasso, ReceiptService $receiptService)
    {
        $incasso->load(['receipt', 'member', 'subscription', 'conto']);

        if ($incasso->receipt) {
            return back()->with('flash', [
                'type'    => 'info',
                'message' => 'Ricevuta già emessa: n° ' . $incasso->receipt->number . '.',
            ]);
        }

        if (! $incasso->member_id && ! $incasso->donor_name) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Per emettere la ricevuta è necessario un socio o un donatore.',
            ]);
        }

        $request->validate([
            'receipt_text_override' => ['nullable', 'string', 'max:50000'],
        ]);

        $overrideText = $request->filled('receipt_text_override')
            ? $request->input('receipt_text_override')
            : null;

        // Persisti il testo personalizzato sull'incasso se fornito
        if ($overrideText !== null) {
            $incasso->update(['receipt_text_override' => $overrideText]);
            $incasso->refresh();
        }

        try {
            $receipt = $receiptService->generateForIncasso($incasso, $overrideText);

            return back()->with('flash', [
                'type'    => 'success',
                'message' => 'Ricevuta n° ' . $receipt->number . ' emessa con successo.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Errore nella generazione della ricevuta: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Invia per email la ricevuta dell'incasso, restando nel contesto incasso.
     */
    public function sendReceiptEmail(Request $request, Incasso $incasso)
    {
        $incasso->load(['receipt.member']);

        if (! $incasso->receipt) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Nessuna ricevuta emessa per questo incasso.',
            ]);
        }

        $receipt = $incasso->receipt;

        if (! $receipt->file_path || ! Storage::disk('local')->exists($receipt->file_path)) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'File PDF non trovato. Rigenera prima la ricevuta.',
            ]);
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email       = $request->input('email');
        $appName     = Settings::get('nome_associazione', config('app.name'));
        $safeNumber  = str_replace(['/', '\\'], '-', $receipt->number);
        $filename    = 'ricevuta-' . $safeNumber . '.pdf';

        $rawAmount = $receipt->receivable?->amount ?? null;
        $replacements = [
            'receipt_number'    => $receipt->number,
            'receipt_issued_at' => $receipt->issued_at?->format('d/m/Y') ?? '',
            'appName'           => $appName,
            'receipt_amount'    => $rawAmount !== null
                ? number_format((float) $rawAmount, 2, ',', '.')
                : '',
            'recipient_name' => $receipt->member
                ? trim($receipt->member->cognome . ' ' . $receipt->member->nome)
                : ($receipt->recipient_name ?? ''),
            'year' => (string) now()->year,
        ];
        $rendered = EmailTemplate::render('ricevuta', $replacements);

        try {
            if ($rendered) {
                Mail::html($rendered['body'], function ($message) use ($email, $rendered, $receipt, $filename) {
                    $message->to($email)->subject($rendered['subject']);
                    $message->attach(Storage::disk('local')->path($receipt->file_path), [
                        'as'   => $filename,
                        'mime' => 'application/pdf',
                    ]);
                });
            } else {
                Mail::send('emails.receipt', ['receipt' => $receipt, 'appName' => $appName], function ($message) use ($email, $appName, $receipt, $filename) {
                    $message->to($email)->subject('[' . $appName . '] Ricevuta n. ' . $receipt->number);
                    $message->attach(Storage::disk('local')->path($receipt->file_path), [
                        'as'   => $filename,
                        'mime' => 'application/pdf',
                    ]);
                });
            }

            $receipt->update(['sent_at' => now()]);

            return back()->with('flash', [
                'type'    => 'success',
                'message' => 'Ricevuta inviata a ' . $email . '.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Errore nell\'invio: ' . $e->getMessage(),
            ]);
        }
    }

    public function destroy(Incasso $incasso)
    {
        $incasso->load(['receipt', 'primaNotaEntry']);

        // Elimina la prima nota collegata
        if ($incasso->primaNotaEntry) {
            $incasso->primaNotaEntry->delete();
        }

        // Elimina la ricevuta (file PDF + record)
        if ($incasso->receipt) {
            if ($incasso->receipt->file_path) {
                Storage::disk('local')->delete($incasso->receipt->file_path);
            }
            $incasso->receipt->delete();
        }

        $type = $incasso->type;
        $incasso->delete();

        $route = $type === Incasso::TYPE_DONAZIONE ? 'donazioni.index'
            : ($type === Incasso::TYPE_ALTRO ? 'incassi-generici.index' : 'quote-sociali.index');

        return redirect()->route($route)->with('flash', ['type' => 'success', 'message' => 'Incasso eliminato.']);
    }
}
