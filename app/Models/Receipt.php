<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ricevuta collegata a un incasso (quota o donazione) o a un rimborso spese.
 * type: 'liberale' (quote/donazioni), 'fiscale', 'rimborso' (rimborsi spese).
 */
class Receipt extends Model
{
    use BelongsToTenant;

    protected $fillable = ['member_id', 'recipient_name', 'receivable_type', 'receivable_id', 'number', 'issued_at', 'file_path', 'type', 'sent_at'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** Incasso (quota o donazione) o ExpenseRefund */
    public function receivable(): MorphTo
    {
        return $this->morphTo();
    }
}
