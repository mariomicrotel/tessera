<?php

namespace App\Policies;

use App\Models\FatturaPassiva;
use App\Models\User;

/**
 * Policy per Fatture Passive.
 * contabile/admin → pieno accesso; segreteria → sola lettura.
 * Una fattura agganciata a una liquidazione definitiva è read-only
 * (aggiornamento/cancellazione bloccati).
 */
class FatturaPassivaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function view(User $user, FatturaPassiva $fattura): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'contabile');
    }

    public function update(User $user, FatturaPassiva $fattura): bool
    {
        if (! $user->hasRole('admin', 'contabile')) {
            return false;
        }

        // Bloccata se agganciata a liquidazione definitiva o già annullata
        if ($fattura->stato === FatturaPassiva::STATO_ANNULLATA) {
            return false;
        }

        return $fattura->liquidazione_iva_id === null;
    }

    public function delete(User $user, FatturaPassiva $fattura): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        return $fattura->liquidazione_iva_id === null
            && $fattura->stato !== FatturaPassiva::STATO_PAGATA;
    }

    public function export(User $user): bool
    {
        return $user->hasRole('admin', 'contabile');
    }
}
