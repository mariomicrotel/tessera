<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use BelongsToTenant;

    protected $table = 'templates';

    protected $fillable = ['nome', 'categoria', 'tipo_verbale', 'contenuto'];

    protected $appends = ['tipo', 'tipo_label'];

    /** Compatibilità con frontend verbali che filtra per t.tipo */
    public function getTipoAttribute(): ?string
    {
        return $this->tipo_verbale;
    }

    public function getTipoLabelAttribute(): ?string
    {
        return match ($this->tipo_verbale ?? '') {
            'consiglio_direttivo' => 'Consiglio direttivo',
            'assemblea' => 'Assemblea',
            default => null,
        };
    }
}
