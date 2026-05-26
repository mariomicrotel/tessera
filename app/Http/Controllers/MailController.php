<?php

namespace App\Http\Controllers;

use App\Jobs\SendMailJob;
use App\Jobs\SyncMailboxJob;
use App\Models\Attachment;
use App\Models\MailAccount;
use App\Models\MailMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $query = MailMessage::query()
            ->with('account:id,name,email')
            ->select([
                'id', 'mail_account_id', 'uid', 'folder',
                'subject', 'from_name', 'from_email',
                'sent_at', 'is_read', 'is_flagged', 'has_attachments',
                'body_text', 'body_html',  // per snippet
            ])
            ->orderByDesc('sent_at');

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
            'sent_at'         => $m->sent_at?->toIso8601String(),
            'is_read'         => $m->is_read,
            'is_flagged'      => $m->is_flagged,
            'has_attachments' => $m->has_attachments,
            'snippet'         => $m->snippet,
        ]);

        $totalUnread = MailMessage::query()->where('is_read', false)->count();

        return Inertia::render('Mail/Inbox', [
            'accounts'     => $accounts,
            'messages'     => $messages,
            'total_unread' => $totalUnread,
            'filters'      => [
                'account' => $accountId,
                'filter'  => $filter,
                'search'  => $search,
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

        // Allegati
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
                'account'         => $mailMessage->account?->only(['id', 'name', 'email']),
                'attachments'     => $attachments,
            ],
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
                    'account_id'   => $orig->mail_account_id,
                ];
            }
        }

        return Inertia::render('Mail/Compose', [
            'accounts' => $accounts,
            'reply_to' => $replyTo,
        ]);
    }

    /** Invia l'email (dispatch a queue) */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|integer|exists:mail_accounts,id',
            'to'         => 'required|email',
            'to_name'    => 'nullable|string|max:200',
            'cc'         => 'nullable|string',   // CSV di email
            'bcc'        => 'nullable|string',
            'subject'    => 'required|string|max:500',
            'body'       => 'required|string',
            'reply_to_message_id' => 'nullable|integer|exists:mail_messages,id',
        ]);

        // Verifica che l'account appartenga al tenant corrente
        $account = MailAccount::findOrFail($validated['account_id']);
        abort_unless($account->hasSmtp(), 422, 'Questa casella non ha SMTP configurato.');

        // Parsing CC/BCC da stringa CSV
        $cc  = array_filter(array_map('trim', explode(',', $validated['cc']  ?? '')));
        $bcc = array_filter(array_map('trim', explode(',', $validated['bcc'] ?? '')));

        // Reply-To: se è risposta, il reply-to è il mittente originale
        $replyToEmail = null;
        $replyToName  = null;
        if ($validated['reply_to_message_id'] ?? null) {
            $orig = MailMessage::find($validated['reply_to_message_id']);
            if ($orig) {
                $replyToEmail = $orig->from_email;
                $replyToName  = $orig->from_name;
            }
        }

        SendMailJob::dispatch(
            mailAccountId: $account->id,
            to:            $validated['to'],
            toName:        $validated['to_name'] ?? '',
            subject:       $validated['subject'],
            bodyHtml:      nl2br(e($validated['body'])), // testo → HTML semplice
            bodyText:      $validated['body'],
            replyTo:       $replyToEmail,
            replyToName:   $replyToName,
            cc:            array_values($cc),
            bcc:           array_values($bcc),
        );

        return redirect()->route('mail.index')
            ->with('success', 'Messaggio in coda per l\'invio.');
    }
}
