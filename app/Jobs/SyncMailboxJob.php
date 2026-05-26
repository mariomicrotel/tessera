<?php

namespace App\Jobs;

use App\Models\MailAccount;
use App\Models\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

/**
 * Sincronizza una casella IMAP in background.
 *
 * - Scarica i messaggi dal server a partire dall'ultima sincronizzazione
 *   (o da sync_days giorni fa al primo avvio).
 * - Usa il driver "protocol" (pure PHP socket) — non richiede ext-imap.
 * - Idempotente: aggiorna i messaggi già presenti (flag is_read).
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
                'host'       => $account->imap_host,
                'port'       => $account->imap_port,
                'encryption' => $account->imap_encryption === 'none' ? false : $account->imap_encryption,
                'username'   => $account->imap_username,
                'password'   => $account->getDecryptedPassword(),
                'driver'     => 'Protocol', // pure PHP, no ext-imap
                'validate_cert' => false,
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
            throw $e; // retries
        }
    }

    private function upsertMessage(MailAccount $account, $msg): void
    {
        $uid = (int) $msg->uid;

        // Estrai mittente
        $fromCollection = $msg->getFrom();
        $fromAddr = $fromCollection->first();
        $fromEmail = $fromAddr?->mail ?? null;
        $fromName  = $fromAddr?->personal ?? null;

        // Estrai destinatari (To)
        $toAddresses = collect($msg->getTo())->map(fn($a) => [
            'name'  => $a->personal ?? null,
            'email' => $a->mail ?? null,
        ])->values()->toArray();

        // Cc
        $ccAddresses = collect($msg->getCc())->map(fn($a) => [
            'name'  => $a->personal ?? null,
            'email' => $a->mail ?? null,
        ])->values()->toArray();

        // Body
        $bodyHtml = null;
        $bodyText = null;
        try {
            $bodyHtml = $msg->getHTMLBody();
        } catch (\Throwable) {}
        try {
            $bodyText = $msg->getTextBody();
        } catch (\Throwable) {}

        // Data
        $sentAt = null;
        try {
            $sentAt = Carbon::parse($msg->getDate()->first())->toDateTimeString();
        } catch (\Throwable) {
            $sentAt = now()->toDateTimeString();
        }

        // hasAttachments
        $hasAttachments = $msg->hasAttachments();

        MailMessage::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'mail_account_id' => $account->id,
                'folder'          => $account->imap_folder,
                'uid'             => $uid,
            ],
            [
                'tenant_id'       => $account->tenant_id,
                'message_id'      => mb_substr((string)($msg->getMessageId()->first() ?? ''), 0, 500) ?: null,
                'subject'         => mb_substr((string)($msg->getSubject()->first() ?? '(nessun oggetto)'), 0, 500),
                'from_name'       => $fromName  ? mb_substr($fromName,  0, 300) : null,
                'from_email'      => $fromEmail ? mb_substr($fromEmail, 0, 300) : null,
                'to_addresses'    => $toAddresses,
                'cc_addresses'    => $ccAddresses ?: null,
                'sent_at'         => $sentAt,
                'body_html'       => $bodyHtml,
                'body_text'       => $bodyText,
                'is_read'         => (bool) $msg->getFlags()->has('Seen'),
                'is_flagged'      => (bool) $msg->getFlags()->has('Flagged'),
                'has_attachments' => $hasAttachments,
            ]
        );
    }
}
