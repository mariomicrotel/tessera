<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nota del consulente per un ente cliente.
 *
 * Visibilità:
 *   - 'interna': solo il consulente che l'ha creata
 *   - 'condivisa': visibile anche agli utenti admin/responsabile del tenant
 */
class ConsultantNote extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'consultant_notes';

    protected $fillable = [
        'tenant_id',
        'consultant_user_id',
        'testo',
        'visibilita',
        'fissata',
    ];

    protected $casts = [
        'fissata' => 'boolean',
    ];

    /* ── Relazioni ─────────────────────────────────────────────────────── */

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_user_id');
    }

    /* ── Scope ─────────────────────────────────────────────────────────── */

    public function scopeCondivise($query)
    {
        return $query->where('visibilita', 'condivisa');
    }

    public function scopeFissate($query)
    {
        return $query->where('fissata', true);
    }

    public function scopePerConsulente($query, int $userId)
    {
        return $query->where('consultant_user_id', $userId);
    }
}
