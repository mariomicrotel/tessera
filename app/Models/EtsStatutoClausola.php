<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtsStatutoClausola extends Model
{
    use BelongsToTenant;

    protected $table = 'ets_statuto_clausole';

    protected $fillable = [
        'statuto_id',
        'numero_articolo',
        'titolo',
        'testo',
        'articolo_cts',
        'compliance_ok',
        'note_compliance',
        'ordine',
    ];

    protected $appends = ['compliance_label'];

    protected function casts(): array
    {
        return [
            'compliance_ok' => 'boolean',
            'ordine'        => 'integer',
        ];
    }

    public function statuto(): BelongsTo
    {
        return $this->belongsTo(EtsStatuto::class, 'statuto_id');
    }

    public function getComplianceLabelAttribute(): string
    {
        return match ($this->compliance_ok) {
            true  => 'Conforme',
            false => 'Non conforme',
            default => 'Non verificato',
        };
    }
}
