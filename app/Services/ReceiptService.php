<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\ExpenseRefund;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\Receipt;
use App\Models\ReceiptTemplate;
use App\Models\Settings;
use Illuminate\Support\Facades\Storage;

/**
 * Genera ricevute (PDF) per incassi (quote/donazioni) e rimborsi.
 */
class ReceiptService
{
    /**
     * Genera ricevuta per un incasso. Crea record Receipt e salva PDF su storage.
     */
    public function generateForIncasso(Incasso $incasso, ?string $receiptTextOverride = null): Receipt
    {
        if ($incasso->receipt) {
            return $incasso->receipt;
        }

        $member = $incasso->member;
        $recipientName = $incasso->donor_name;
        if (! $member && ! $recipientName) {
            throw new \InvalidArgumentException('Per emettere ricevuta l\'incasso deve avere un socio/donatore in anagrafica o un donatore inserito a mano.');
        }

        $number = $this->nextReceiptNumber($incasso->paid_at);
        $issuedAt = $incasso->paid_at->toDateString();
        if ($incasso->type === Incasso::TYPE_DONAZIONE) {
            $causale = $incasso->description ?: Settings::get('causale_default_donazione', 'Erogazione liberale');
        } elseif ($incasso->type === Incasso::TYPE_ALTRO) {
            $causale = $incasso->description;
        } else {
            $baseQuota = Settings::get('causale_default_quota', 'Quota associativa');
            $causale = $incasso->description ?: ($incasso->subscription ? $baseQuota . ' ' . $incasso->subscription->year : $baseQuota);
        }

        $receipt = Receipt::create([
            'member_id' => $member?->id,
            'recipient_name' => $recipientName ?: null,
            'receivable_type' => Incasso::class,
            'receivable_id' => $incasso->id,
            'number' => $number,
            'issued_at' => $issuedAt,
            'type' => 'liberale',
        ]);

        $receiptText = $this->buildIncassoReceiptText($incasso, $receipt, $causale, $issuedAt, $receiptTextOverride);
        $path = $this->savePdf($receipt, $member, $recipientName, $incasso->amount, $causale, $issuedAt, $receiptText);
        $receipt->update(['file_path' => $path]);
        $incasso->update(['receipt_issued_at' => $incasso->paid_at]);

        return $receipt->fresh();
    }

    /**
     * Genera ricevuta per un rimborso spese.
     */
    public function generateForExpenseRefund(ExpenseRefund $refund): Receipt
    {
        if ($refund->receipt) {
            return $refund->receipt;
        }

        $member = $refund->member;
        $number = $this->nextReceiptNumber($refund->refund_date);
        $issuedAt = $refund->refund_date->format('Y-m-d');
        $causale = Settings::get('causale_default_rimborso', 'Rimborso spese');

        $receipt = Receipt::create([
            'member_id' => $member->id,
            'receivable_type' => ExpenseRefund::class,
            'receivable_id' => $refund->id,
            'number' => $number,
            'issued_at' => $issuedAt,
            'type' => 'rimborso',
        ]);

        $path = $this->savePdf($receipt, $member, null, $refund->total, $causale, $issuedAt, $causale);
        $receipt->update(['file_path' => $path]);
        $refund->update(['receipt_id' => $receipt->id, 'status' => 'stampata']);

        return $receipt->fresh();
    }

