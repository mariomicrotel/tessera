<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtsComplianceCheckResult extends Model
{
    protected $table = 'ets_compliance_check_results';

    protected $fillable = [
        'compliance_check_id',
        'rule_id',
        'esito',
        'valore_rilevato',
        'messaggio',
        'suggerimento',
    ];

    protected $appends = ['esito_label', 'esito_color'];

    public function check(): BelongsTo
    {
        return $this->belongsTo(EtsComplianceCheck::class, 'compliance_check_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EtsComplianceRule::class, 'rule_id');
    }

    public function getEsitoLabelAttribute(): string
    {
        return match ($this->esito) {
            'ok'              => 'Conforme',
            'avviso'          => 'Avviso',
            'errore'          => 'Non conforme',
            'non_applicabile' => 'N/A',
            default           => $this->esito,
        };
    }

    public function getEsitoColorAttribute(): string
    {
        return match ($this->esito) {
            'ok'              => 'green',
            'avviso'          => 'yellow',
            'errore'          => 'red',
            default           => 'gray',
        };
    }
}
