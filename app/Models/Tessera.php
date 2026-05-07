<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tessera extends Model
{
    use BelongsToTenant;

    const STATO_BOZZA    = 'bozza';
    const STATO_EMESSA   = 'emessa';
    const STATO_SCADUTA  = 'scaduta';
    const STATO_REVOCATA = 'revocata';

    protected $fillable = [
        'member_id',
        'numero',
        'anno',
        'data_emissione',
        'data_scadenza',
        'stato',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'anno'           => 'integer',
            'data_emissione' => 'date',
            'data_scadenza'  => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function isScaduta(): bool
    {
        return $this->stato === self::STATO_EMESSA
            && $this->data_scadenza !== null
            && $this->data_scadenza->isPast();
    }

    /** Genera il prossimo numero progressivo per anno/tenant. Es: "2026-042" */
    public static function nextNumero(int $anno): string
    {
        $max = static::withoutGlobalScope('tenant')
            ->whereHas('tenant', fn ($q) => $q->where('id', app('current_tenant')->id))
            ->where('anno', $anno)
            ->max(\DB::raw("CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)"));

        return $anno . '-' . str_pad(($max ?? 0) + 1, 3, '0', STR_PAD_LEFT);
    }
}
