<?php

namespace App\Policies;

use App\Models\Tessera;
use App\Models\User;

class TesseraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'segreteria', 'contabile');
    }

    public function view(User $user, Tessera $tessera): bool
    {
        return $user->hasRole('admin', 'segreteria', 'contabile');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'segreteria');
    }

    public function update(User $user, Tessera $tessera): bool
    {
        return $user->hasRole('admin', 'segreteria')
            && $tessera->stato !== Tessera::STATO_REVOCATA;
    }

    public function delete(User $user, Tessera $tessera): bool
    {
        return $user->hasRole('admin')
            && $tessera->stato === Tessera::STATO_BOZZA;
    }
}
