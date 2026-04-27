<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registro audit: ogni modifica a entità rilevanti del sistema.
 * Non usa BelongsToTenant perché deve essere leggibile dall'admin
 * anche tra tenant diversi (per super-admin future).
 */
class AuditLog extends Model
{
    public const ACTION_CREATED  = 'created';
    public const ACTION_UPDATED  = 'updated';
    public const ACTION_DELETED  = 'deleted';
    public const ACTION_RESTORED = 'restored';

    protected $table = 'audit_logs';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'user_email',
        'entity_type',
        'entity_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    // ── Relazioni ─────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForEntity(Builder $query, string $entityType, ?int $entityId = null): Builder
    {
        $query->where('entity_type', $entityType);
        if ($entityId !== null) {
            $query->where('entity_id', $entityId);
        }
        return $query;
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Nome breve del tipo di entità (senza namespace).
     */
    public function entityLabel(): string
    {
        return class_basename($this->entity_type);
    }

    /**
     * Restituisce le differenze tra old e new values come array diff.
     */
    public function diff(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        $changed = [];
        foreach ($new as $key => $newVal) {
            $oldVal = $old[$key] ?? null;
            if ($oldVal !== $newVal) {
                $changed[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        return $changed;
    }

    /**
     * Crea un log di audit (factory method).
     */
    public static function record(
        string  $action,
        Model   $entity,
        array   $oldValues = [],
        array   $newValues = [],
        string|null $tenantId  = null,
    ): static {
        $user   = auth()->user();
        $tenant = $tenantId ?? (app()->bound('current_tenant') ? app('current_tenant')->id : null);

        return static::create([
            'tenant_id'   => $tenant,
            'user_id'     => $user?->id,
            'user_email'  => $user?->email,
            'entity_type' => get_class($entity),
            'entity_id'   => $entity->getKey(),
            'action'      => $action,
            'old_values'  => empty($oldValues) ? null : $oldValues,
            'new_values'  => empty($newValues) ? null : $newValues,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}
