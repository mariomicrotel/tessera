<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incarico extends Model
{
    use BelongsToTenant;

    protected $table = 'incarichi';

    protected $fillable = ['member_id', 'carica_sociale_id'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function caricaSociale(): BelongsTo
    {
        return $this->belongsTo(CaricaSociale::class, 'carica_sociale_id');
    }
}
