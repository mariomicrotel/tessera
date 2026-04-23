<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Controller per la dashboard di amministrazione della piattaforma SaaS.
 * Accessibile solo al super admin.
 */
class AdminController extends Controller
{
    public function __construct()
    {
        // Verifica super admin per tutte le azioni
    }

    /**
     * Dashboard principale con statistiche piattaforma.
     */
    public function dashboard()
    {
        $this->authorizeSuperAdmin();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('is_active', true)->count(),
                'total_users' => User::count(),
                'tenants_by_plan' => Tenant::query()
                    ->selectRaw('plan, count(*) as count')
                    ->groupBy('plan')
                    ->pluck('count', 'plan'),
            ],
        ]);
    }

    /**
     * Lista di tutti i tenant.
     */
    public function tenants(Request $request)
    {
        $this->authorizeSuperAdmin();

        $tenants = Tenant::query()
            ->withCount('users')
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Form creazione nuovo tenant.
     */
    public function createTenant()
    {
        $this->authorizeSuperAdmin();

        return Inertia::render('Admin/Tenants/Create', [
            'plans' => array_keys(config('saas.plans')),
        ]);
    }

    /**
     * Salva un nuovo tenant.
     */
    public function storeTenant(Request $request)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'slug'             => ['required', 'string', 'max:100', 'alpha_dash', 'unique:tenants,slug'],
            'plan'             => ['required', 'string', 'in:free,basic,pro,enterprise'],
            'admin_name'       => ['required', 'string', 'max:255'],
            'admin_email'      => ['required', 'email', 'max:255'],
            'admin_password'   => ['required', 'string', 'min:8'],
            'organization_type' => ['required', 'string', 'in:ets,cooperative'],
            'cooperative_type'  => ['nullable', 'string', 'in:lavoro,sociale_a,sociale_b,agricola,comunita,consumo,abitazione,consortile', 'required_if:organization_type,cooperative'],
        ]);

        $tenant = Tenant::create([
            'name'              => $validated['name'],
            'slug'              => $validated['slug'],
            'plan'              => $validated['plan'],
            'is_active'         => true,
            'organization_type' => $validated['organization_type'],
            'cooperative_type'  => $validated['organization_type'] === 'cooperative'
                ? $validated['cooperative_type']
                : null,
        ]);

        // Seed dati iniziali del tenant
        Artisan::call('tenant:seed', [
            '--tenant' => $tenant->slug,
            '--name' => $validated['admin_name'],
            '--email' => $validated['admin_email'],
            '--password' => $validated['admin_password'],
        ]);

        return redirect()->route('admin.tenants')
            ->with('flash', ['type' => 'success', 'message' => "Tenant \"{$tenant->name}\" creato con successo."]);
    }

    /**
     * Esegue il seed di un tenant esistente.
     */
    public function seedTenant(Tenant $tenant)
    {
        $this->authorizeSuperAdmin();

        Artisan::call('tenant:seed', [
            '--tenant' => $tenant->slug,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Seed completato.']);
    }

    /**
     * Dettaglio di un tenant.
     */
    public function showTenant(Tenant $tenant)
    {
        $this->authorizeSuperAdmin();

        $tenant->loadCount('users');

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant,
            'users' => $tenant->users()->get(),
        ]);
    }

    /**
     * Aggiorna un tenant (piano, stato, ecc.).
     */
    public function updateTenant(Request $request, Tenant $tenant)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'name'              => ['sometimes', 'string', 'max:255'],
            'plan'              => ['sometimes', 'string', 'in:free,basic,pro,enterprise'],
            'plan_expires_at'   => ['sometimes', 'nullable', 'date'],
            'is_active'         => ['sometimes', 'boolean'],
            'organization_type' => ['sometimes', 'string', 'in:ets,cooperative'],
            'cooperative_type'  => ['sometimes', 'nullable', 'string', 'in:lavoro,sociale_a,sociale_b,agricola,comunita,consumo,abitazione,consortile'],
        ]);

        // Se si passa a ETS, azzera il tipo cooperativa
        if (isset($validated['organization_type']) && $validated['organization_type'] === 'ets') {
            $validated['cooperative_type'] = null;
        }

        $tenant->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Tenant aggiornato.']);
    }

    /**
     * Attiva/disattiva un tenant.
     */
    public function toggleTenantActive(Tenant $tenant)
    {
        $this->authorizeSuperAdmin();

        $tenant->update(['is_active' => ! $tenant->is_active]);

        $status = $tenant->is_active ? 'attivato' : 'disattivato';

        return back()->with('flash', ['type' => 'success', 'message' => "Tenant {$status}."]);
    }

    /**
     * Elimina un tenant (soft delete).
     */
    public function destroyTenant(Tenant $tenant)
    {
        $this->authorizeSuperAdmin();

        $tenant->delete();

        return redirect()->route('admin.tenants')
            ->with('flash', ['type' => 'success', 'message' => 'Tenant eliminato.']);
    }

    /**
     * Verifica che l'utente sia super admin.
     */
    private function authorizeSuperAdmin(): void
    {
        if (! auth()->user()?->is_super_admin) {
            abort(403, 'Accesso riservato al super admin della piattaforma.');
        }
    }
}
