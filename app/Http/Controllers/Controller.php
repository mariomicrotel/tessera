<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use ReflectionMethod;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Override di callAction per correggere il mapping dei parametri nelle route multi-tenant.
     *
     * Problema: il route group usa il prefisso app/{tenant}, quindi ogni route ha due parametri:
     *   ['tenant' => 'default', 'spesa' => <SpesaModel>]
     *
     * Laravel risolve le dipendenze del metodo tramite ResolvesRouteDependencies::resolveMethodDependencies
     * che – quando il modello è già in $parameters (binding fatto da SubstituteBindings) – restituisce
     * l'array invariato con le chiavi originali. Poi callAction chiama array_values() e passa 'default'
     * come primo argomento a show(Spesa $spesa), causando il TypeError.
     *
     * Fix: ricostruiamo la lista degli argomenti consultando prima le chiavi nominali (route params)
     * e poi i valori numerici (dipendenze risolte dal container) nell'ordine dei parametri del metodo.
     */
    public function callAction($method, $parameters)
    {
        if (! method_exists($this, $method)) {
            return $this->{$method}(...array_values($parameters));
        }

        $reflection = new ReflectionMethod($this, $method);

        // Separa dipendenze container (chiave numerica) da route params (chiave stringa)
        $numericArgs = [];
        $namedArgs   = [];
        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                $numericArgs[] = $value;
            } else {
                $namedArgs[$key] = $value;
            }
        }

        $numericIdx = 0;
        $args       = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();

            // Prova il nome esatto, poi la versione snake_case (es. expenseRefund → expense_refund)
            $snakeName = Str::snake($name);

            if (array_key_exists($name, $namedArgs)) {
                // Il parametro è stato risolto per nome da SubstituteBindings (es. Spesa $spesa)
                $args[] = $namedArgs[$name];
            } elseif (array_key_exists($snakeName, $namedArgs)) {
                // Route param in snake_case, metodo in camelCase (es. expense_refund → $expenseRefund)
                $args[] = $namedArgs[$snakeName];
            } elseif (isset($numericArgs[$numericIdx])) {
                // Dipendenza iniettata dal container (es. Request $request, Service $service)
                $args[] = $numericArgs[$numericIdx++];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            }
            // se mancante senza default, PHP solleverà l'errore appropriato
        }

        return $this->{$method}(...$args);
    }
}
