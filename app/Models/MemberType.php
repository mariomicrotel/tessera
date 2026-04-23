<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipologia anagrafica: socio, volontario, collaboratore.
 */
class MemberType extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = ['name', 'display_name'];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'member_type_id');
    }
}
