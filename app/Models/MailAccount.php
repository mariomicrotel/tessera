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
    ];

    protected $hidden = ['imap_password'];

    protected function casts(): array
    {
        return [
            'imap_port'      => 'integer',
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

    /** Salva la password cifrata con Crypt::encrypt() */
    public function setImapPasswordAttribute(string $value): void
    {
        // Evita doppia cifratura (es. durante la modifica se il campo non cambia)
        try {
            Crypt::decrypt($value);
            $this->attributes['imap_password'] = $value; // già cifrata
        } catch (\Exception) {
            $this->attributes['imap_password'] = Crypt::encrypt($value);
        }
    }

    /** Restituisce la password in chiaro */
    public function getDecryptedPassword(): string
    {
        return Crypt::decrypt($this->attributes['imap_password']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Conta messaggi non letti */
    public function unreadCount(): int
    {
        return $this->messages()->where('is_read', false)->count();
    }
}
