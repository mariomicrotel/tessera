<?php

namespace App\Policies;

use App\Models\CooperativeShare;
use App\Models\User;

/**
 * Policy per Quote Capitale Sociale (Cooperative).
 * Solo admin può eliminare; contabile/admin può creare/modificare.
 */
class CooperativeSharePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function view(User $user, CooperativeShare $share): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'contabile');
    }

    public function update(User $user, CooperativeShare $share): bool
    {
        if (! $user->hasRole('admin', 'contabile')) {
            return false;
        }

        // Quote già riscattate non sono modificabili
        return $share->stato !== 'riscattata';
    }

    public function delete(User $user, CooperativeShare $share): bool
    {
        return $user->hasRole('admin')
            && $share->stato === 'bozza';
    }

    public function versa(User $user, CooperativeShare $share): bool
    {
        return $user->hasRole('admin', 'contabile')
            && in_array($share->stato, ['sottoscritta', 'parzialmente_versata'], true);
    }

    public function riscatta(User $user, CooperativeShare $share): bool
    {
        return $user->hasRole('admin', 'contabile')
            && $share->stato === 'versata';
    }
}
