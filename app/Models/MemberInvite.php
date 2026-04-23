<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemberInvite extends Model
{
    use BelongsToTenant;

    protected $table = 'member_invites';

    protected $fillable = [
        'email',
        'token',
        'expires_at',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('used_at')
            ->where('expires_at', '>', now());
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * Elimina gli inviti scaduti (expires_at < now()). Restituisce il numero di record eliminati.
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }

    /**
     * Esegue la pulizia degli inviti scaduti al massimo una volta al mese (usa Cache).
     * Chiamare da controller così la pulizia avviene senza cron.
     */
    public static function runCleanupIfDue(): void
    {
        $key = 'member_invites_cleanup_last_run';
        $lastRun = \Illuminate\Support\Facades\Cache::get($key);
        if ($lastRun !== null && $lastRun->gt(now()->subMonth())) {
            return;
        }
        static::cleanupExpired();
        \Illuminate\Support\Facades\Cache::put($key, now(), 86400 * 35); // 35 giorni TTL
    }

    /**
     * Genera un token univoco (64 caratteri).
     */
    public static function generateUniqueToken(): string
    {
        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $token = Str::random(64);
            if (! static::where('token', $token)->exists()) {
                return $token;
            }
        }
        return Str::random(64) . bin2hex(random_bytes(8));
    }
}
