<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ente extends Model
{
    protected $table = 'enti';

    protected $fillable = ['nome', 'note'];

    public function organi(): HasMany
    {
        return $this->hasMany(Organo::class, 'ente_id');
    }
}
