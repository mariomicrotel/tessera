<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Voto anonimo: nessun member_id, solo elezione_id + candidatura_id.
 */
class Voto extends Model
{
    use BelongsToTenant;

    protected $table = 'voti';

    protected $fillable = ['elezione_id', 'candidatura_id'];

    public function elezione(): BelongsTo
    {
        return $this->belongsTo(Elezione::class);
    }

    public function candidatura(): BelongsTo
    {
        return $this->belongsTo(Candidatura::class);
    }
}
