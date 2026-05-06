<?php

namespace App\Policies;

use App\Models\EtsAttoCostituivo;
use App\Models\EtsComplianceCheck;
use App\Models\EtsStatuto;
use App\Models\User;

/**
 * Autorizzazione modulo Compliance ETS.
 *
 * admin     → accesso completo
 * segreteria → lettura + modifica bozze
 * contabile  → sola lettura
 * altri      → negato
 */
class EtsCompliancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('segreteria') || $user->hasRole('contabile');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('segreteria');
    }

    public function update(User $user, EtsStatuto|EtsAttoCostituivo $document): bool
    {
        if ($user->hasRole('admin')) return true;
        if ($user->hasRole('segreteria')) {
            // La segreteria può modificare solo bozze
            return $document->stato === 'bozza';
        }
        return false;
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function runCheck(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('segreteria');
    }

    public function manageRules(User $user): bool
    {
        return $user->is_super_admin;
    }
}
