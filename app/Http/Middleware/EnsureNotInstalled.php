<?php

namespace App\Http\Middleware;

use App\Providers\AppServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consente l'accesso alle route di installazione solo se l'app non è ancora
 * installata (APP_KEY vuoto o chiave placeholder). Durante l'installazione
 * forza la sessione su file perché il database potrebbe non essere ancora configurato.
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('app.key');
        $isPlaceholder = $key === AppServiceProvider::INSTALL_PLACEHOLDER_KEY;
        if (! empty($key) && ! $isPlaceholder) {
            return redirect()->route('saas.landing');
        }

        Config::set('session.driver', 'file');

        // Flush solo su GET e solo se in sessione non ci sono dati dell'installer:
        // altrimenti dopo redirect da storeDatabase() perderemmo install.db e torneremmo al form database.
        if ($request->isMethod('GET') && ! $request->session()->has('install')) {
            $request->session()->flush();
        }

        return $next($request);
    }
}
