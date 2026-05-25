<?php

namespace App\Notifications;

use App\Models\ConsultantExportBundle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al consulente: un bundle di export è pronto al download.
 *
 * Canali:
 *  - mail     → email con link al dettaglio bundle
 *  - database → record per badge in-app "Hai N export pronti"
 */
class ExportBundleReadyNotification extends Notification
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
        $tenant       = $this->bundle->tenant;
        $tenantLabel  = $tenant?->name ?? 'tenant';
        $period       = $this->bundle->periodLabel();
        $size         = $this->bundle->fileSizeHuman() ?? '—';
        $downloadUrl  = route('consultant.exports.show', $this->bundle->id);
        $expiresAt    = $this->bundle->expires_at?->format('d/m/Y H:i') ?? '—';

        return (new MailMessage)
            ->subject("Bundle di export pronto — {$tenantLabel}")
            ->greeting('Salve,')
            ->line("Il bundle di export richiesto per **{$tenantLabel}** ({$period}) è pronto al download.")
            ->line("Dimensione: {$size}")
            ->line("Sarà disponibile fino al: **{$expiresAt}**")
            ->action('Scarica il bundle', $downloadUrl)
            ->line('Grazie per usare Tessera.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'export_bundle_ready',
            'bundle_id'    => $this->bundle->id,
            'tenant_id'    => $this->bundle->tenant_id,
            'tenant_name'  => $this->bundle->tenant?->name,
            'period_from'  => $this->bundle->period_from?->toDateString(),
            'period_to'    => $this->bundle->period_to?->toDateString(),
            'file_size'    => $this->bundle->file_size_bytes,
            'expires_at'   => $this->bundle->expires_at?->toIso8601String(),
        ];
    }
}
