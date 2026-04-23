<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cespite aziendale — Registro Cespiti conforme normativa italiana.
 *
 * Estende il model base (name, code, purchase_date, value, notes, property_id)
 * con tutti i metadati fiscali necessari per il calcolo degli ammortamenti
 * ai sensi del DM 31/12/1988 (coefficienti ministeriali).
 *
 * Ciclo di vita:
 *   in_uso → dismesso (o venduto) tramite AssetDisposal
 *
 * L'aliquota di ammortamento è determinata da (in ordine di priorità):
 *   1. aliquota_custom (override manuale)
 *   2. AssetCategory::coefficiente_ministeriale
 *
 * Il primo anno si applica il 50% del coefficiente (prima_anno_ridotto = true).
 */
class Asset extends Model
{
    use BelongsToTenant, SoftDeletes;

    // ── Metodi di ammortamento ────────────────────────────────────────────────
    public const METODO_ORDINARIO   = 'ordinario';
    public const METODO_RIDOTTO     = 'ridotto';
    public const METODO_ACCELERATO  = 'accelerato';
    public const METODO_ANTICIPATO  = 'anticipato';

    public const METODI_LABEL = [
        self::METODO_ORDINARIO   => 'Ordinario',
        self::METODO_RIDOTTO     => 'Ridotto',
        self::METODO_ACCELERATO  => 'Accelerato',
        self::METODO_ANTICIPATO  => 'Anticipato',
    ];

    // ── Stato cespite ─────────────────────────────────────────────────────────
    public const STATO_IN_USO   = 'in_uso';
    public const STATO_DISMESSO = 'dismesso';
    public const STATO_VENDUTO  = 'venduto';

    public const STATI_LABEL = [
        self::STATO_IN_USO   => 'In uso',
        self::STATO_DISMESSO => 'Dismesso',
        self::STATO_VENDUTO  => 'Venduto',
    ];

    protected $fillable = [
        // campi originali
        'property_id',
        'name',
        'code',
        'purchase_date',
        'value',
        'notes',
        // nuovi campi fiscali
        'tenant_id',
        'asset_category_id',
        'supplier_id',
        'fattura_passiva_id',
        'matricola',
        'costo_storico',
        'data_inizio_ammortamento',
        'aliquota_custom',
        'metodo_ammortamento',
        'primo_anno_ridotto',
        'percentuale_deducibilita',
        'stato',
        'conto_bene_id',
        'conto_fondo_id',
        'note_fiscali',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date'              => 'date',
            'data_inizio_ammortamento'   => 'date',
            'value'                      => 'decimal:2',
            'costo_storico'              => 'decimal:2',
            'aliquota_custom'            => 'decimal:2',
            'percentuale_deducibilita'   => 'decimal:2',
            'primo_anno_ridotto'         => 'boolean',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    /** Solo cespiti attivi (in uso). */
    public function scopeInUso(Builder $query): Builder
    {
        return $query->where('stato', self::STATO_IN_USO);
    }

    /** Cespiti con ammortamento non ancora completato. */
    public function scopeAmmortizzabili(Builder $query): Builder
    {
        return $query->where('stato', self::STATO_IN_USO)
                     ->where('costo_storico', '>', 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relazioni
    // ─────────────────────────────────────────────────────────────────────────

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function fatturaPassiva(): BelongsTo
    {
        return $this->belongsTo(FatturaPassiva::class);
    }

    public function contoBene(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_bene_id');
    }

    public function contoFondo(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_fondo_id');
    }

    /** Piano di ammortamento (righe per esercizio). */
    public function depreciationSchedules(): HasMany
    {
        return $this->hasMany(AssetDepreciationSchedule::class)->orderBy('esercizio');
    }

    /** Dismissione/vendita (al massimo una per cespite). */
    public function disposal(): HasOne
    {
        return $this->hasOne(AssetDisposal::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers fiscali
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Aliquota di ammortamento applicabile (aliquota_custom ha priorità).
     */
    public function aliquotaEffettiva(): float
    {
        if ($this->aliquota_custom !== null && (float) $this->aliquota_custom > 0) {
            return (float) $this->aliquota_custom;
        }

        return $this->category ? (float) $this->category->coefficiente_ministeriale : 0.0;
    }

    /**
     * Fondo ammortamento cumulato basato sulle schedule definitive.
     */
    public function fondoCumulato(): float
    {
        return (float) $this->depreciationSchedules()
            ->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)
            ->sum('quota_registrata');
    }

    /**
     * Valore netto contabile corrente = costo storico - fondo cumulato.
     */
    public function valoreNetto(): float
    {
        return max(0, (float) $this->costo_storico - $this->fondoCumulato());
    }

    /**
     * True se il cespite è stato completamente ammortizzato.
     */
    public function isCompletamenteAmmortizzato(): bool
    {
        return $this->valoreNetto() <= 0;
    }

    /**
     * Conto del bene: usa l'override cespite o il default della categoria.
     */
    public function contoBenoEffettivo(): ?ContoContabile
    {
        if ($this->conto_bene_id) {
            return $this->contoBene;
        }

        return $this->category?->contoBene;
    }

    /**
     * Conto del fondo: usa l'override cespite o il default della categoria.
     */
    public function contoFondoEffettivo(): ?ContoContabile
    {
        if ($this->conto_fondo_id) {
            return $this->contoFondo;
        }

        return $this->category?->contoFondo;
    }
}
