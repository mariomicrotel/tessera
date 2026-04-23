<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Elezione extends Model
{
    use BelongsToTenant;

    protected $table = 'elezioni';

    public const STATO_BOZZA = 'bozza';
    public const STATO_APERTA = 'aperta';
    public const STATO_CHIUSA = 'chiusa';

    protected $fillable = [
        'organo_id',
        'titolo',
        'data_elezione',
        'stato',
        'permetti_astenuti',
        'invalidata_at',
        'motivazione_invalidazione',
    ];

    protected function casts(): array
    {
        return [
            'data_elezione' => 'date',
            'permetti_astenuti' => 'boolean',
            'invalidata_at' => 'datetime',
        ];
    }

    public function organo(): BelongsTo
    {
        return $this->belongsTo(Organo::class);
    }

    public function candidature(): HasMany
    {
        return $this->hasMany(Candidatura::class, 'elezione_id');
    }

    public function partecipazioni(): HasMany
    {
        return $this->hasMany(PartecipazioneVoto::class, 'elezione_id');
    }

    public function voti(): HasMany
    {
        return $this->hasMany(Voto::class, 'elezione_id');
    }

    public function isAperta(): bool
    {
        if ($this->stato !== self::STATO_APERTA) {
            return false;
        }
        $today = now()->startOfDay();

        return $this->data_elezione->eq($today);
    }

    public function isInvalidata(): bool
    {
        return $this->invalidata_at !== null;
    }
}
