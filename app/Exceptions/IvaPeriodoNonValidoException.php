<?php

namespace App\Exceptions;

use Exception;

/**
 * Eccezione per parametri di periodo IVA non validi
 * (anno fuori range, periodo fuori range per tipo, tipoPeriodo sconosciuto).
 */
class IvaPeriodoNonValidoException extends Exception {}
