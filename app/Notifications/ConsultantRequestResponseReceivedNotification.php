<?php

namespace App\Notifications;

use App\Models\ConsultantRequest;
use App\Models\ConsultantRequestDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al consulente: l'ente ha caricato un file in risposta a una sua
 * richiesta documenti.
 */
class ConsultantRequestResponseReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ConsultantRequest $request,
        public readonly ConsultantRequestDocument $document,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->request->tenant;

        return (new MailMessage)
            ->subject("Risposta ricevuta — {$this->request->titolo}")
            ->greeting('Salve,')
            ->line("**{$tenant?->name}** ha caricato un nuovo documento in risposta alla sua richiesta:")
            ->line("**Richiesta**: {$this->request->titolo}")
            ->line("**File**: {$this->document->filename_originale} ({$this->document->sizeHuman()})")
            ->lineIf(! empty($this->document->note), "Nota dell'ente: {$this->document->note}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'consultant_request_response_received',
            'request_id'     => $this->request->id,
            'document_id'    => $this->document->id,
            'tenant_id'      => $this->request->tenant_id,
            'titolo'         => $this->request->titolo,
            'filename'       => $this->document->filename_originale,
        ];
    }
}
