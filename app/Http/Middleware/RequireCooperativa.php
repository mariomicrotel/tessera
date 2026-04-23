<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impedisce l'accesso a route riservate alle cooperative.
 * Deve essere applicato dopo il middleware 'tenant' che risolve current_tenant.
 */
class RequireCooperativa
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if (! $tenant?->isCooperativa()) {
            abort(403, 'Questa sezione è disponibile solo per le cooperative.');
        }

        return $next($request);
    }
}
