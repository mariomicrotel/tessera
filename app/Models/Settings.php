<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Impostazioni applicative (key/value) con scoping per tenant.
 *
 * In contesto multi-tenant, ogni setting è associato al tenant corrente.
 * Se non c'è un tenant nel container (es. comandi CLI), opera in modalità globale.
 */
class Settings extends Model
{
    use BelongsToTenant;

    protected $table = 'settings';

    // PK è ora `id` (bigIncrements), con unique(key, tenant_id)
    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = false;

    protected $keyType = 'int';

    protected $fillable = ['key', 'value', 'tenant_id'];

    /**
     * Restituisce il tenant_id corrente o null.
     */
    private static function currentTenantId(): ?string
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant instanceof Tenant ? $tenant->id : null;
    }

    /**
     * Restituisce il valore per la chiave nel contesto del tenant corrente.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $query = DB::table('settings')->where('key', $key);

        $tenantId = static::currentTenantId();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $row = $query->first();
        if (! $row) {
            return $default;
        }

        if (in_array($key, [
            'quota_annuale',
            'quota_mensile',
            'quota_ingresso',
            'quota_valore_unitario_coop',
            'quota_minima_quote_coop',
            'ristorno_percentuale_max',
            'riserva_legale_percentuale',
            'riserva_indivisibile_percentuale',
            'tasso_interesse_prestito',
        ], true)) {
            return (float) $row->value;
        }

        if ($key === 'site_sections') {
            $decoded = json_decode($row->value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return $row->value;
    }

    /**
     * Imposta il valore per la chiave nel contesto del tenant corrente (upsert).
     */
    public static function set(string $key, mixed $value): void
    {
        $tenantId = static::currentTenantId();

        $where = ['key' => $key];
        if ($tenantId) {
            $where['tenant_id'] = $tenantId;
        }

        DB::table('settings')->updateOrInsert(
            $where,
            ['value' => (string) $value, 'tenant_id' => $tenantId]
        );
    }
}