    /**
     * Rigenera il PDF della ricevuta mantenendo numero e dati esistenti (utile dopo modifiche al template).
     */
    public function regenerate(Receipt $receipt): Receipt
    {
        $receipt->load(['member', 'receivable' => fn ($q) => $q->with('subscription')]);
        $receivable = $receipt->receivable;
        if (! $receivable) {
            throw new \InvalidArgumentException('Ricevuta senza incasso o rimborso collegato.');
        }

        $member = $receipt->member;
        $recipientName = $receipt->recipient_name;
        $issuedAt = $receipt->issued_at->format('Y-m-d');

        if ($receivable instanceof Incasso) {
            $amount = $receivable->amount;
            if ($receivable->type === Incasso::TYPE_DONAZIONE) {
                $causale = $receivable->description ?: Settings::get('causale_default_donazione', 'Erogazione liberale');
            } elseif ($receivable->type === Incasso::TYPE_ALTRO) {
                $causale = $receivable->description;
            } else {
                $baseQuota = Settings::get('causale_default_quota', 'Quota associativa');
                $causale = $receivable->description ?: ($receivable->subscription ? $baseQuota . ' ' . $receivable->subscription->year : $baseQuota);
            }
        } elseif ($receivable instanceof ExpenseRefund) {
            $amount = $receivable->total;
            $causale = Settings::get('causale_default_rimborso', 'Rimborso spese');
        } else {
            throw new \InvalidArgumentException('Tipo di ricevuta non supportato per la rigenerazione.');
        }

        $override = $receivable instanceof Incasso ? $receivable->receipt_text_override : null;
        $receiptText = $receivable instanceof Incasso
            ? $this->buildIncassoReceiptText($receivable, $receipt, $causale, $issuedAt, $override)
            : $causale;
        $path = $this->savePdf($receipt, $member, $recipientName, $amount, $causale, $issuedAt, $receiptText);
        $receipt->update(['file_path' => $path]);

        return $receipt->fresh();
    }

