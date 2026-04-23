<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use BelongsToTenant;

    public const TIPO_LEGALE = 'legale';
    public const TIPO_OPERATIVA = 'operativa';

    protected $fillable = ['name', 'address', 'tipo'];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
