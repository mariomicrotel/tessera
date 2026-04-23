<?php

namespace App\Exceptions;

use Exception;

/**
 * Eccezione lanciata quando si tenta di chiudere una liquidazione IVA
 * che è già stata chiusa (status != bozza).
 */
class IvaAlreadyClosedException extends Exception
{
    public function __construct(int $anno, int $periodo, string $tipoPeriodo)
    {
        $periodoLabel = $tipoPeriodo === 'trimestrale' ? "Q{$periodo}" : "M{$periodo}";
        parent::__construct("Liquidazione IVA {$periodoLabel} {$anno} è già stata chiusa.");
    }
}
