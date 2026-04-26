<?php

namespace App\Policies;

use App\Models\Incasso;
use App\Models\User;

/**
 * Policy per Incassi.
 * admin/contabile/segreteria → lettura; admin/contabile → scrittura.
 */
class IncassoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function view(User $user, Incasso $incasso): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function update(User $user, Incasso $incasso): bool
    {
        return $user->hasRole('admin', 'contabile');
    }

    public function delete(User $user, Incasso $incasso): bool
    {
        // Solo admin può eliminare; i movimenti contabili collegati devono
        // essere stornati separatamente
        return $user->hasRole('admin');
    }

    public function export(User $user): bool
    {
        return $user->hasRole('admin', 'contabile');
    }
}
