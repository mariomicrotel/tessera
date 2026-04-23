<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'address', 'notes'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
