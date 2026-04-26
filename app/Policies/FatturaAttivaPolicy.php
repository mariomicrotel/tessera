<?php

namespace App\Policies;

use App\Models\FatturaAttiva;
use App\Models\User;

/**
 * Policy per Fatture Attive.
 * contabile/admin → pieno accesso; altri ruoli → sola lettura.
 */
class FatturaAttivaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function view(User $user, FatturaAttiva $fattura): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'contabile');
    }

    public function update(User $user, FatturaAttiva $fattura): bool
    {
        if (! $user->hasRole('admin', 'contabile')) {
            return false;
        }
        // Non modificabile se già trasmessa/pagata
        return ! in_array($fattura->stato, ['trasmessa', 'pagata'], true);
    }

    public function delete(User $user, FatturaAttiva $fattura): bool
    {
        return $user->hasRole('admin')
            && ! in_array($fattura->stato, ['trasmessa', 'pagata'], true);
    }

    public function export(User $user): bool
    {
        return $user->hasRole('admin', 'contabile');
    }
}
