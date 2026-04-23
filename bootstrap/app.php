<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Redirect utenti autenticati che visitano pagine guest (login, register)
        // verso la selezione tenant anziché route('dashboard') che richiede {tenant}
        $middleware->redirectUsersTo('/select-tenant');

        $middleware->validateCsrfTokens(except: [
            'install',
            'install/*',
        ]);
        $middleware->web(prepend: [
            \App\Http\Middleware\UseFileSessionForInstall::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role'              => \App\Http\Middleware\EnsureUserHasRole::class,
            'install'           => \App\Http\Middleware\EnsureNotInstalled::class,
            'redirectToInstall' => \App\Http\Middleware\RedirectToInstallWhenNotInstalled::class,
            'tenant'            => \App\Http\Middleware\ResolveTenant::class,
            'cooperative'       => \App\Http\Middleware\RequireCooperativa::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, $e, $request) {
            if ($e instanceof HttpException && $e->getStatusCode() === 419) {
                return redirect()->back()
                    ->with('flash', ['type' => 'warning', 'message' => 'La sessione è scaduta. Ricarica la pagina e riprova ad accedere.']);
            }
            return $response;
        });
    })->create();
