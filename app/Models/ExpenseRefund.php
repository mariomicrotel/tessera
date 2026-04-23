<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Rimborso spese: beneficiario (member), voci (refund_items), ricevuta, allegati, stato.
 */
class ExpenseRefund extends Model
{
    use BelongsToTenant;

    protected $fillable = ['member_id', 'refund_date', 'status', 'total', 'receipt_id'];

    protected function casts(): array
    {
        return [
            'refund_date' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function refundItems(): HasMany
    {
        return $this->hasMany(RefundItem::class, 'expense_refund_id');
    }

    public function receipt(): MorphOne
    {
        return $this->morphOne(Receipt::class, 'receivable');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function primaNotaEntries(): MorphMany
    {
        return $this->morphMany(PrimaNotaEntry::class, 'entryable');
    }

    /** Ricalcola total da refundItems. */
    public function recalculateTotal(): void
    {
        $this->update(['total' => $this->refundItems()->sum('amount')]);
    }
}
