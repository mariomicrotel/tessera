<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class MailAccount extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'imap_folder',
        'is_active',
        'sync_days',
        'last_synced_at',
        // SMTP
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'smtp_from_name',
        'smtp_from_email',
    ];

    protected $hidden = ['imap_password', 'smtp_password'];

    protected function casts(): array
    {
        return [
            'imap_port'      => 'integer',
            'smtp_port'      => 'integer',
            'sync_days'      => 'integer',
            'is_active'      => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    // ── Relazioni ─────────────────────────────────────────────────────────────

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }

    // ── Accessori / Mutatori ──────────────────────────────────────────────────

    /** Salva la password IMAP cifrata */
    public function setImapPasswordAttribute(string $value): void
    {
        try {
            Crypt::decrypt($value);
            $this->attributes['imap_password'] = $value; // già cifrata
        } catch (\Exception) {
            $this->attributes['imap_password'] = Crypt::encrypt($value);
        }
    }

    /** Restituisce la password IMAP in chiaro */
    public function getDecryptedPassword(): string
    {
        return Crypt::decrypt($this->attributes['imap_password']);
    }

    /** Salva la password SMTP cifrata */
    public function setSmtpPasswordAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['smtp_password'] = null;
            return;
        }
        try {
            Crypt::decrypt($value);
            $this->attributes['smtp_password'] = $value; // già cifrata
        } catch (\Exception) {
            $this->attributes['smtp_password'] = Crypt::encrypt($value);
        }
    }

    /** Restituisce la password SMTP in chiaro (null se non configurata) */
    public function getDecryptedSmtpPassword(): ?string
    {
        if (empty($this->attributes['smtp_password'])) {
            return null;
        }
        try {
            return Crypt::decrypt($this->attributes['smtp_password']);
        } catch (\Exception) {
            return null;
        }
    }

    /** True se la casella ha SMTP configurato */
    public function hasSmtp(): bool
    {
        return ! empty($this->smtp_host) && ! empty($this->smtp_username);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Conta messaggi non letti */
    public function unreadCount(): int
    {
        return $this->messages()->where('is_read', false)->count();
    }
}
