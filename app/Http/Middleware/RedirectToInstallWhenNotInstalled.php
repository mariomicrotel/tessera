<?php

namespace App\Http\Middleware;

use App\Providers\AppServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se l'app non è installata (chiave vuota o placeholder), reindirizza la home (/)
 * a /install così non si accede al sito pubblico (che richiede DB) prima dell'installazione.
 */
class RedirectToInstallWhenNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('app.key');
        $isPlaceholder = $key === AppServiceProvider::INSTALL_PLACEHOLDER_KEY;
        if (empty($key) || $isPlaceholder) {
            return redirect()->route('install.show');
        }

        return $next($request);
    }
}
