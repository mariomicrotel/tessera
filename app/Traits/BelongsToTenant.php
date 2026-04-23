<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait per il multi-tenancy automatico.
 *
 * Aggiunge un global scope che filtra tutte le query per tenant_id
 * e imposta automaticamente tenant_id alla creazione di nuovi record.
 *
 * Usato da tutti i modelli che hanno la colonna tenant_id.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Global scope: filtra automaticamente per tenant corrente
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

            if ($tenant instanceof Tenant) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenant->id);
            }
        });

        // Auto-set tenant_id alla creazione
        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

                if ($tenant instanceof Tenant) {
                    $model->tenant_id = $tenant->id;
                }
            }
        });
    }

    /**
     * Override del route model binding per bypassare il global scope tenant.
     *
     * SubstituteBindings (web middleware group) viene eseguito PRIMA di ResolveTenant
     * (route middleware). Al momento del binding, 'current_tenant' non è ancora registrato
     * nel container, quindi il global scope non può filtrare per tenant_id.
     * Bypassando i global scopes, la query usa solo la chiave primaria; l'autorizzazione
     * al tenant è garantita da ResolveTenant che gira subito dopo.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->newQueryWithoutScopes()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    /**
     * Relazione con il tenant proprietario.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope per query esplicite su un tenant specifico.
     */
    public function scopeForTenant(Builder $query, Tenant|string $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->withoutGlobalScope('tenant')->where($this->getTable().'.tenant_id', $tenantId);
    }
}
