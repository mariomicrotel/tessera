<?php

namespace App\Jobs;

use App\Mail\DynamicMail;
use App\Models\MailAccount;
use App\Models\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Invia un'email tramite il mailer SMTP dinamico della casella specificata.
 *
 * - Registra a runtime un mailer named 'mail_account_{id}' (config dinamica)
 * - Salva il messaggio inviato in mail_messages (folder='Sent')
 * - Pulisce i file temporanei degli allegati dopo l'invio
 */
class SendMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries   = 2;

    public function __construct(
        public readonly int     $mailAccountId,
        public readonly string  $to,
        public readonly string  $toName,
        public readonly string  $subject,
        public readonly string  $bodyHtml,
        public readonly string  $bodyText,
        public readonly ?string $replyTo      = null,
        public readonly ?string $replyToName  = null,
        public readonly array   $cc           = [],
        public readonly array   $bcc          = [],
        /** Array di ['path' => 'storage path', 'name' => 'nome originale', 'mime' => '...'] */
        public readonly array   $attachments  = [],
        // Threading: message-id a cui si risponde, catena References, thread radice
        public readonly ?string $inReplyTo    = null,
        public readonly array   $references   = [],
        public readonly ?string $threadId     = null,
    ) {}

    public function handle(): void
    {
        $account = MailAccount::withoutGlobalScope('tenant')->find($this->mailAccountId);

        if (! $account || ! $account->hasSmtp()) {
            Log::warning("[SendMail] Account #{$this->mailAccountId} non trovato o senza SMTP.");
            return;
        }

        $fromEmail = $account->smtp_from_email ?: $account->email;
        $fromName  = $account->smtp_from_name  ?: $account->name;
        $password  = $account->getDecryptedSmtpPassword();

        // Registra un mailer dinamico a runtime con le credenziali dell'account.
        // Ogni account ha il suo nome univoco per evitare conflitti di cache del manager.
        $mailerName = 'mail_account_' . $this->mailAccountId;
        config(['mail.mailers.' . $mailerName => [
            'transport'  => 'smtp',
            'host'       => $account->smtp_host,
            'port'       => $account->smtp_port ?? 587,
            'encryption' => $account->smtp_encryption === 'none' ? null : ($account->smtp_encryption ?? 'tls'),
            'username'   => $account->smtp_username,
            'password'   => $password,
            'timeout'    => 30,
        ]]);

        // Genera un Message-ID univoco per il messaggio in uscita (per il threading futuro)
        $domain     = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
        $ownMessageId = uniqid('ets-', true) . '@' . $domain;

        try {
            // ── Invio ─────────────────────────────────────────────────────────
            Mail::mailer($mailerName)
                ->to($this->to, $this->toName ?: null)
                ->cc($this->cc ?: [])
                ->bcc($this->bcc ?: [])
                ->send(new DynamicMail(
                    from:            $fromEmail,
                    fromName:        $fromName,
                    subject:         $this->subject,
                    bodyHtml:        $this->bodyHtml,
                    bodyText:        $this->bodyText,
                    replyTo:         $this->replyTo,
                    replyToName:     $this->replyToName,
                    attachmentPaths: $this->attachments,
                    messageId:       $ownMessageId,
                    inReplyTo:       $this->inReplyTo,
                    references:      $this->references,
                ));

            Log::info("[SendMail] Inviato a {$this->to} via account #{$this->mailAccountId}");

            // ── Salva in Sent ─────────────────────────────────────────────────
            $this->saveSentMessage($account, $fromEmail, $fromName, $ownMessageId);

            // ── Pulizia file temporanei ───────────────────────────────────────
            $this->cleanupTempFiles();

        } catch (\Throwable $e) {
            Log::error("[SendMail] Errore account #{$this->mailAccountId}: " . $e->getMessage());
            $this->cleanupTempFiles();
            throw $e;
        }
    }

    private function saveSentMessage(MailAccount $account, string $fromEmail, string $fromName, string $ownMessageId): void
    {
        try {
            // UID sintetico per messaggi inviati: timestamp in ms (non collide con IMAP UIDs reali)
            $uid = (int) round(microtime(true) * 1000);

            $toAddresses = [['name' => $this->toName ?: null, 'email' => $this->to]];
            $ccAddresses = array_map(fn($e) => ['name' => null, 'email' => $e], $this->cc);

            // thread_id: eredita dal thread originale (risposta) o usa il proprio message-id
            $threadId = $this->threadId ?: $ownMessageId;

            MailMessage::withoutGlobalScope('tenant')->create([
                'tenant_id'       => $account->tenant_id,
                'mail_account_id' => $account->id,
                'folder'          => 'Sent',
                'uid'             => $uid,
                'message_id'      => $ownMessageId,
                'in_reply_to'     => $this->inReplyTo,
                'thread_id'       => $threadId,
                'subject'         => $this->subject,
                'from_name'       => $fromName,
                'from_email'      => $fromEmail,
                'to_addresses'    => $toAddresses,
                'cc_addresses'    => $ccAddresses ?: null,
                'sent_at'         => now(),
                'body_html'       => $this->bodyHtml ?: null,
                'body_text'       => $this->bodyText ?: null,
                'is_read'         => true,  // messaggi inviati sempre "letti"
                'is_flagged'      => false,
                'has_attachments' => count($this->attachments) > 0,
            ]);
        } catch (\Throwable $e) {
            // Non bloccare per errore di salvataggio Sent
            Log::warning("[SendMail] Impossibile salvare in Sent: " . $e->getMessage());
        }
    }

    private function cleanupTempFiles(): void
    {
        foreach ($this->attachments as $att) {
            try {
                if (! empty($att['temp_path']) && Storage::disk('local')->exists($att['temp_path'])) {
                    Storage::disk('local')->delete($att['temp_path']);
                }
            } catch (\Throwable) {}
        }
    }
}
