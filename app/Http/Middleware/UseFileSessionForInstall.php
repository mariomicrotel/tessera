<?php

namespace App\Http\Middleware;

use App\Providers\AppServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per le richieste a /install, imposta sessione e cache su driver che non usano
 * il database, così middleware e app non toccano il DB prima che l'installer
 * lo crei (sessione=file, cache=array; ThrottleRequests usa la cache).
 */
class UseFileSessionForInstall
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! str_starts_with($request->path(), 'install')) {
            return $next($request);
        }

        $key = config('app.key');
        $isPlaceholder = $key === AppServiceProvider::INSTALL_PLACEHOLDER_KEY;
        if (! empty($key) && ! $isPlaceholder) {
            return $next($request);
        }

        Config::set('session.driver', 'file');
        Config::set('cache.default', 'array');

        return $next($request);
    }
}
