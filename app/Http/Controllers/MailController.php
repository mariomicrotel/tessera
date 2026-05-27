<?php

namespace App\Http\Controllers;

use App\Jobs\SendMailJob;
use App\Jobs\SyncMailboxJob;
use App\Models\Attachment;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\Protocollo;
use App\Services\ProtocolloService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MailController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    // ── Inbox ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $accounts = MailAccount::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'email', 'last_synced_at'])
            ->get()
            ->map(fn($a) => array_merge($a->toArray(), [
                'unread_count' => $a->unreadCount(),
            ]));

        $accountId = $request->input('account');
        $filter    = $request->input('filter', 'all'); // all | unread | flagged
        $search    = $request->input('search');
        $box       = $request->input('box', 'received'); // received | sent | drafts

        $query = MailMessage::query()
            ->with('account:id,name,email')
            ->select([
                'id', 'mail_account_id', 'uid', 'folder',
                'subject', 'from_name', 'from_email', 'to_addresses',
                'sent_at', 'is_read', 'is_flagged', 'has_attachments',
                'body_text', 'body_html',  // per snippet
            ])
            ->orderByDesc('sent_at');

        // Filtro cartella: ricevuti (tutto tranne Sent/Drafts) | inviati | bozze
        if ($box === 'sent') {
            $query->where('folder', 'Sent');
        } elseif ($box === 'drafts') {
            $query->where('folder', 'Drafts');
        } else {
            $query->whereNotIn('folder', ['Sent', 'Drafts']);
        }

        if ($accountId) {
            $query->where('mail_account_id', $accountId);
        }

        if ($filter === 'unread') {
            $query->where('is_read', false);
        } elseif ($filter === 'flagged') {
            $query->where('is_flagged', true);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('from_name', 'like', "%{$search}%")
                  ->orWhere('from_email', 'like', "%{$search}%")
                  ->orWhere('body_text', 'like', "%{$search}%");
            });
        }

        $messages = $query->paginate(30)->through(fn($m) => [
            'id'              => $m->id,
            'account'         => ['id' => $m->mail_account_id, 'name' => $m->account?->name],
            'subject'         => $m->subject ?? '(nessun oggetto)',
            'from_name'       => $m->from_name,
            'from_email'      => $m->from_email,
            // Per le viste "inviati"/"bozze" mostriamo il destinatario invece del mittente
            'to_label'        => collect($m->to_addresses ?? [])
                ->map(fn($a) => $a['name'] ?? $a['email'] ?? null)
                ->filter()->implode(', ') ?: null,
            'is_sent'         => $m->folder === 'Sent',
            'is_draft'        => $m->folder === 'Drafts',
            'sent_at'         => $m->sent_at?->toIso8601String(),
            'is_read'         => $m->is_read,
            'is_flagged'      => $m->is_flagged,
            'has_attachments' => $m->has_attachments,
            'snippet'         => $m->snippet,
        ]);

        // Conteggi non-letti: solo posta ricevuta (Sent/Drafts sono sempre letti)
        $totalUnread = MailMessage::query()
            ->whereNotIn('folder', ['Sent', 'Drafts'])
            ->where('is_read', false)
            ->count();

        // Conteggi per i badge delle cartelle
        $sentCount  = MailMessage::query()->where('folder', 'Sent')->count();
        $draftCount = MailMessage::query()->where('folder', 'Drafts')->count();

        return Inertia::render('Mail/Inbox', [
            'accounts'     => $accounts,
            'messages'     => $messages,
            'total_unread' => $totalUnread,
            'sent_count'   => $sentCount,
            'draft_count'  => $draftCount,
            'filters'      => [
                'account' => $accountId,
                'filter'  => $filter,
                'search'  => $search,
                'box'     => $box,
            ],
            'has_accounts' => $accounts->isNotEmpty(),
        ]);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(MailMessage $mailMessage)
    {
        // Marca come letto
        if (! $mailMessage->is_read) {
            $mailMessage->update(['is_read' => true]);
        }

        // Allegati ricevuti (da IMAP)
        $attachments = Attachment::withoutGlobalScope('tenant')
            ->where('attachable_type', MailMessage::class)
            ->where('attachable_id', $mailMessage->id)
            ->get()
            ->map(fn($a) => [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'mime_type'     => $a->mime_type,
                'size'          => $a->size,
                'download_url'  => route('mail.attachment', [$mailMessage->id, $a->id]),
            ]);

        // Prev / Next (stesso account e folder, ordinati per sent_at)
        $prevId = MailMessage::query()
            ->where('mail_account_id', $mailMessage->mail_account_id)
            ->where('folder', $mailMessage->folder)
            ->where('sent_at', '>', $mailMessage->sent_at)
            ->orderBy('sent_at')
            ->value('id');

        $nextId = MailMessage::query()
            ->where('mail_account_id', $mailMessage->mail_account_id)
            ->where('folder', $mailMessage->folder)
            ->where('sent_at', '<', $mailMessage->sent_at)
            ->orderByDesc('sent_at')
            ->value('id');

        // Protocollo collegato (se già protocollato)
        $protocollo = Protocollo::query()
            ->where('linked_type', MailMessage::class)
            ->where('linked_id', $mailMessage->id)
            ->first();

        // ── Conversazione (thread) ────────────────────────────────────────────
        // Tutti i messaggi con lo stesso thread_id, ordinati cronologicamente.
        $threadMessages = collect();
        if ($mailMessage->thread_id) {
            $threadMessages = MailMessage::query()
                ->with('account:id,name,email')
                ->where('thread_id', $mailMessage->thread_id)
                ->orderBy('sent_at')
                ->get();
        }
        // Fallback: se per qualche motivo il thread è vuoto, includi almeno questo messaggio
        if ($threadMessages->isEmpty()) {
            $threadMessages = collect([$mailMessage]);
        }

        $thread = $threadMessages->map(fn($m) => [
            'id'              => $m->id,
            'subject'         => $m->subject ?? '(nessun oggetto)',
            'from_name'       => $m->from_name,
            'from_email'      => $m->from_email,
            'from'            => $m->from,
            'to_addresses'    => $m->to_addresses,
            'cc_addresses'    => $m->cc_addresses,
            'sent_at'         => $m->sent_at?->toIso8601String(),
            'is_flagged'      => $m->is_flagged,
            'has_attachments' => $m->has_attachments,
            'body_html'       => $m->body_html,
            'body_text'       => $m->body_text,
            'folder'          => $m->folder,
            'is_sent'         => $m->folder === 'Sent',
            'account'         => $m->account?->only(['id', 'name', 'email']),
            // Allegati per ciascun messaggio del thread
            'attachments'     => Attachment::withoutGlobalScope('tenant')
                ->where('attachable_type', MailMessage::class)
                ->where('attachable_id', $m->id)
                ->get()
                ->map(fn($a) => [
                    'id'            => $a->id,
                    'original_name' => $a->original_name,
                    'mime_type'     => $a->mime_type,
                    'size'          => $a->size,
                    'download_url'  => route('mail.attachment', [$m->id, $a->id]),
                ])->values(),
        ])->values();

        return Inertia::render('Mail/Show', [
            'message' => [
                'id'              => $mailMessage->id,
                'subject'         => $mailMessage->subject ?? '(nessun oggetto)',
                'from_name'       => $mailMessage->from_name,
                'from_email'      => $mailMessage->from_email,
                'from'            => $mailMessage->from,
                'to_addresses'    => $mailMessage->to_addresses,
                'cc_addresses'    => $mailMessage->cc_addresses,
                'sent_at'         => $mailMessage->sent_at?->toIso8601String(),
                'is_read'         => $mailMessage->is_read,
                'is_flagged'      => $mailMessage->is_flagged,
                'has_attachments' => $mailMessage->has_attachments,
                'body_html'       => $mailMessage->body_html,
                'body_text'       => $mailMessage->body_text,
                'folder'          => $mailMessage->folder,
                'account'         => $mailMessage->account?->only(['id', 'name', 'email']),
                'attachments'     => $attachments,
            ],
            'thread'     => $thread,
            'prev_id'    => $prevId,
            'next_id'    => $nextId,
            'protocollo' => $protocollo ? [
                'id'                => $protocollo->id,
                'numero_formattato' => $protocollo->numero_formattato,
            ] : null,
        ]);
    }

    /** Download di un allegato (verifica che appartenga al messaggio del tenant) */
    public function downloadAttachment(MailMessage $mailMessage, Attachment $attachment)
    {
        abort_if(
            $attachment->attachable_type !== MailMessage::class
            || $attachment->attachable_id !== $mailMessage->id,
            404
        );

        if (! Storage::disk($attachment->disk)->exists($attachment->file_path)) {
            abort(404, 'File non trovato.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->file_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    // ── Azioni ────────────────────────────────────────────────────────────────

    public function markRead(MailMessage $mailMessage)
    {
        $mailMessage->update(['is_read' => true]);
        return back();
    }

    public function markUnread(MailMessage $mailMessage)
    {
        $mailMessage->update(['is_read' => false]);
        return back();
    }

    public function toggleFlag(MailMessage $mailMessage)
    {
        $mailMessage->update(['is_flagged' => ! $mailMessage->is_flagged]);
        return back();
    }

    /** Avvia sync manuale per una casella (o tutte se nessuna specificata) */
    public function sync(Request $request)
    {
        $accountId = $request->input('account_id');

        $query = MailAccount::query()->where('is_active', true);
        if ($accountId) {
            $query->where('id', $accountId);
        }

        $dispatched = 0;
        $query->each(function (MailAccount $account) use (&$dispatched) {
            SyncMailboxJob::dispatch($account->id);
            $dispatched++;
        });

        return back()->with('success', "Sync avviato per {$dispatched} casell" . ($dispatched === 1 ? 'a' : 'e') . '.');
    }

    /** Elimina localmente (soft delete) */
    public function destroy(MailMessage $mailMessage)
    {
        $mailMessage->delete();
        return redirect()->route('mail.index');
    }

    // ── Compose / Reply / Send ────────────────────────────────────────────────

    /** Converte l'HTML del corpo in testo semplice (versione text/plain dell'email) */
    private function htmlToText(string $html): string
    {
        // Converte i blocchi e i <br> in a-capo, poi rimuove i tag residui
        $text = preg_replace('/<\s*(br)\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/\s*(p|div|h[1-6]|li|blockquote|tr)\s*>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Comprime gli a-capo multipli
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    /** Mostra form di composizione (nuovo o risposta) */
    public function compose(Request $request)
    {
        $accounts = MailAccount::query()
            ->where('is_active', true)
            ->whereNotNull('smtp_host')
            ->whereNotNull('smtp_username')
            ->select(['id', 'name', 'email', 'smtp_from_name', 'smtp_from_email'])
            ->get()
            ->map(fn ($a) => [
                'id'    => $a->id,
                'label' => $a->name . ' <' . ($a->smtp_from_email ?: $a->email) . '>',
            ]);

        // Se è una risposta, pre-compila i campi
        $replyTo = null;
        if ($replyMessageId = $request->input('reply_to')) {
            $orig = MailMessage::find($replyMessageId);
            if ($orig) {
                $replyTo = [
                    'id'           => $orig->id,
                    'subject'      => $orig->subject,
                    'from_email'   => $orig->from_email,
                    'from_name'    => $orig->from_name,
                    'sent_at'      => $orig->sent_at?->toIso8601String(),
                    'body_text'    => $orig->body_text,
                    'body_html'    => $orig->body_html,
                    'account_id'   => $orig->mail_account_id,
                ];
            }
        }

        // Se si modifica una bozza, pre-compila il form dalla bozza
        $draft = null;
        if ($draftId = $request->input('draft')) {
            $d = MailMessage::where('folder', 'Drafts')->find($draftId);
            if ($d) {
                $toFirst = collect($d->to_addresses ?? [])->first();
                $draft = [
                    'id'         => $d->id,
                    'account_id' => $d->mail_account_id,
                    'to'         => $toFirst['email'] ?? '',
                    'to_name'    => $toFirst['name'] ?? '',
                    'cc'         => collect($d->cc_addresses ?? [])->pluck('email')->implode(', '),
                    'subject'    => $d->subject,
                    'body'       => $d->body_html ?: $d->body_text,
                ];
            }
        }

        return Inertia::render('Mail/Compose', [
            'accounts' => $accounts,
            'reply_to' => $replyTo,
            'draft'    => $draft,
        ]);
    }

    /** Salva (o aggiorna) una bozza */
    public function saveDraft(Request $request)
    {
        $validated = $request->validate([
            'draft_id'   => 'nullable|integer|exists:mail_messages,id',
            'account_id' => 'required|integer|exists:mail_accounts,id',
            'to'         => 'nullable|email',
            'to_name'    => 'nullable|string|max:200',
            'cc'         => 'nullable|string',
            'subject'    => 'nullable|string|max:500',
            'body'       => 'nullable|string',
        ]);

        $account = MailAccount::findOrFail($validated['account_id']);

        $toAddresses = ! empty($validated['to'])
            ? [['name' => $validated['to_name'] ?? null, 'email' => $validated['to']]]
            : [];
        $ccAddresses = collect(array_filter(array_map('trim', explode(',', $validated['cc'] ?? ''))))
            ->map(fn($e) => ['name' => null, 'email' => $e])->values()->all();

        $attrs = [
            'tenant_id'       => $account->tenant_id,
            'mail_account_id' => $account->id,
            'folder'          => 'Drafts',
            'subject'         => $validated['subject'] ?: '(bozza senza oggetto)',
            'from_name'       => $account->smtp_from_name ?: $account->name,
            'from_email'      => $account->smtp_from_email ?: $account->email,
            'to_addresses'    => $toAddresses,
            'cc_addresses'    => $ccAddresses ?: null,
            'sent_at'         => now(),
            'body_html'       => $validated['body'],
            'body_text'       => $this->htmlToText($validated['body'] ?? ''),
            'is_read'         => true,
            'is_flagged'      => false,
            'has_attachments' => false,
        ];

        if (! empty($validated['draft_id'])) {
            $draft = MailMessage::where('folder', 'Drafts')->findOrFail($validated['draft_id']);
            $draft->update($attrs);
        } else {
            $attrs['uid'] = (int) round(microtime(true) * 1000);
            MailMessage::create($attrs);
        }

        return redirect()->route('mail.index', ['box' => 'drafts'])
            ->with('success', 'Bozza salvata.');
    }

    /** Invia l'email (dispatch a queue) */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'account_id'          => 'required|integer|exists:mail_accounts,id',
            'to'                  => 'required|email',
            'to_name'             => 'nullable|string|max:200',
            'cc'                  => 'nullable|string',
            'bcc'                 => 'nullable|string',
            'subject'             => 'required|string|max:500',
            'body'                => 'required|string',
            'reply_to_message_id' => 'nullable|integer|exists:mail_messages,id',
            'draft_id'            => 'nullable|integer|exists:mail_messages,id',
            'attachments'         => 'nullable|array',
            'attachments.*'       => 'file|max:10240', // max 10 MB per file
        ]);

        $account = MailAccount::findOrFail($validated['account_id']);
        abort_unless($account->hasSmtp(), 422, 'Questa casella non ha SMTP configurato.');

        // Parsing CC/BCC da stringa CSV
        $cc  = array_filter(array_map('trim', explode(',', $validated['cc']  ?? '')));
        $bcc = array_filter(array_map('trim', explode(',', $validated['bcc'] ?? '')));

        // Reply-To header + threading: mittente e catena della conversazione
        $replyToEmail = null;
        $replyToName  = null;
        $inReplyTo    = null;
        $references   = [];
        $threadId     = null;
        if ($validated['reply_to_message_id'] ?? null) {
            $orig = MailMessage::find($validated['reply_to_message_id']);
            if ($orig) {
                $replyToEmail = $orig->from_email;
                $replyToName  = $orig->from_name;
                $inReplyTo    = $orig->message_id;
                $threadId     = $orig->thread_id;
                // References: radice del thread + message-id del messaggio a cui si risponde
                $references   = array_values(array_unique(array_filter([
                    $orig->thread_id,
                    $orig->message_id,
                ])));
            }
        }

        // Salva allegati in area temporanea (accessibile al job in coda)
        $attachmentMeta = [];
        foreach ($request->file('attachments', []) as $file) {
            $tempPath = 'mail-temp/' . Str::uuid() . '/' . $file->getClientOriginalName();
            Storage::disk('local')->put($tempPath, file_get_contents($file->getRealPath()));
            $attachmentMeta[] = [
                'temp_path'     => $tempPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType() ?? 'application/octet-stream',
            ];
        }

        SendMailJob::dispatch(
            mailAccountId: $account->id,
            to:            $validated['to'],
            toName:        $validated['to_name'] ?? '',
            subject:       $validated['subject'],
            bodyHtml:      $validated['body'], // già HTML dall'editor rich text
            bodyText:      $this->htmlToText($validated['body']),
            replyTo:       $replyToEmail,
            replyToName:   $replyToName,
            cc:            array_values($cc),
            bcc:           array_values($bcc),
            attachments:   $attachmentMeta,
            inReplyTo:     $inReplyTo,
            references:    $references,
            threadId:      $threadId,
        );

        // Se l'invio proviene da una bozza, eliminala
        if ($validated['draft_id'] ?? null) {
            MailMessage::where('folder', 'Drafts')->find($validated['draft_id'])?->forceDelete();
        }

        return redirect()->route('mail.index')
            ->with('success', 'Messaggio in coda per l\'invio.');
    }

    /** Protocolla un messaggio ricevuto (crea record Protocollo collegato) */
    public function protocolla(MailMessage $mailMessage, ProtocolloService $service)
    {
        // Evita duplicati
        $existing = Protocollo::query()
            ->where('linked_type', MailMessage::class)
            ->where('linked_id', $mailMessage->id)
            ->first();

        if ($existing) {
            return redirect()->route('protocolli.show', $existing->id)
                ->with('info', 'Messaggio già protocollato come ' . $existing->numero_formattato . '.');
        }

        $tenant = app('current_tenant');

        $tipo = $mailMessage->folder === 'Sent' ? Protocollo::TIPO_USCITA : Protocollo::TIPO_ENTRATA;

        $mittente = $tipo === Protocollo::TIPO_ENTRATA
            ? ($mailMessage->from_name ? $mailMessage->from_name . ' <' . $mailMessage->from_email . '>' : $mailMessage->from_email)
            : null;

        $destinatario = $tipo === Protocollo::TIPO_USCITA
            ? collect($mailMessage->to_addresses ?? [])
                ->map(fn($a) => $a['name'] ? $a['name'] . ' <' . $a['email'] . '>' : $a['email'])
                ->implode(', ')
            : null;

        $protocollo = $service->crea([
            'tenant_id'          => $tenant->id,
            'tipo'               => $tipo,
            'data_registrazione' => ($mailMessage->sent_at ?? now())->toDateString(),
            'oggetto'            => $mailMessage->subject ?? '(nessun oggetto)',
            'mittente'           => $mittente,
            'destinatario'       => $destinatario,
            'note'               => 'Protocollato automaticamente dalla posta in arrivo.',
            'linked_type'        => MailMessage::class,
            'linked_id'          => $mailMessage->id,
            'created_by'         => Auth::id(),
        ]);

        return redirect()->route('protocolli.show', $protocollo->id)
            ->with('success', 'Messaggio protocollato: ' . $protocollo->numero_formattato . '.');
    }
}
