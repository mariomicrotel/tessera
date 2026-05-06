<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtsComplianceRule extends Model
{
    protected $table = 'ets_compliance_rules';

    protected $fillable = [
        'codice',
        'titolo',
        'descrizione',
        'categoria',
        'forme_applicabili',
        'severita',
        'check_type',
        'check_config',
        'attivo',
        'ordine',
    ];

    protected function casts(): array
    {
        return [
            'forme_applicabili' => 'array',
            'check_config'      => 'array',
            'attivo'            => 'boolean',
            'ordine'            => 'integer',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(EtsComplianceCheckResult::class, 'rule_id');
    }

    public function scopeAttive($query)
    {
        return $query->where('attivo', true)->orderBy('ordine');
    }
}
