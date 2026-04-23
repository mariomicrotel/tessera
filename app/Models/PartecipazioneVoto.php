<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartecipazioneVoto extends Model
{
    use BelongsToTenant;

    protected $table = 'partecipazioni_voto';

    protected $fillable = ['member_id', 'elezione_id'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function elezione(): BelongsTo
    {
        return $this->belongsTo(Elezione::class);
    }
}
