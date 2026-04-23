<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

/**
 * Policy per anagrafica soci: segreteria/admin CRUD, socio solo proprio profilo.
 */
class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'segreteria');
    }

    public function view(User $user, Member $member): bool
    {
        if ($user->hasRole('admin', 'segreteria')) {
            return true;
        }
        // Socio può vedere solo se stesso e solo se ha stato attivo
        return $user->member && $user->member->id === $member->id && $user->member->stato === 'attivo';
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'segreteria');
    }

    public function update(User $user, Member $member): bool
    {
        if ($user->hasRole('admin', 'segreteria')) {
            return true;
        }
        return $user->member && $user->member->id === $member->id && $user->member->stato === 'attivo';
    }

    public function delete(User $user, Member $member): bool
    {
        return $user->hasRole('admin', 'segreteria');
    }
}
