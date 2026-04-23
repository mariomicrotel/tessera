<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'code', 'unit'];

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class, 'item_id');
    }
}
