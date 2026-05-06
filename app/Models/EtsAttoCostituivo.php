<?php

namespace App\Models;

use App\Traits\AuditsChanges;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EtsAttoCostituivo extends Model
{
    use BelongsToTenant, AuditsChanges, HasUuids, SoftDeletes;

    public const STATO_BOZZA     = 'bozza';
    public const STATO_REGISTRATO = 'registrato';

    protected $table = 'ets_atti_costitutivi';

    protected $fillable = [
        'notaio',
        'repertorio',
        'data_atto',
        'data_registrazione_ae',
        'ufficio_registro',
        'numero_registro',
        'stato',
        'note',
    ];

    protected $appends = ['stato_label', 'is_registrato'];

    protected function casts(): array
    {
        return [
            'data_atto'              => 'date',
            'data_registrazione_ae'  => 'date',
        ];
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function getStatoLabelAttribute(): string
    {
        return $this->stato === self::STATO_REGISTRATO ? 'Registrato' : 'Bozza';
    }

    public function getIsRegistratoAttribute(): bool
    {
        return $this->stato === self::STATO_REGISTRATO;
    }
}
