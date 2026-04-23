<?php

namespace App\Observers;

use App\Models\Tenant;
use Database\Seeders\CausaliContabiliDiSistemaSeeder;
use Database\Seeders\CodiciIvaDiSistemaSeeder;
use Database\Seeders\PianoContiCooperativaSeeder;

/**
 * Observer sul modello Tenant.
 *
 * Alla creazione di un nuovo tenant di tipo cooperativa, precarica
 * automaticamente:
 *  1. Codici IVA di sistema (10 voci)
 *  2. Piano dei conti cooperativa (~150 conti gerarchici)
 *  3. Causali contabili di sistema (21 causali predefinite)
 *
 * L'ordine è rilevante: prima i codici IVA, poi il piano dei conti
 * (che può referenziarli via `codice_iva_default_id`), poi le causali
 * (che potrebbero referenziare conti del piano).
 */
class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        if ($tenant->isCooperativa()) {
            (new CodiciIvaDiSistemaSeeder)->perTenant($tenant);
            (new PianoContiCooperativaSeeder)->perTenant($tenant);
            (new CausaliContabiliDiSistemaSeeder)->perTenant($tenant);
        }
    }
}
