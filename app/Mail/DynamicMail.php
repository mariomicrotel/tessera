<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mailable generico per l'invio tramite account SMTP dinamico.
 * Usato da SendMailJob.
 */
class DynamicMail extends Mailable
{
    public function __construct(
        private readonly string  $from,
        private readonly string  $fromName,
        private readonly string  $subject,
        private readonly string  $bodyHtml,
        private readonly string  $bodyText,
        private readonly ?string $replyTo     = null,
        private readonly ?string $replyToName = null,
    ) {}

    public function envelope(): Envelope
    {
        $replyToAddresses = $this->replyTo
            ? [new Address($this->replyTo, $this->replyToName ?? '')]
            : [];

        return new Envelope(
            from:    new Address($this->from, $this->fromName),
            subject: $this->subject,
            replyTo: $replyToAddresses,
        );
    }

    public function content(): Content
    {
        // Se c'è body HTML lo usiamo; altrimenti convertiamo il testo plain in HTML semplice
        $html = $this->bodyHtml
            ?: '<html><body><pre style="font-family:sans-serif;white-space:pre-wrap">'
               . e($this->bodyText)
               . '</pre></body></html>';

        return new Content(htmlString: $html);
    }
}
