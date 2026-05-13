<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Richiesta di documenti/informazioni del consulente verso il cliente.
 *
 * Usa BelongsToTenant — viene sempre consultata nel contesto di un tenant specifico.
 */
class ConsultantRequest extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'consultant_requests';

    protected $fillable = [
        'tenant_id',
        'consultant_user_id',
        'titolo',
        'descrizione',
        'priorita',
        'stato',
        'data_scadenza',
        'chiusa_il',
    ];

    protected $casts = [
        'data_scadenza' => 'date',
        'chiusa_il'     => 'datetime',
    ];

    /* ── Costanti stato ─────────────────────────────────────────────────── */

    const STATO_APERTA              = 'aperta';
    const STATO_IN_ATTESA_RISPOSTA  = 'in_attesa_risposta';
    const STATO_RISPOSTA_RICEVUTA   = 'risposta_ricevuta';
    const STATO_CHIUSA              = 'chiusa';
    const STATO_ANNULLATA           = 'annullata';

    /* ── Relazioni ─────────────────────────────────────────────────────── */

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_user_id');
    }

    public function documenti(): HasMany
    {
        return $this->hasMany(ConsultantRequestDocument::class, 'consultant_request_id');
    }

    /* ── Scope ─────────────────────────────────────────────────────────── */

    public function scopeAperte($query)
    {
        return $query->whereNotIn('stato', [self::STATO_CHIUSA, self::STATO_ANNULLATA]);
    }

    public function scopePerConsulente($query, int $userId)
    {
        return $query->where('consultant_user_id', $userId);
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    public function isAperta(): bool
    {
        return ! in_array($this->stato, [self::STATO_CHIUSA, self::STATO_ANNULLATA]);
    }

    public function badgeColor(): string
    {
        return match ($this->stato) {
            self::STATO_APERTA             => 'blue',
            self::STATO_IN_ATTESA_RISPOSTA => 'yellow',
            self::STATO_RISPOSTA_RICEVUTA  => 'green',
            self::STATO_CHIUSA             => 'gray',
            self::STATO_ANNULLATA          => 'red',
            default                        => 'gray',
        };
    }

    public function prioritaBadgeColor(): string
    {
        return match ($this->priorita) {
            'urgente' => 'red',
            'alta'    => 'orange',
            'normale' => 'blue',
            'bassa'   => 'gray',
            default   => 'gray',
        };
    }
}
