<?php

namespace App\Http\Controllers;

use App\Jobs\SyncMailboxJob;
use App\Models\MailAccount;
use App\Models\MailMessage;
use Illuminate\Http\Request;
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
            ],
        ]);
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
}
