<?php

namespace App\Notifications;

use App\Models\ConsultantExportBundle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExportBundleFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ConsultantExportBundle $bundle,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantLabel = $this->bundle->tenant?->name ?? 'tenant';
        $period      = $this->bundle->periodLabel();
        $retryUrl    = route('consultant.exports.create', ['tenant' => $this->bundle->tenant?->slug]);

        return (new MailMessage)
            ->error()
            ->subject("Generazione bundle fallita — {$tenantLabel}")
            ->greeting('Salve,')
            ->line("La generazione del bundle di export per **{$tenantLabel}** ({$period}) non è andata a buon fine.")
            ->lineIf((bool) $this->bundle->error_message, "Dettaglio errore: {$this->bundle->error_message}")
            ->line('Puoi riprovare la richiesta. Se il problema persiste, contatta il supporto.')
            ->action('Riprova', $retryUrl);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'export_bundle_failed',
            'bundle_id'     => $this->bundle->id,
            'tenant_id'     => $this->bundle->tenant_id,
            'tenant_name'   => $this->bundle->tenant?->name,
            'error_message' => $this->bundle->error_message,
        ];
    }
}
