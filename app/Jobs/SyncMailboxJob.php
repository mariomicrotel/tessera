<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Models\MailAccount;
use App\Models\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Webklex\IMAP\Facades\Client;

/**
 * Sincronizza una casella IMAP in background.
 *
 * - Scarica i messaggi dal server a partire dall'ultima sincronizzazione
 *   (o da sync_days giorni fa al primo avvio).
 * - Usa protocol=imap (ImapProtocol — pure PHP socket, no ext-imap).
 * - Idempotente: aggiorna flags is_read/is_flagged dei messaggi esistenti.
 * - Salva gli allegati su disco (Storage::disk('local')).
 */
class SyncMailboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minuti max
    public int $tries   = 2;

    public function __construct(
        public readonly int $mailAccountId
    ) {}

    public function handle(): void
    {
        $account = MailAccount::withoutGlobalScope('tenant')
            ->find($this->mailAccountId);

        if (! $account || ! $account->is_active) {
            return;
        }

        // Imposta il tenant corrente per BelongsToTenant
        $tenant = \App\Models\Tenant::find($account->tenant_id);
        if (! $tenant) {
            return;
        }
        app()->instance('current_tenant', $tenant);

        Log::info("[MailSync] Avvio sync account #{$account->id} ({$account->email})");

        try {
            $client = Client::make([
                'host'          => $account->imap_host,
                'port'          => $account->imap_port,
                'protocol'      => 'imap',                  // ImapProtocol (pure PHP socket)
                'encryption'    => $account->imap_encryption === 'none' ? false : $account->imap_encryption,
                'validate_cert' => false,
                'username'      => $account->imap_username,
                'password'      => $account->getDecryptedPassword(),
            ]);

            $client->connect();

            $folder = $client->getFolder($account->imap_folder);

            // Data di partenza: ultima sync o N giorni fa
            $since = $account->last_synced_at
                ? $account->last_synced_at->subHours(1) // 1h overlap per sicurezza
                : now()->subDays($account->sync_days);

            $messages = $folder->messages()
                ->since($since)
                ->leaveUnread()   // NON marcare come letto sul server
                ->get();

            $synced  = 0;
            $skipped = 0;

            foreach ($messages as $message) {
                try {
                    $this->upsertMessage($account, $message);
                    $synced++;
                } catch (\Throwable $e) {
                    $skipped++;
                    Log::warning("[MailSync] Errore messaggio uid={$message->uid}: " . $e->getMessage());
                }
            }

            $account->last_synced_at = now();
            $account->saveQuietly();

            $client->disconnect();

            Log::info("[MailSync] Completato account #{$account->id}: {$synced} messaggi, {$skipped} saltati");

        } catch (\Throwable $e) {
            Log::error("[MailSync] Errore account #{$account->id}: " . $e->getMessage());
            throw $e; // triggers retry
        }
    }

    private function upsertMessage(MailAccount $account, $msg): void
    {
        $uid = (int) $msg->uid;

        // ── Mittente ─────────────────────────────────────────────────────────
        $fromAddr  = $msg->getFrom()->first();
        $fromEmail = $fromAddr?->mail ?? null;
        $fromName  = $fromAddr?->personal ?? null;

        // ── To / Cc ──────────────────────────────────────────────────────────
        $toAddresses = collect($msg->getTo())->map(fn($a) => [
            'name'  => $a->personal ?? null,
            'email' => $a->mail ?? null,
        ])->values()->toArray();

        $ccAddresses = collect($msg->getCc())->map(fn($a) => [
            'name'  => $a->personal ?? null,
            'email' => $a->mail ?? null,
        ])->values()->toArray();

        // ── Body ─────────────────────────────────────────────────────────────
        $bodyHtml = null;
        $bodyText = null;
        try { $bodyHtml = $msg->getHTMLBody(); } catch (\Throwable) {}
        try { $bodyText = $msg->getTextBody(); } catch (\Throwable) {}

        // ── Data ─────────────────────────────────────────────────────────────
        $sentAt = now()->toDateTimeString();
        try { $sentAt = Carbon::parse($msg->getDate()->first())->toDateTimeString(); } catch (\Throwable) {}

        // ── Flags ────────────────────────────────────────────────────────────
        $flags        = $msg->getFlags();
        $isRead       = (bool) $flags->get('Seen');
        $isFlagged    = (bool) $flags->get('Flagged');
        $hasAttachments = $msg->hasAttachments();

        // ── Threading (In-Reply-To / References) ──────────────────────────────
        $messageId = mb_substr((string) ($msg->getMessageId()->first() ?? ''), 0, 500) ?: null;

        $inReplyTo = null;
        try {
            $inReplyTo = mb_substr(trim(str_replace(['<', '>'], '', (string) ($msg->getInReplyTo()->first() ?? ''))), 0, 500) ?: null;
        } catch (\Throwable) {}

        // References: lista di message-id, il primo è la radice della conversazione
        $references = [];
        try {
            foreach ($msg->getReferences()->all() as $ref) {
                $ref = trim(str_replace(['<', '>'], '', (string) $ref));
                if ($ref !== '') {
                    $references[] = $ref;
                }
            }
        } catch (\Throwable) {}

        // thread_id = radice delle References (robusto, indipendente dall'ordine di sync),
        // altrimenti l'In-Reply-To, altrimenti il proprio message-id (è una radice)
        $threadId = $references[0] ?? $inReplyTo ?? $messageId ?? null;

        // ── Upsert messaggio ─────────────────────────────────────────────────
        $record = MailMessage::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'mail_account_id' => $account->id,
                'folder'          => $account->imap_folder,
                'uid'             => $uid,
            ],
            [
                'tenant_id'       => $account->tenant_id,
                'message_id'      => $messageId,
                'in_reply_to'     => $inReplyTo,
                'thread_id'       => $threadId ?: ('local-uid-' . $uid),
                'subject'         => mb_substr((string) ($msg->getSubject()->first() ?? '(nessun oggetto)'), 0, 500),
                'from_name'       => $fromName  ? mb_substr($fromName,  0, 300) : null,
                'from_email'      => $fromEmail ? mb_substr($fromEmail, 0, 300) : null,
                'to_addresses'    => $toAddresses,
                'cc_addresses'    => $ccAddresses ?: null,
                'sent_at'         => $sentAt,
                'body_html'       => $bodyHtml,
                'body_text'       => $bodyText,
                'is_read'         => $isRead,
                'is_flagged'      => $isFlagged,
                'has_attachments' => $hasAttachments,
            ]
        );

        // ── Allegati (solo al primo insert) ──────────────────────────────────
        if ($record->wasRecentlyCreated && $hasAttachments) {
            $this->saveAttachments($account, $record, $msg);
        }
    }

    private function saveAttachments(MailAccount $account, MailMessage $record, $msg): void
    {
        $msg->getAttachments()->each(function ($att) use ($account, $record) {
            try {
                $originalName = $att->name ?? $att->filename ?? 'allegato';
                $mimeType     = $att->getMimeType() ?? 'application/octet-stream';
                $content      = $att->content;
                $size         = is_string($content) ? strlen($content) : 0;

                if (! $content || $size === 0) {
                    return;
                }

                // Percorso: mail-attachments/{tenant_id}/{message_id}/{filename}
                $safeName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $originalName);
                $path     = "mail-attachments/{$account->tenant_id}/{$record->id}/{$safeName}";

                Storage::disk('local')->put($path, $content);

                Attachment::withoutGlobalScope('tenant')->create([
                    'tenant_id'      => $account->tenant_id,
                    'attachable_type' => MailMessage::class,
                    'attachable_id'  => $record->id,
                    'tag'            => 'mail-attachment',
                    'file_path'      => $path,
                    'original_name'  => $originalName,
                    'mime_type'      => $mimeType,
                    'size'           => $size,
                    'disk'           => 'local',
                ]);
            } catch (\Throwable $e) {
                Log::warning("[MailSync] Allegato non salvato per msg#{$record->id}: " . $e->getMessage());
            }
        });
    }
}
