<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Risolutore dello schema Rendiconto per cassa in base al tipo di tenant.
 *
 * - Tenant ETS      → `RendicontoCassaSchema` (Modello D - D.M. 5/3/2020)
 * - Tenant Cooperativa → `RendicontoCassaSchemaCooperativa`
 *
 * Entrambi gli schemi espongono la stessa API statica, quindi i chiamanti
 * possono lavorare uniformemente tramite:
 *
 *   $schema = RendicontoCassaSchemaResolver::class();
 *   $voci   = $schema::getSelectableVoices();
 *
 * Il tenant corrente è quello bindato nel container come `current_tenant`
 * dal middleware multi-tenant. Se non è disponibile (CLI, seeder, ecc.)
 * si ricade sullo schema ETS di default.
 */
class RendicontoCassaSchemaResolver
{
    /**
     * Restituisce il FQCN dello schema da usare per il tenant corrente.
     *
     * @return class-string<RendicontoCassaSchema>|class-string<RendicontoCassaSchemaCooperativa>
     */
    public static function class(): string
    {
        return self::forTenant(self::currentTenant());
    }

    /**
     * Restituisce il FQCN dello schema da usare per un tenant specifico.
     *
     * @return class-string<RendicontoCassaSchema>|class-string<RendicontoCassaSchemaCooperativa>
     */
    public static function forTenant(?Tenant $tenant): string
    {
        if ($tenant && $tenant->isCooperativa()) {
            return RendicontoCassaSchemaCooperativa::class;
        }

        return RendicontoCassaSchema::class;
    }

    /**
     * True se il tenant corrente usa lo schema cooperativa.
     */
    public static function isCooperativa(): bool
    {
        $tenant = self::currentTenant();

        return $tenant !== null && $tenant->isCooperativa();
    }

    protected static function currentTenant(): ?Tenant
    {
        if (! app()->bound('current_tenant')) {
            return null;
        }

        $tenant = app('current_tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
