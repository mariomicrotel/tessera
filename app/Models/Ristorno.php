<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ristorno deliberato dall'assemblea di una cooperativa.
 *
 * Delibera unica annuale di distribuzione ai soci dell'avanzo mutualistico
 * (art. 2545-sexies c.c.). Ogni ristorno si scompone in entries per socio
 * (RistornoEntry) proporzionalmente all'apporto mutualistico.
 *
 * Sui ristorni è applicata una ritenuta fiscale del 30% (default) salvo
 * regimi particolari.
 */
class Ristorno extends Model
{
    use BelongsToTenant;

    public const STATUS_DELIBERATO   = 'deliberato';
    public const STATUS_IN_PAGAMENTO = 'in_pagamento';
    public const STATUS_PAGATO       = 'pagato';
    public const STATUS_ANNULLATO    = 'annullato';

    protected $table = 'ristorni';

    protected $fillable = [
        'anno',
        'importo_totale_deliberato',
        'aliquota_ritenuta',
        'data_delibera_assemblea',
        'data_pagamento',
        'verbale_id',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'anno'                       => 'integer',
            'importo_totale_deliberato'  => 'decimal:2',
            'aliquota_ritenuta'          => 'decimal:4',
            'data_delibera_assemblea'    => 'date',
            'data_pagamento'             => 'date',
            'status'                     => 'string',
        ];
    }

    // ── Relazioni ─────────────────────────────────────────────────────────────

    public function entries(): HasMany
    {
        return $this->hasMany(RistornoEntry::class, 'ristorno_id');
    }

    public function verbale(): BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'verbale_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeDeliberati(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_DELIBERATO);
    }

    public function scopePagati(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PAGATO);
    }

    public function scopePerAnno(Builder $q, int $anno): Builder
    {
        return $q->where('anno', $anno);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    public function isDeliberato(): bool
    {
        return $this->status === self::STATUS_DELIBERATO;
    }

    public function isPagato(): bool
    {
        return $this->status === self::STATUS_PAGATO;
    }

    public function isAnnullato(): bool
    {
        return $this->status === self::STATUS_ANNULLATO;
    }

    /**
     * Importo totale ritenuta calcolato sulle entries (non sul lordo).
     */
    public function getTotaleRitenutaAttribute(): float
    {
        return round((float) $this->entries->sum('importo_ritenuta'), 2);
    }

    /**
     * Importo totale netto pagato ai soci.
     */
    public function getTotaleNettoAttribute(): float
    {
        return round((float) $this->entries->sum('importo_netto'), 2);
    }
}
