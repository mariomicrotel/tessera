<?php

namespace App\Notifications;

use App\Models\ConsultantDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al consulente: l'ente ha risposto a una sua consegna
 * (accettato o contestato).
 */
class ConsultantDeliveryFeedbackNotification extends Notification
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
        $isContestato = $this->delivery->isContestato();

        $mail = (new MailMessage)
            ->subject(($isContestato ? '[Contestato] ' : '[Accettato] ') . $this->delivery->titolo)
            ->greeting('Salve,')
            ->line("**{$tenant?->name}** ha risposto alla consegna **{$this->delivery->titolo}**.")
            ->line("**Esito**: {$this->delivery->statoLabel()}");

        if ($this->delivery->feedback_note) {
            $mail->line("**Note**: {$this->delivery->feedback_note}");
        }

        if ($isContestato) {
            $mail->error();
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'consultant_delivery_feedback',
            'delivery_id' => $this->delivery->id,
            'tenant_id'   => $this->delivery->tenant_id,
            'titolo'      => $this->delivery->titolo,
            'stato'       => $this->delivery->stato,
            'feedback_note' => $this->delivery->feedback_note,
        ];
    }
}
