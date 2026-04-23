<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware per risolvere il tenant corrente dalla route.
 *
 * Verifica che:
 * 1. Il tenant esista e sia attivo
 * 2. L'utente autenticato appartenga al tenant
 * 3. Il piano del tenant non sia scaduto
 *
 * Imposta il tenant come binding singleton 'current_tenant' nel container.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantSlug = $request->route('tenant');

        if (! $tenantSlug) {
            abort(404, 'Tenant non specificato.');
        }

        // Risolvi il tenant dal parametro route
        $tenant = $tenantSlug instanceof Tenant
            ? $tenantSlug
            : Tenant::where('slug', $tenantSlug)->first();

        if (! $tenant) {
            abort(404, 'Organizzazione non trovata.');
        }

        if (! $tenant->is_active) {
            abort(403, 'Questa organizzazione è stata disattivata.');
        }

        if (! $tenant->isPlanActive()) {
            abort(403, 'Il piano di questa organizzazione è scaduto. Contatta il supporto.');
        }

        // Verifica che l'utente appartenga al tenant
        $user = $request->user();
        if ($user) {
            $membership = $user->tenants()->where('tenants.id', $tenant->id)->first();

            if (! $membership && ! $user->is_super_admin) {
                abort(403, 'Non hai accesso a questa organizzazione.');
            }

            // Imposta il ruolo dell'utente nel contesto del tenant
            if ($membership) {
                $request->attributes->set('tenant_role', $membership->pivot->role);
            }
        }

        // Registra il tenant corrente nel container
        app()->instance('current_tenant', $tenant);

        // Imposta il default URL per il parametro {tenant} così route() lo inietta automaticamente
        URL::defaults(['tenant' => $tenant->slug]);

        // Condividi il tenant con Inertia
        if (class_exists(\Inertia\Inertia::class)) {
            \Inertia\Inertia::share('currentTenant', fn () => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan' => $tenant->plan,
            ]);
        }

        return $next($request);
    }
}
