<?php

namespace App\Jobs;

use App\Mail\DynamicMail;
use App\Models\MailAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Invia un'email tramite il mailer SMTP dinamico della casella specificata.
 *
 * Crea a runtime un mailer named 'mail_account_{id}' con le credenziali
 * dell'account, poi usa Mail::mailer() per inviare.
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
        public readonly ?string $replyTo     = null,
        public readonly ?string $replyToName = null,
        public readonly array   $cc          = [],
        public readonly array   $bcc         = [],
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

        // Registra un mailer dinamico a runtime con le credenziali dell'account
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

        try {
            $mailer = Mail::mailer($mailerName);

            if ($this->cc) {
                $mailer = $mailer->cc($this->cc);
            }
            if ($this->bcc) {
                $mailer = $mailer->bcc($this->bcc);
            }

            $mailer->to($this->to, $this->toName ?: null)
                   ->send(new DynamicMail(
                       from:        $fromEmail,
                       fromName:    $fromName,
                       subject:     $this->subject,
                       bodyHtml:    $this->bodyHtml,
                       bodyText:    $this->bodyText,
                       replyTo:     $this->replyTo,
                       replyToName: $this->replyToName,
                   ));

            Log::info("[SendMail] Inviato a {$this->to} via account #{$this->mailAccountId}");

        } catch (\Throwable $e) {
            Log::error("[SendMail] Errore account #{$this->mailAccountId}: " . $e->getMessage());
            throw $e;
        }
    }
}
