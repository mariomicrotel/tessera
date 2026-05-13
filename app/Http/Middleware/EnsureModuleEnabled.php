<?php

namespace App\Http\Middleware;

use App\Support\Tessera;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware che blocca l'accesso a route appartenenti a moduli disabilitati.
 *
 * Registrato come alias "module" in bootstrap/app.php.
 *
 * Uso:
 *   Route::middleware('module:vat_registers')->group(fn () => ...);
 *   Route::get('/foo', ...)->middleware('module:invoicing');
 *
 * Se il modulo è disabilitato (config/tessera.php), risponde 404 per non
 * rivelare l'esistenza della funzionalità.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! Tessera::module($module)) {
            abort(404);
        }

        return $next($request);
    }
}
