<?php

namespace App\Notifications;

use App\Models\ConsultantRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica all'ente: il consulente ha creato una nuova richiesta documenti.
 */
class ConsultantRequestCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ConsultantRequest $request,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant   = $this->request->tenant;
        $priorita = ucfirst($this->request->priorita ?? 'normale');
        $scadenza = $this->request->data_scadenza?->format('d/m/Y') ?? 'nessuna';

        return (new MailMessage)
            ->subject("Nuova richiesta dal consulente — {$this->request->titolo}")
            ->greeting('Salve,')
            ->line("Il consulente ha aperto una nuova richiesta documenti per **{$tenant?->name}**.")
            ->line("**Titolo**: {$this->request->titolo}")
            ->line("**Priorità**: {$priorita}")
            ->line("**Scadenza**: {$scadenza}")
            ->lineIf(! empty($this->request->descrizione), "Descrizione: {$this->request->descrizione}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'consultant_request_created',
            'request_id'   => $this->request->id,
            'tenant_id'    => $this->request->tenant_id,
            'titolo'       => $this->request->titolo,
            'priorita'     => $this->request->priorita,
            'data_scadenza' => $this->request->data_scadenza?->toDateString(),
        ];
    }
}
