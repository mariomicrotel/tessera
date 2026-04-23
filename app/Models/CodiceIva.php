<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Codice IVA configurabile per tenant.
 *
 * I codici "di sistema" (di_sistema=true) vengono precaricati dal seeder
 * e non sono eliminabili da UI. I codici custom sono creati dall'admin.
 */
class CodiceIva extends Model
{
    use BelongsToTenant;

    public const TIPO_NORMALE        = 'normale';
    public const TIPO_ESENTE          = 'esente';
    public const TIPO_FUORI_CAMPO     = 'fuori_campo';
    public const TIPO_NON_IMPONIBILE  = 'non_imponibile';
    public const TIPO_REVERSE_CHARGE  = 'reverse_charge';
    public const TIPO_SPLIT_PAYMENT   = 'split_payment';

    protected $table = 'codici_iva';

    protected $fillable = [
        'tenant_id',
        'codice',
        'descrizione',
        'percentuale',
        'tipo',
        'natura_sdi',
        'indetraibile_percentuale',
        'attivo',
        'di_sistema',
    ];

    protected function casts(): array
    {
        return [
            'percentuale'              => 'decimal:2',
            'indetraibile_percentuale' => 'decimal:2',
            'attivo'                   => 'boolean',
            'di_sistema'               => 'boolean',
        ];
    }

    public function scopeAttivi($query)
    {
        return $query->where('attivo', true);
    }

    public function scopeDiSistema($query)
    {
        return $query->where('di_sistema', true);
    }

    public function righeFatturePassive(): HasMany
    {
        return $this->hasMany(RigaFatturaPassiva::class);
    }

    public function righeFattureAttive(): HasMany
    {
        return $this->hasMany(RigaFatturaAttiva::class);
    }

    /**
     * True se questo codice concorre al calcolo dell'IVA detraibile/dovuta.
     */
    public function concorreAllaLiquidazione(): bool
    {
        return ! in_array($this->tipo, [self::TIPO_FUORI_CAMPO], true);
    }
}
