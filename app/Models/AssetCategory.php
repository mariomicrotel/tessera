<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * Categoria fiscale di cespite (DM 31/12/1988 — coefficienti ministeriali).
 *
 * Le categorie `di_sistema = true` (tenant_id null) sono condivise tra
 * tutti i tenant e non eliminabili. Ogni tenant può creare categorie
 * personalizzate aggiuntive.
 *
 * Il metodo `scopePerTenant()` restituisce sia le categorie di sistema
 * che quelle custom del tenant corrente.
 *
 * NOTA: non usa BelongsToTenant perché il global scope escluderebbe
 * le categorie di sistema (tenant_id = null).
 */
class AssetCategory extends Model
{
    use SoftDeletes;

    protected $table = 'asset_categories';

    protected $fillable = [
        'tenant_id',
        'codice',
        'descrizione',
        'coefficiente_ministeriale',
        'percentuale_deducibilita_default',
        'primo_anno_ridotto_default',
        'conto_bene_default_id',
        'conto_fondo_default_id',
        'conto_ammortamento_default_id',
        'di_sistema',
        'attivo',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'coefficiente_ministeriale'      => 'decimal:2',
            'percentuale_deducibilita_default' => 'decimal:2',
            'primo_anno_ridotto_default'      => 'boolean',
            'di_sistema'                     => 'boolean',
            'attivo'                         => 'boolean',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Restituisce le categorie visibili al tenant: di sistema + proprie.
     */
    public function scopePerTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where(function (Builder $q) use ($tenantId) {
            $q->whereNull('tenant_id')              // categorie di sistema
              ->orWhere('tenant_id', $tenantId);    // categorie custom del tenant
        });
    }

    /**
     * Solo categorie attive.
     */
    public function scopeAttive(Builder $query): Builder
    {
        return $query->where('attivo', true);
    }

    /**
     * Solo categorie di sistema.
     */
    public function scopeDiSistema(Builder $query): Builder
    {
        return $query->where('di_sistema', true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relazioni
    // ─────────────────────────────────────────────────────────────────────────

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function contoBene(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_bene_default_id');
    }

    public function contoFondo(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_fondo_default_id');
    }

    public function contoAmmortamento(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_ammortamento_default_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Restituisce l'aliquota effettiva del primo anno (50% del coefficiente).
     */
    public function aliquotaPrimoAnno(): float
    {
        return $this->primo_anno_ridotto_default
            ? (float) $this->coefficiente_ministeriale / 2
            : (float) $this->coefficiente_ministeriale;
    }
}
