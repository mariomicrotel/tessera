<?php

namespace App\Notifications;

use App\Models\ConsultantDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica all'ente: il consulente ha consegnato un nuovo documento.
 */
class ConsultantDeliveryCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ConsultantDelivery $delivery,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant   = $this->delivery->tenant;
        $numFiles = $this->delivery->documenti()->count();

        return (new MailMessage)
            ->subject("Nuova consegna dal consulente — {$this->delivery->titolo}")
            ->greeting('Salve,')
            ->line("Il consulente ha consegnato un nuovo documento per **{$tenant?->name}**.")
            ->line("**Titolo**: {$this->delivery->titolo}")
            ->line("**Tipo**: {$this->delivery->tipoLabel()}")
            ->line("**Allegati**: {$numFiles}")
            ->lineIf(! empty($this->delivery->descrizione), "Descrizione: {$this->delivery->descrizione}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'consultant_delivery_created',
            'delivery_id' => $this->delivery->id,
            'tenant_id'   => $this->delivery->tenant_id,
            'titolo'      => $this->delivery->titolo,
            'tipo'        => $this->delivery->tipo,
        ];
    }
}
