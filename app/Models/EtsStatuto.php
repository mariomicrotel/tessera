<?php

namespace App\Models;

use App\Traits\AuditsChanges;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EtsStatuto extends Model
{
    use BelongsToTenant, AuditsChanges, HasUuids, SoftDeletes;

    public const STATO_BOZZA     = 'bozza';
    public const STATO_APPROVATO = 'approvato';
    public const STATO_ARCHIVIATO = 'archiviato';

    protected $table = 'ets_statuti';

    protected $fillable = [
        'versione',
        'titolo',
        'stato',
        'data_approvazione',
        'data_deposito',
        'note',
    ];

    protected $appends = ['stato_label', 'is_approvato'];

    protected function casts(): array
    {
        return [
            'data_approvazione' => 'date',
            'data_deposito'     => 'date',
        ];
    }

    public function clausole(): HasMany
    {
        return $this->hasMany(EtsStatutoClausola::class, 'statuto_id')->orderBy('ordine');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function getStatoLabelAttribute(): string
    {
        return match ($this->stato) {
            self::STATO_APPROVATO  => 'Approvato',
            self::STATO_ARCHIVIATO => 'Archiviato',
            default                => 'Bozza',
        };
    }

    public function getIsApprovatoAttribute(): bool
    {
        return $this->stato === self::STATO_APPROVATO;
    }
}
