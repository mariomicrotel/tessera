<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Iscrizione periodica di un socio (anno, date inizio/fine, stato).
 */
class Subscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'member_id',
        'year',
        'started_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ends_at' => 'date',
            'year' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
