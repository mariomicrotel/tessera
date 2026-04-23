<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica che l'utente abbia almeno uno dei ruoli indicati (es. admin, segreteria).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if ($request->user()->hasRole(...$roles)) {
            return $next($request);
        }

        abort(403, 'Non autorizzato per questa azione.');
    }
}
