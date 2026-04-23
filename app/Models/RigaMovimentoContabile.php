<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riga di un movimento contabile (scrittura dare/avere su un conto).
 *
 * Invarianti (enforcement via MovimentoContabileService):
 *  - il conto deve essere movimentabile (livello 4)
 *  - importo_dare XOR importo_avere > 0 (non entrambi zero, non entrambi > 0)
 *  - la somma dare di tutte le righe del movimento = somma avere
 *
 * Non usa BelongsToTenant perché il tenant_id è già garantito
 * dalla FK su movimenti_contabili (cascade delete).
 */
class RigaMovimentoContabile extends Model
{
    protected $table = 'righe_movimento_contabile';

    protected $fillable = [
        'movimento_id',
        'ordine',
        'conto_contabile_id',
        'descrizione',
        'importo_dare',
        'importo_avere',
        'centro_costo',
        'gestione',
    ];

    protected function casts(): array
    {
        return [
            'ordine'        => 'integer',
            'importo_dare'  => 'decimal:2',
            'importo_avere' => 'decimal:2',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relazioni
    // ─────────────────────────────────────────────────────────────────────

    public function movimento(): BelongsTo
    {
        return $this->belongsTo(MovimentoContabile::class, 'movimento_id');
    }

    public function contoContabile(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_contabile_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopeDare(Builder $q): Builder
    {
        return $q->where('importo_dare', '>', 0);
    }

    public function scopeAvere(Builder $q): Builder
    {
        return $q->where('importo_avere', '>', 0);
    }

    public function scopePerConto(Builder $q, int $contoId): Builder
    {
        return $q->where('conto_contabile_id', $contoId);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    public function isDare(): bool
    {
        return (float) $this->importo_dare > 0;
    }

    public function isAvere(): bool
    {
        return (float) $this->importo_avere > 0;
    }

    /**
     * Importo della riga (indipendentemente dal segno).
     */
    public function importo(): float
    {
        return (float) ($this->importo_dare > 0 ? $this->importo_dare : $this->importo_avere);
    }

    /**
     * Importo con segno: positivo se dare, negativo se avere.
     * Utile per calcoli di saldo.
     */
    public function importoSegno(): float
    {
        return $this->isDare() ? $this->importo() : -$this->importo();
    }
}
