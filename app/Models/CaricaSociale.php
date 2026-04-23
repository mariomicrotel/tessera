<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaricaSociale extends Model
{
    use BelongsToTenant;

    protected $table = 'cariche_sociali';

    protected $fillable = ['organo_id', 'nome', 'ordine', 'multiplo'];

    protected function casts(): array
    {
        return [
            'ordine' => 'integer',
            'multiplo' => 'boolean',
        ];
    }

    public function organo(): BelongsTo
    {
        return $this->belongsTo(Organo::class);
    }

    public function incarichi(): HasMany
    {
        return $this->hasMany(Incarico::class, 'carica_sociale_id');
    }
}
