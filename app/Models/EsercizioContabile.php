<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Esercizio contabile annuale.
 *
 * Traccia lo stato di apertura/chiusura di ogni anno fiscale per tenant.
 * La chiusura:
 *  1. Genera scritture di chiusura CE (azzera i conti economici)
 *  2. Genera scritture di chiusura SP (azzera i conti patrimoniali)
 *  3. Blocca tutti i movimenti dell'anno (locked = true)
 *
 * La riapertura (solo anno corrente, se esiste):
 *  1. Cancella i movimenti di chiusura/apertura generati
 *  2. Sblocca i movimenti (locked = false)
 *  3. Riporta stato ad 'aperto'
 */
class EsercizioContabile extends Model
{
    use BelongsToTenant;

    public const STATO_APERTO = 'aperto';
    public const STATO_CHIUSO = 'chiuso';

    protected $table = 'esercizi_contabili';

    protected $fillable = [
        'tenant_id',
        'anno',
        'stato',
        'data_apertura',
        'data_chiusura',
        'conto_chiusura_ce_id',
        'conto_apertura_id',
        'movimento_chiusura_ce_id',
        'movimento_chiusura_sp_id',
        'movimento_apertura_id',
        'locked_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'anno'           => 'integer',
            'data_apertura'  => 'date',
            'data_chiusura'  => 'date',
            'locked_at'      => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopeAperti(Builder $query): Builder
    {
        return $query->where('stato', self::STATO_APERTO);
    }

    public function scopeChiusi(Builder $query): Builder
    {
        return $query->where('stato', self::STATO_CHIUSO);
    }

    public function scopePerAnno(Builder $query, int $anno): Builder
    {
        return $query->where('anno', $anno);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────

    public function isAperto(): bool
    {
        return $this->stato === self::STATO_APERTO;
    }

    public function isChiuso(): bool
    {
        return $this->stato === self::STATO_CHIUSO;
    }

    /**
     * True se i conti di contropartita sono configurati (necessari per la chiusura).
     */
    public function isConfiguratoPerChiusura(): bool
    {
        return $this->conto_chiusura_ce_id !== null
            && $this->conto_apertura_id !== null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relazioni
    // ─────────────────────────────────────────────────────────────────────

    public function contoChiusuraCe(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_chiusura_ce_id');
    }

    public function contoApertura(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_apertura_id');
    }

    public function movimentoChiusuraCe(): BelongsTo
    {
        return $this->belongsTo(MovimentoContabile::class, 'movimento_chiusura_ce_id');
    }

    public function movimentoChiusuraSp(): BelongsTo
    {
        return $this->belongsTo(MovimentoContabile::class, 'movimento_chiusura_sp_id');
    }

    public function movimentoApertura(): BelongsTo
    {
        return $this->belongsTo(MovimentoContabile::class, 'movimento_apertura_id');
    }
}
