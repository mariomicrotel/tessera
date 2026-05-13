<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Movimento amministrativo semplificato.
 *
 * Alternativa a PrimaNotaEntry per enti che usano l'amministrazione
 * semplificata: non richiede piano dei conti né codici rendiconto.
 *
 * - tipo='entrata'   → importo entra nel conto
 * - tipo='uscita'    → importo esce dal conto
 * - tipo='giroconto' → importo si sposta da conto a conto_destinazione
 *
 * importo è sempre positivo; il segno viene dedotto dal tipo.
 */
class AdministrativeMovement extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'administrative_movements';

    protected $fillable = [
        'tenant_id',
        'data',
        'tipo',
        'importo',
        'descrizione',
        'category_id',
        'conto_id',
        'conto_destinazione_id',
        'riferimento',
        'note',
        'entryable_type',
        'entryable_id',
        'tags',
    ];

    protected $casts = [
        'data'    => 'date',
        'importo' => 'decimal:2',
        'tags'    => 'array',
    ];

    /* ── Costanti ──────────────────────────────────────────────────────── */

    const TIPO_ENTRATA   = 'entrata';
    const TIPO_USCITA    = 'uscita';
    const TIPO_GIROCONTO = 'giroconto';

    /* ── Relazioni ─────────────────────────────────────────────────────── */

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdministrativeMovementCategory::class, 'category_id');
    }

    public function conto(): BelongsTo
    {
        return $this->belongsTo(Conto::class, 'conto_id');
    }

    public function contoDestinazione(): BelongsTo
    {
        return $this->belongsTo(Conto::class, 'conto_destinazione_id');
    }

    /**
     * Collegamento opzionale al documento d'origine (Incasso, ExpenseRefund, ecc.).
     */
    public function entryable(): MorphTo
    {
        return $this->morphTo();
    }

    /* ── Scope ─────────────────────────────────────────────────────────── */

    public function scopeEntrate($query)
    {
        return $query->where('tipo', self::TIPO_ENTRATA);
    }

    public function scopeUscite($query)
    {
        return $query->where('tipo', self::TIPO_USCITA);
    }

    public function scopeNelPeriodo($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('data', '>=', $from);
        }
        if ($to) {
            $query->where('data', '<=', $to);
        }
        return $query;
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    /**
     * Importo con segno: positivo per entrate, negativo per uscite.
     * I giroconti restituiscono 0 (neutri per il totale).
     */
    public function importoConSegno(): float
    {
        return match ($this->tipo) {
            self::TIPO_ENTRATA   =>  (float) $this->importo,
            self::TIPO_USCITA    => -(float) $this->importo,
            default              =>  0.0,
        };
    }

    public function tipoLabel(): string
    {
        return match ($this->tipo) {
            self::TIPO_ENTRATA   => 'Entrata',
            self::TIPO_USCITA    => 'Uscita',
            self::TIPO_GIROCONTO => 'Giroconto',
            default              => $this->tipo,
        };
    }

    public function tipoBadgeColor(): string
    {
        return match ($this->tipo) {
            self::TIPO_ENTRATA   => 'green',
            self::TIPO_USCITA    => 'red',
            self::TIPO_GIROCONTO => 'blue',
            default              => 'gray',
        };
    }
}
