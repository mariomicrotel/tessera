<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ruolo assegnabile agli utenti: admin, contabile, segreteria, socio.
 */
class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'display_name'];

    /**
     * Utenti che hanno questo ruolo.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    /**
     * Verifica se il ruolo è admin.
     */
    public function isAdmin(): bool
    {
        return $this->name === 'admin';
    }
}
