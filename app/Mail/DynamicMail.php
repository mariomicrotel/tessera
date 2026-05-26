<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/**
 * Mailable generico per l'invio tramite account SMTP dinamico.
 * Supporta allegati tramite array di percorsi file temporanei.
 */
class DynamicMail extends Mailable
{
    public function __construct(
        private readonly string  $from,
        private readonly string  $fromName,
        private readonly string  $subject,
        private readonly string  $bodyHtml,
        private readonly string  $bodyText,
        private readonly ?string $replyTo         = null,
        private readonly ?string $replyToName      = null,
        /** Array di ['temp_path' => '...', 'original_name' => '...', 'mime_type' => '...'] */
        private readonly array   $attachmentPaths  = [],
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
        $html = $this->bodyHtml
            ?: '<html><body><pre style="font-family:sans-serif;white-space:pre-wrap">'
               . e($this->bodyText)
               . '</pre></body></html>';

        return new Content(htmlString: $html);
    }

    public function attachments(): array
    {
        $list = [];
        foreach ($this->attachmentPaths as $att) {
            $fullPath = Storage::disk('local')->path($att['temp_path'] ?? '');
            if (! empty($att['temp_path']) && file_exists($fullPath)) {
                $list[] = Attachment::fromPath($fullPath)
                    ->as($att['original_name'] ?? basename($fullPath))
                    ->withMime($att['mime_type'] ?? 'application/octet-stream');
            }
        }
        return $list;
    }
}