    /**
     * Numero progressivo annuale per ricevute (es. 2026/0001).
     */
    private function nextReceiptNumber($date): string
    {
        $year = $date instanceof \DateTimeInterface ? $date->format('Y') : date('Y', strtotime($date));
        $last = Receipt::whereYear('issued_at', $year)->orderByDesc('id')->first();
        $seq = $last ? (int) substr($last->number, -4) + 1 : 1;
        return $year . '/' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Member|null  $member  Socio/donatore in anagrafica (null se donatore inserito a mano)
     * @param  string|null  $recipientName  Nome destinatario per ricevuta senza socio (donatore a mano)
     */
    private function savePdf(Receipt $receipt, ?Member $member, ?string $recipientName, $amount, string $causale, string $issuedAt, string $receiptHtml = ''): string
    {
        $logoDataUri = Attachment::logoDataUriForPdf();

        $viewData = [
            'receipt' => $receipt,
            'member' => $member,
            'recipient_name' => $recipientName,
            'amount' => $amount,
            'causale' => $causale,
            'receipt_html' => $this->sanitizeReceiptHtml($receiptHtml),
            'issued_at' => $issuedAt,
            'nome_associazione' => Settings::get('nome_associazione', 'La mia Associazione ETS'),
            'indirizzo_associazione' => Settings::get('indirizzo_associazione', ''),
            'email_associazione' => Settings::get('email_associazione', ''),
            'pec_associazione' => Settings::get('pec_associazione', ''),
            'codice_fiscale_associazione' => Settings::get('codice_fiscale_associazione', ''),
            'partita_iva_associazione' => Settings::get('partita_iva_associazione', ''),
            'logo_data_uri' => $logoDataUri,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('receipts.pdf_incasso', $viewData);
        $year = date('Y', strtotime($issuedAt));
        $dir = "media/ricevute/{$year}";
        Storage::disk('local')->makeDirectory($dir);
        $path = "{$dir}/{$receipt->id}-{$year}.pdf";
        Storage::disk('local')->put($path, $pdf->output());
        return $path;
    }

    private function buildIncassoReceiptText(Incasso $incasso, Receipt $receipt, string $causale, string $issuedAt, ?string $overrideText): string
    {
        $member = $incasso->member;
        $conto = $incasso->conto;
        $recipientName = $member ? trim($member->cognome . ' ' . $member->nome) : ($incasso->donor_name ?: '');
        $recipientCf = $member?->codice_fiscale ?: '';
        $iban = $conto?->iban ?: '';
        $tipoTemplate = $this->receiptTemplateTipoForIncasso($incasso);

        $replacements = [
            'nome-associazione' => Settings::get('nome_associazione', config('app.name')),
            'receipt_number' => $receipt->number,
            'data' => \Carbon\Carbon::parse($issuedAt)->format('d/m/Y'),
            'amount' => number_format((float) $incasso->amount, 2, ',', '.'),
            'causale' => $causale,
            'recipient_name' => $recipientName,
            'recipient_cf' => $recipientCf,
            'iban' => $iban,
            'anno' => (string) now()->year,
        ];

        $rendered = ReceiptTemplate::render($tipoTemplate, $replacements, $overrideText);
        $rendered = PlaceholderResolver::resolve($rendered, ['data' => \Carbon\Carbon::parse($issuedAt)]);
        $sanitized = $this->sanitizeReceiptHtml($rendered);

        if ($sanitized !== '') {
            return $sanitized;
        }

        $fallbackRendered = ReceiptTemplate::render($tipoTemplate, $replacements, null);
        $fallbackRendered = PlaceholderResolver::resolve($fallbackRendered, ['data' => \Carbon\Carbon::parse($issuedAt)]);

        return $this->sanitizeReceiptHtml($fallbackRendered);
    }

    private function receiptTemplateTipoForIncasso(Incasso $incasso): string
    {
        return match ($incasso->type) {
            Incasso::TYPE_DONAZIONE => 'incasso_donazione',
            Incasso::TYPE_ALTRO => 'incasso_altro',
            default => 'incasso_quota',
        };
    }

    private function sanitizeReceiptHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (! preg_match('/<[^>]+>/', $html)) {
            $escaped = htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<p>' . nl2br($escaped) . '</p>';
        }

        $allowedTags = '<p><br><strong><em><ul><ol><li><h1><h2><h3><span><b><i><u>'
            . '<table><thead><tbody><tfoot><tr><th><td><caption>';
        $clean = strip_tags($html, $allowedTags);

        $clean = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $clean) ?? $clean;
        $clean = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $clean) ?? $clean;
        $clean = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $clean) ?? $clean;
        $clean = preg_replace_callback(
            '/\sstyle\s*=\s*"([^"]*)"/i',
            function (array $m) {
                $san = $this->sanitizeReceiptStyleAttribute($m[1]);

                return $san !== '' ? ' style="' . htmlspecialchars($san, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"' : '';
            },
            $clean
        ) ?? $clean;
        $clean = preg_replace_callback(
            "/\sstyle\s*=\s*'([^']*)'/i",
            function (array $m) {
                $san = $this->sanitizeReceiptStyleAttribute($m[1]);

                return $san !== '' ? " style='" . htmlspecialchars($san, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "'" : '';
            },
            $clean
        ) ?? $clean;
        $clean = preg_replace('/\s+href\s*=\s*"javascript:[^"]*"/i', '', $clean) ?? $clean;
        $clean = preg_replace("/\s+href\s*=\s*'javascript:[^']*'/i", '', $clean) ?? $clean;

        if (trim(strip_tags($clean)) === '') {
            return '';
        }

        return $clean;
    }

    /**
     * Consente solo text-align sicuro (editor WYSIWYG) per il PDF, scartando altro CSS inline.
     */
    private function sanitizeReceiptStyleAttribute(string $style): string
    {
        $style = trim($style);
        if ($style === '') {
            return '';
        }
        $allowed = ['left', 'right', 'center', 'justify'];
        foreach (preg_split('/\s*;\s*/', $style) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^text-align\s*:\s*(.+)$/i', $part, $mm)) {
                $val = strtolower(trim($mm[1], " \t\"'"));
                if (in_array($val, $allowed, true)) {
                    return 'text-align: ' . $val;
                }
            }
        }

        return '';
    }

}
