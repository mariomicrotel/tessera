<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quote di capitale sociale di una cooperativa.
 *
 * Ogni socio possiede un record con il numero di quote sottoscritte.
 * Il versamento può essere parziale (parzialmente_versata) o completo (versata).
 * Il socio può riscattare le quote quando esce dalla cooperativa.
 */
class CooperativeShare extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'member_id',
        'numero_quote',
        'valore_unitario',
        'totale_sottoscritto',
        'totale_versato',
        'data_sottoscrizione',
        'data_versamento',
        'data_riscatto',
        'motivo_riscatto',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'numero_quote'        => 'integer',
            'valore_unitario'     => 'decimal:2',
            'totale_sottoscritto' => 'decimal:2',
            'totale_versato'      => 'decimal:2',
            'data_sottoscrizione' => 'date',
            'data_versamento'     => 'date',
            'data_riscatto'       => 'date',
            'status'              => 'string',
        ];
    }

    // ── Relazioni ─────────────────────────────────────────────────────────────

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Quote attive: non riscattate e non annullate.
     */
    public function scopeAttive(Builder $query): Builder
    {
        return $query->whereIn('status', ['sottoscritta', 'parzialmente_versata', 'versata']);
    }

    /**
     * Quote completamente versate.
     */
    public function scopeVersate(Builder $query): Builder
    {
        return $query->where('status', 'versata');
    }

    /**
     * Quote riscattate (socio uscito).
     */
    public function scopeRiscattate(Builder $query): Builder
    {
        return $query->where('status', 'riscattata');
    }

    /**
     * Quote per un socio specifico.
     */
    public function scopeForMember(Builder $query, int $memberId): Builder
    {
        return $query->where('member_id', $memberId);
    }

    // ── Accessor / helper ─────────────────────────────────────────────────────

    /**
     * Importo ancora da versare.
     */
    public function getAncoraDaVersareAttribute(): float
    {
        return max(0, (float) $this->totale_sottoscritto - (float) $this->totale_versato);
    }

    /**
     * Percentuale versamento (0–100).
     */
    public function getPercentualeVersamentoAttribute(): float
    {
        if ((float) $this->totale_sottoscritto === 0.0) {
            return 0.0;
        }
        return ((float) $this->totale_versato / (float) $this->totale_sottoscritto) * 100;
    }

    /**
     * La quota è completamente versata.
     */
    public function isVersata(): bool
    {
        return $this->status === 'versata';
    }

    /**
     * La quota è stata riscattata (socio uscito).
     */
    public function isRiscattata(): bool
    {
        return $this->status === 'riscattata';
    }

    /**
     * La quota è attiva (non riscattata e non annullata).
     */
    public function isAttiva(): bool
    {
        return in_array($this->status, ['sottoscritta', 'parzialmente_versata', 'versata'], true);
    }
}
