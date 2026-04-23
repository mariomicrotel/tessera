<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Testata di un movimento contabile in partita doppia.
 *
 * Un movimento è valido quando:
 *  - ha almeno 2 righe (una dare, una avere)
 *  - SUM(righe.importo_dare) = SUM(righe.importo_avere)  [bilanciato]
 *  - tutti i conti usati nelle righe sono movimentabili (livello 4)
 *
 * Ciclo di vita:
 *  bozza → definitivo → stornato
 *
 * Solo i movimenti "definitivi" aggiornano i saldi dei conti.
 * Un movimento stornato genera un movimento di storno inverso
 * (stesso importo, dare/avere invertiti).
 */
class MovimentoContabile extends Model
{
    use BelongsToTenant;

    // ── Stati ─────────────────────────────────────────────────────────────
    public const STATO_BOZZA      = 'bozza';
    public const STATO_DEFINITIVO = 'definitivo';
    public const STATO_STORNATO   = 'stornato';

    public const STATI = [
        self::STATO_BOZZA,
        self::STATO_DEFINITIVO,
        self::STATO_STORNATO,
    ];

    // ── Gestione (cooperative) ────────────────────────────────────────────
    public const GESTIONE_ISTITUZIONALE = 'istituzionale';
    public const GESTIONE_COMMERCIALE   = 'commerciale';

    protected $table = 'movimenti_contabili';

    protected $fillable = [
        'tenant_id',
        'anno_esercizio',
        'numero',
        'data_registrazione',
        'data_competenza',
        'causale_id',
        'descrizione',
        'gestione',
        'stato',
        'locked',
        'numero_documento',
        'data_documento',
        'movimento_origine_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'anno_esercizio'     => 'integer',
            'numero'             => 'integer',
            'data_registrazione' => 'date',
            'data_competenza'    => 'date',
            'data_documento'     => 'date',
            'locked'             => 'boolean',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relazioni
    // ─────────────────────────────────────────────────────────────────────

    public function causale(): BelongsTo
    {
        return $this->belongsTo(CausaleContabile::class, 'causale_id');
    }

    public function righe(): HasMany
    {
        return $this->hasMany(RigaMovimentoContabile::class, 'movimento_id')->orderBy('ordine');
    }

    /** Movimento originale di cui questo è lo storno. */
    public function movimentoOrigine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'movimento_origine_id');
    }

    /** Movimento di storno generato da questo. */
    public function storno(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'movimento_origine_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopeDefinitivi(Builder $q): Builder
    {
        return $q->where('stato', self::STATO_DEFINITIVO);
    }

    public function scopeBozze(Builder $q): Builder
    {
        return $q->where('stato', self::STATO_BOZZA);
    }

    public function scopePerAnno(Builder $q, int $anno): Builder
    {
        return $q->where('anno_esercizio', $anno);
    }

    public function scopePerPeriodo(Builder $q, string $dal, string $al): Builder
    {
        return $q->whereBetween('data_registrazione', [$dal, $al]);
    }

    public function scopeConDocumento(Builder $q, string $numeroDoc): Builder
    {
        return $q->where('numero_documento', $numeroDoc);
    }

    public function scopeNonLocked(Builder $q): Builder
    {
        return $q->where('locked', false);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $q;
        }

        return $q->where(function (Builder $q) use ($term) {
            $q->where('descrizione', 'like', "%{$term}%")
              ->orWhere('numero_documento', 'like', "%{$term}%");
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    public function isBozza(): bool
    {
        return $this->stato === self::STATO_BOZZA;
    }

    public function isDefinitivo(): bool
    {
        return $this->stato === self::STATO_DEFINITIVO;
    }

    public function isStornato(): bool
    {
        return $this->stato === self::STATO_STORNATO;
    }

    public function isLocked(): bool
    {
        return (bool) $this->locked;
    }

    /**
     * True se il movimento è modificabile (bozza e non locked).
     */
    public function isModificabile(): bool
    {
        return $this->isBozza() && ! $this->isLocked();
    }

    /**
     * Totale dare calcolato dalle righe (già caricate o eager-loaded).
     */
    public function totaleDare(): \Illuminate\Support\Number|float
    {
        return $this->righe->sum('importo_dare');
    }

    /**
     * Totale avere calcolato dalle righe.
     */
    public function totaleAvere(): \Illuminate\Support\Number|float
    {
        return $this->righe->sum('importo_avere');
    }

    /**
     * True se il movimento è bilanciato (dare == avere).
     * Usa una tolleranza di 0.01 centesimi per floating-point.
     */
    public function isBilanciato(): bool
    {
        return abs($this->totaleDare() - $this->totaleAvere()) < 0.005;
    }

    /**
     * Etichetta leggibile numero/anno.
     */
    public function getEtichettaAttribute(): string
    {
        return sprintf('%d/%04d', $this->numero, $this->anno_esercizio);
    }
}
