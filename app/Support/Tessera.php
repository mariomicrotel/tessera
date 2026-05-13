<?php

namespace App\Support;

/**
 * Helper statico per leggere lo stato dei moduli (feature flags) di Tessera.
 *
 * Uso:
 *   if (Tessera::module('vat_registers')) { ... }
 *   Tessera::enabledModules() // ritorna array di moduli attivi
 *
 * I flag sono definiti in config/tessera.php e possono essere sovrascritti
 * via variabili d'ambiente TESSERA_MODULE_<NAME>.
 */
class Tessera
{
    /**
     * Indica se un modulo è abilitato.
     */
    public static function module(string $name): bool
    {
        return (bool) config("tessera.modules.{$name}", false);
    }

    /**
     * Ritorna l'elenco dei moduli abilitati (chiavi).
     *
     * @return array<int, string>
     */
    public static function enabledModules(): array
    {
        $modules = config('tessera.modules', []);

        return array_keys(array_filter($modules, fn ($enabled) => (bool) $enabled));
    }

    /**
     * Ritorna l'intero array dei moduli (chiave => bool) per condivisione con il frontend.
     *
     * @return array<string, bool>
     */
    public static function allModules(): array
    {
        return array_map(fn ($v) => (bool) $v, config('tessera.modules', []));
    }

    /**
     * Ritorna la label rinominata per una chiave, o la chiave stessa se assente.
     */
    public static function label(string $key): string
    {
        return config("tessera.labels.{$key}", $key);
    }
}
