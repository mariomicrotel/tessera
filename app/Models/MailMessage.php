<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailMessage extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'mail_account_id',
        'uid',
        'message_id',
        'folder',
        'subject',
        'from_name',
        'from_email',
        'to_addresses',
        'cc_addresses',
        'sent_at',
        'body_html',
        'body_text',
        'is_read',
        'is_flagged',
        'has_attachments',
    ];

    protected function casts(): array
    {
        return [
            'to_addresses'   => 'array',
            'cc_addresses'   => 'array',
            'sent_at'        => 'datetime',
            'is_read'        => 'boolean',
            'is_flagged'     => 'boolean',
            'has_attachments' => 'boolean',
            'uid'            => 'integer',
        ];
    }

    // ── Relazioni ─────────────────────────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    // ── Accessori ─────────────────────────────────────────────────────────────

    /** Mittente leggibile: "Mario Rossi <mario@example.com>" oppure solo l'email */
    public function getFromAttribute(): string
    {
        if ($this->from_name) {
            return "{$this->from_name} <{$this->from_email}>";
        }
        return $this->from_email ?? '(sconosciuto)';
    }

    /** Snippet del testo (max 160 car.) per la lista messaggi */
    public function getSnippetAttribute(): string
    {
        $text = $this->body_text ?? strip_tags($this->body_html ?? '');
        $text = preg_replace('/\s+/', ' ', trim($text));
        return mb_substr($text, 0, 160);
    }
}
