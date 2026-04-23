<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Liquidazione IVA periodica (mensile o trimestrale).
 */
class LiquidazioneIva extends Model
{
    use BelongsToTenant;

    public const TIPO_MENSILE     = 'mensile';
    public const TIPO_TRIMESTRALE = 'trimestrale';

    public const STATUS_BOZZA      = 'bozza';
    public const STATUS_DEFINITIVA = 'definitiva';
    public const STATUS_VERSATA    = 'versata';

    protected $table = 'liquidazioni_iva';

    protected $appends = ['periodo_label'];

    protected $fillable = [
        'tenant_id',
        'anno',
        'periodo',
        'tipo_periodo',
        'data_inizio',
        'data_fine',
        'iva_debito',
        'iva_credito',
        'credito_periodo_precedente',
        'saldo_periodo',
        'saldo_finale',
        'acconto_versato',
        'interessi_trimestrali',
        'status',
        'data_chiusura',
        'data_versamento',
        'numero_f24',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'anno'                       => 'integer',
            'periodo'                    => 'integer',
            'data_inizio'                => 'date',
            'data_fine'                  => 'date',
            'data_chiusura'              => 'date',
            'data_versamento'            => 'date',
            'iva_debito'                 => 'decimal:2',
            'iva_credito'                => 'decimal:2',
            'credito_periodo_precedente' => 'decimal:2',
            'saldo_periodo'              => 'decimal:2',
            'saldo_finale'               => 'decimal:2',
            'acconto_versato'            => 'decimal:2',
            'interessi_trimestrali'      => 'decimal:2',
        ];
    }

    public function fatturePassive(): HasMany
    {
        return $this->hasMany(FatturaPassiva::class, 'liquidazione_iva_id');
    }

    public function fattureAttive(): HasMany
    {
        return $this->hasMany(FatturaAttiva::class, 'liquidazione_iva_id');
    }

    public function scopeBozze($query)
    {
        return $query->where('status', self::STATUS_BOZZA);
    }

    public function scopeDefinitive($query)
    {
        return $query->where('status', self::STATUS_DEFINITIVA);
    }

    public function isBozza(): bool
    {
        return $this->status === self::STATUS_BOZZA;
    }

    public function isDefinitiva(): bool
    {
        return $this->status === self::STATUS_DEFINITIVA;
    }

    /**
     * Label leggibile del periodo, es. "Q1 2026" o "Gen 2026".
     */
    public function getPeriodoLabelAttribute(): string
    {
        if ($this->tipo_periodo === self::TIPO_TRIMESTRALE) {
            return "Q{$this->periodo} {$this->anno}";
        }

        $mesi = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];

        return ($mesi[$this->periodo - 1] ?? $this->periodo)." {$this->anno}";
    }
}
