<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assegnazione di un consulente a un tenant cliente.
 *
 * NOTA: questo modello NON usa BelongsToTenant perché è cross-tenant:
 * ogni riga lega un utente-consulente a un tenant, e deve essere
 * interrogabile indipendentemente dal tenant corrente nel container.
 */
class ConsultantAssignment extends Model
{
    protected $table = 'consultant_assignments';

    protected $fillable = [
        'consultant_user_id',
        'tenant_id',
        'assigned_by_user_id',
        'ruolo',
        'active',
        'started_at',
        'note',
    ];

    protected $casts = [
        'active'     => 'boolean',
        'started_at' => 'date',
    ];

    /* ── Relazioni ─────────────────────────────────────────────────────── */

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /* ── Scope ─────────────────────────────────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Tenants assegnati a un consulente (scope su user_id).
     */
    public function scopeForConsultant($query, int|User $consultant)
    {
        $id = $consultant instanceof User ? $consultant->id : $consultant;
        return $query->where('consultant_user_id', $id);
    }

    /**
     * Consulenti assegnati a un tenant (scope su tenant_id).
     */
    public function scopeForTenantId($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
