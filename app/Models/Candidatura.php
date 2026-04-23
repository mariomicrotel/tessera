<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidatura extends Model
{
    use BelongsToTenant;

    protected $table = 'candidature';

    protected $fillable = ['elezione_id', 'member_id'];

    public function elezione(): BelongsTo
    {
        return $this->belongsTo(Elezione::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function voti(): HasMany
    {
        return $this->hasMany(Voto::class, 'candidatura_id');
    }
}
