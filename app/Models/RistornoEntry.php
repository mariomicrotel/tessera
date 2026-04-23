<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riga di ristorno assegnato a un singolo socio.
 *
 * Relazione: Ristorno (1) ↔ (N) RistornoEntry ↔ (1) Member.
 * Unique constraint: un socio compare una sola volta per ristorno.
 */
class RistornoEntry extends Model
{
    use BelongsToTenant;

    public const STATUS_DELIBERATO = 'deliberato';
    public const STATUS_PAGATO     = 'pagato';

    protected $table = 'ristorno_entries';

    protected $fillable = [
        'ristorno_id',
        'member_id',
        'importo_lordo',
        'aliquota_ritenuta',
        'importo_ritenuta',
        'importo_netto',
        'data_pagamento',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'importo_lordo'      => 'decimal:2',
            'aliquota_ritenuta'  => 'decimal:4',
            'importo_ritenuta'   => 'decimal:2',
            'importo_netto'      => 'decimal:2',
            'data_pagamento'     => 'date',
            'status'             => 'string',
        ];
    }

    // ── Relazioni ─────────────────────────────────────────────────────────────

    public function ristorno(): BelongsTo
    {
        return $this->belongsTo(Ristorno::class, 'ristorno_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    public function isPagato(): bool
    {
        return $this->status === self::STATUS_PAGATO;
    }

    /**
     * Calcola netto e ritenuta a partire da lordo e aliquota.
     * Arrotonda a 2 decimali.
     */
    public static function calcolaFromLordo(float $lordo, float $aliquota): array
    {
        $ritenuta = round($lordo * $aliquota, 2);
        $netto = round($lordo - $ritenuta, 2);

        return [
            'importo_lordo'     => round($lordo, 2),
            'aliquota_ritenuta' => $aliquota,
            'importo_ritenuta'  => $ritenuta,
            'importo_netto'     => $netto,
        ];
    }
}
