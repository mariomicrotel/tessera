<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use BelongsToTenant;

    protected $fillable = ['property_id', 'name', 'code', 'purchase_date', 'value', 'notes'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'value' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
