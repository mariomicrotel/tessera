<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaasController extends Controller
{
    /**
     * Landing page della piattaforma SaaS.
     */
    public function landing()
    {
        return Inertia::render('Saas/Landing', [
            'plans' => config('saas.plans'),
        ]);
    }

    /**
     * Pagina pricing.
     */
    public function pricing()
    {
        return Inertia::render('Saas/Pricing', [
            'plans' => config('saas.plans'),
        ]);
    }

    /**
     * Selezione tenant (se utente appartiene a più organizzazioni).
     */
    public function selectTenant(Request $request)
    {
        $user = $request->user();

        // Il superadmin va direttamente al pannello admin
        if ($user->is_super_admin) {
            return Inertia::location(route('admin.tenants'));
        }

        $tenants = $user->tenants()
            ->where('is_active', true)
            ->get()
            ->map(fn (Tenant $t) => [
                'id'   => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'plan' => $t->plan,
                'role' => $t->pivot->role,
            ]);

        // Se ha un solo tenant, redirect diretto con Inertia location per preservare contesto
        if ($tenants->count() === 1) {
            $tenantSlug = $tenants->first()['slug'];
            return Inertia::location(route('dashboard', ['tenant' => $tenantSlug]));
        }

        return Inertia::render('Saas/SelectTenant', [
            'tenants'      => $tenants,
            'isSuperAdmin' => false,
            'adminUrl'     => null,
        ]);
    }

    /**
     * Switch a un altro tenant.
     */
    public function switchTenant(Request $request, Tenant $tenant)
    {
        $user = $request->user();

        // Verifica che l'utente appartenga al tenant
        if (! $user->tenants()->where('tenants.id', $tenant->id)->exists() && ! $user->is_super_admin) {
            abort(403);
        }

        // Usa Inertia::location per preservare il contesto Inertia
        return Inertia::location(route('dashboard', ['tenant' => $tenant->slug]));
    }
}
