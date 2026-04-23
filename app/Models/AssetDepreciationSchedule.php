<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Piano di ammortamento annuale per un cespite.
 *
 * Una riga per coppia (asset, esercizio).
 * Stato del ciclo di vita:
 *   bozza      → calcolata automaticamente ma non ancora registrata
 *   definitivo → scrittura contabile emessa e confermata
 *   stornato   → movimento contabile annullato
 *
 * Il campo `quota_registrata` può essere modificato dall'admin (es. per
 * applicare ammortamento ridotto) prima della conferma definitiva.
 */
class AssetDepreciationSchedule extends Model
{
    use BelongsToTenant;

    protected $table = 'asset_depreciation_schedules';

    // ── Stati ────────────────────────────────────────────────────────────────
    public const STATO_BOZZA      = 'bozza';
    public const STATO_DEFINITIVO = 'definitivo';
    public const STATO_STORNATO   = 'stornato';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'esercizio',
        'quota_calcolata',
        'quota_registrata',
        'aliquota_applicata',
        'deducibilita_applicata',
        'fondo_inizio_anno',
        'fondo_fine_anno',
        'valore_residuo_fine_anno',
        'movimento_contabile_id',
        'stato',
        'data_registrazione',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'esercizio'                 => 'integer',
            'quota_calcolata'           => 'decimal:2',
            'quota_registrata'          => 'decimal:2',
            'aliquota_applicata'        => 'decimal:2',
            'deducibilita_applicata'    => 'decimal:2',
            'fondo_inizio_anno'         => 'decimal:2',
            'fondo_fine_anno'           => 'decimal:2',
            'valore_residuo_fine_anno'  => 'decimal:2',
            'data_registrazione'        => 'date',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopeBozze(Builder $query): Builder
    {
        return $query->where('stato', self::STATO_BOZZA);
    }

    public function scopeDefinitive(Builder $query): Builder
    {
        return $query->where('stato', self::STATO_DEFINITIVO);
    }

    public function scopeEsercizio(Builder $query, int $anno): Builder
    {
        return $query->where('esercizio', $anno);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relazioni
    // ─────────────────────────────────────────────────────────────────────────

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function movimentoContabile(): BelongsTo
    {
        return $this->belongsTo(MovimentoContabile::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function isBozza(): bool
    {
        return $this->stato === self::STATO_BOZZA;
    }

    public function isDefinitivo(): bool
    {
        return $this->stato === self::STATO_DEFINITIVO;
    }

    /**
     * Importo deducibile fiscalmente (quota × percentuale deducibilità).
     */
    public function quotaDeducibile(): float
    {
        return round((float) $this->quota_registrata * (float) $this->deducibilita_applicata / 100, 2);
    }
}
