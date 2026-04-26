<?php

namespace App\Policies;

use App\Models\MovimentoContabile;
use App\Models\User;

/**
 * Policy per Movimenti Contabili (Prima Nota).
 * Solo contabile/admin può scrivere; segreteria → sola lettura.
 * Movimenti definitivi non sono modificabili (solo stornabili).
 */
class MovimentoContabilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function view(User $user, MovimentoContabile $movimento): bool
    {
        return $user->hasRole('admin', 'contabile', 'segreteria');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin', 'contabile');
    }

    public function update(User $user, MovimentoContabile $movimento): bool
    {
        if (! $user->hasRole('admin', 'contabile')) {
            return false;
        }

        // Solo i movimenti in bozza possono essere modificati
        return $movimento->stato === MovimentoContabile::STATO_BOZZA;
    }

    public function delete(User $user, MovimentoContabile $movimento): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        return $movimento->stato === MovimentoContabile::STATO_BOZZA;
    }

    public function conferma(User $user, MovimentoContabile $movimento): bool
    {
        return $user->hasRole('admin', 'contabile')
            && $movimento->stato === MovimentoContabile::STATO_BOZZA;
    }

    public function storna(User $user, MovimentoContabile $movimento): bool
    {
        return $user->hasRole('admin', 'contabile')
            && $movimento->stato === MovimentoContabile::STATO_DEFINITIVO;
    }

    public function export(User $user): bool
    {
        return $user->hasRole('admin', 'contabile');
    }
}
