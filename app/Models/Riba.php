<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Riba extends Model
{
    use HasUuids, BelongsToTenant, SoftDeletes;

    protected $table = 'riba';

    public const STATO_DA_INVIARE = 'da_inviare';
    public const STATO_INVIATA    = 'inviata';
    public const STATO_ACCETTATA  = 'accettata';
    public const STATO_PAGATA     = 'pagata';
    public const STATO_INSOLUTA   = 'insoluta';
    public const STATO_ANNULLATA  = 'annullata';

    public const STATI_LABEL = [
        self::STATO_DA_INVIARE => 'Da inviare',
        self::STATO_INVIATA    => 'Inviata in banca',
        self::STATO_ACCETTATA  => 'Accettata',
        self::STATO_PAGATA     => 'Pagata',
        self::STATO_INSOLUTA   => 'Insoluta',
        self::STATO_ANNULLATA  => 'Annullata',
    ];

    protected $fillable = [
        'tenant_id',
        'fattura_attiva_id',
        'numero_riba',
        'nome_debitore',
        'cf_piva_debitore',
        'iban_debitore',
        'importo',
        'data_scadenza',
        'data_emissione',
        'data_invio_banca',
        'stato',
        'codice_sia',
        'banca_presentatrice',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'importo'          => 'decimal:2',
            'data_scadenza'    => 'date',
            'data_emissione'   => 'date',
            'data_invio_banca' => 'date',
        ];
    }

    public function fatturaAttiva(): BelongsTo
    {
        return $this->belongsTo(FatturaAttiva::class);
    }

    public function scopeDaInviare($query)
    {
        return $query->where('stato', self::STATO_DA_INVIARE);
    }

    public function scopeInScadenza($query, int $giorni = 7)
    {
        return $query->whereIn('stato', [self::STATO_DA_INVIARE, self::STATO_INVIATA, self::STATO_ACCETTATA])
                     ->whereBetween('data_scadenza', [now()->toDateString(), now()->addDays($giorni)->toDateString()]);
    }
}
