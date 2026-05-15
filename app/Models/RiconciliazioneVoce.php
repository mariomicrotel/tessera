<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiconciliazioneVoce extends Model
{
    use HasUuids, BelongsToTenant;

    protected $table = 'riconciliazione_voci';

    protected $fillable = [
        'tenant_id',
        'movimento_bancario_id',
        'entita_tipo',
        'entita_id',
        'nota',
    ];

    public function movimento(): BelongsTo
    {
        return $this->belongsTo(MovimentoBancario::class, 'movimento_bancario_id');
    }
}
