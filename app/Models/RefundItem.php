<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['expense_refund_id', 'description', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function expenseRefund(): BelongsTo
    {
        return $this->belongsTo(ExpenseRefund::class);
    }
}
