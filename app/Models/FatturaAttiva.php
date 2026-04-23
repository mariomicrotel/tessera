<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fattura attiva (di vendita).
 */
class FatturaAttiva extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const ESIGIBILITA_IMMEDIATA     = 'immediata';
    public const ESIGIBILITA_DIFFERITA     = 'differita';
    public const ESIGIBILITA_SPLIT_PAYMENT = 'split_payment';

    public const STATO_BOZZA         = 'bozza';
    public const STATO_EMESSA        = 'emessa';
    public const STATO_INVIATA_SDI   = 'inviata_sdi';
    public const STATO_ACCETTATA     = 'accettata';
    public const STATO_SCARTATA      = 'scartata';
    public const STATO_ANNULLATA     = 'annullata';

    public const STATO_PAG_DA_INCASSARE          = 'da_incassare';
    public const STATO_PAG_INCASSATA             = 'incassata';
    public const STATO_PAG_PARZIALMENTE_INCASSATA = 'parzialmente_incassata';

    protected $table = 'fatture_attive';

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'sezionale',
        'anno',
        'progressivo',
        'numero_fattura',
        'data_fattura',
        'data_scadenza',
        'imponibile_totale',
        'iva_totale',
        'totale_documento',
        'esigibilita',
        'tipo_documento',
        'stato',
        'stato_pagamento',
        'liquidazione_iva_id',
        'xml_sdi_path',
        'sdi_identificativo',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'data_fattura'      => 'date',
            'data_scadenza'     => 'date',
            'anno'              => 'integer',
            'progressivo'       => 'integer',
            'imponibile_totale' => 'decimal:2',
            'iva_totale'        => 'decimal:2',
            'totale_documento'  => 'decimal:2',
        ];
    }

    public function righe(): HasMany
    {
        return $this->hasMany(RigaFatturaAttiva::class);
    }

    public function liquidazione(): BelongsTo
    {
        return $this->belongsTo(LiquidazioneIva::class, 'liquidazione_iva_id');
    }

    public function scopeEmesse($query)
    {
        return $query->whereIn('stato', [
            self::STATO_EMESSA,
            self::STATO_INVIATA_SDI,
            self::STATO_ACCETTATA,
        ]);
    }

    public function scopeDaIncassare($query)
    {
        return $query->where('stato_pagamento', self::STATO_PAG_DA_INCASSARE);
    }

    public function scopeNelPeriodo($query, string $dataInizio, string $dataFine)
    {
        return $query->whereBetween('data_fattura', [$dataInizio, $dataFine]);
    }

    public function ricalcolaTotali(): void
    {
        $this->imponibile_totale = $this->righe()->sum('imponibile');
        $this->iva_totale        = $this->righe()->sum('iva');
        $this->totale_documento  = $this->righe()->sum('totale');
    }

    public function isReadOnly(): bool
    {
        if (! $this->liquidazione_iva_id) {
            return false;
        }

        return $this->liquidazione && $this->liquidazione->status !== LiquidazioneIva::STATUS_BOZZA;
    }
}
