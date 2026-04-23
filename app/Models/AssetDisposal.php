<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dismissione o vendita di un cespite.
 *
 * Registra l'uscita definitiva di un bene dal patrimonio aziendale.
 * La scrittura contabile di dismissione storna costo storico e fondo
 * ammortamento, rilevando l'eventuale plus/minusvalenza.
 *
 * plusvalenza_minusvalenza > 0 → plusvalenza (tassabile)
 * plusvalenza_minusvalenza < 0 → minusvalenza (deducibile)
 * plusvalenza_minusvalenza = 0 → dismissione a pareggio
 */
class AssetDisposal extends Model
{
    use BelongsToTenant;

    protected $table = 'asset_disposals';

    // ── Tipi di dismissione ───────────────────────────────────────────────────
    public const TIPO_VENDITA      = 'vendita';
    public const TIPO_ROTTAMAZIONE = 'rottamazione';
    public const TIPO_DONAZIONE    = 'donazione';
    public const TIPO_FURTO        = 'furto';

    public const TIPI_LABEL = [
        self::TIPO_VENDITA      => 'Vendita',
        self::TIPO_ROTTAMAZIONE => 'Rottamazione / Demolizione',
        self::TIPO_DONAZIONE    => 'Donazione / Cessione gratuita',
        self::TIPO_FURTO        => 'Furto / Perdita',
    ];

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'tipo',
        'data_dismissione',
        'valore_realizzo',
        'valore_netto_contabile',
        'plusvalenza_minusvalenza',
        'fattura_attiva_id',
        'movimento_contabile_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'data_dismissione'          => 'date',
            'valore_realizzo'           => 'decimal:2',
            'valore_netto_contabile'    => 'decimal:2',
            'plusvalenza_minusvalenza'  => 'decimal:2',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopeVendite(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_VENDITA);
    }

    public function scopePlusvalenze(Builder $query): Builder
    {
        return $query->where('plusvalenza_minusvalenza', '>', 0);
    }

    public function scopeMinusvalenze(Builder $query): Builder
    {
        return $query->where('plusvalenza_minusvalenza', '<', 0);
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

    public function isPlusvalenza(): bool
    {
        return (float) $this->plusvalenza_minusvalenza > 0;
    }

    public function isMinusvalenza(): bool
    {
        return (float) $this->plusvalenza_minusvalenza < 0;
    }

    public function tipoLabel(): string
    {
        return self::TIPI_LABEL[$this->tipo] ?? $this->tipo;
    }
}
