<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

/**
 * Policy per il registro cespiti.
 * admin → tutto; contabile → CRUD + ammortamento; segreteria → solo lettura.
 */
class CespitiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'contabile');
    }

    public function update(User $user, Asset $asset): bool
    {
        if (! $user->hasRole('admin', 'contabile')) {
            return false;
        }
        return $asset->stato !== Asset::STATO_DISMESSO;
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin') && $asset->stato !== Asset::STATO_IN_USO;
    }

    public function restore(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin');
    }

    public function registraAmmortamento(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin', 'contabile') && $asset->stato === Asset::STATO_IN_USO;
    }

    public function dismetti(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin') && $asset->stato === Asset::STATO_IN_USO;
    }
}
