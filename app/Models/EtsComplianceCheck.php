<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtsComplianceCheck extends Model
{
    use BelongsToTenant, HasUuids;

    public const STATO_COMPLETATO  = 'completato';
    public const STATO_CON_ERRORI  = 'con_errori';

    protected $table = 'ets_compliance_checks';

    protected $fillable = [
        'run_by_user_id',
        'run_at',
        'stato',
        'totale',
        'n_ok',
        'n_avviso',
        'n_errore',
        'n_na',
        'note',
    ];

    protected $appends = ['punteggio_percentuale', 'stato_label'];

    protected function casts(): array
    {
        return [
            'run_at'   => 'datetime',
            'totale'   => 'integer',
            'n_ok'     => 'integer',
            'n_avviso' => 'integer',
            'n_errore' => 'integer',
            'n_na'     => 'integer',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(EtsComplianceCheckResult::class, 'compliance_check_id');
    }

    public function runBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by_user_id');
    }

    public function getPunteggioPercentualeAttribute(): int
    {
        $applicabili = $this->n_ok + $this->n_avviso + $this->n_errore;
        if ($applicabili === 0) return 0;
        return (int) round(($this->n_ok / $applicabili) * 100);
    }

    public function getStatoLabelAttribute(): string
    {
        return $this->stato === self::STATO_CON_ERRORI ? 'Con errori' : 'Completato';
    }
}
