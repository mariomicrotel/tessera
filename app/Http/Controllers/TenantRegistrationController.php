<?php

namespace App\Http\Controllers;

use App\Models\Conto;
use App\Models\Settings;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\MemberTypeSeeder;
use Database\Seeders\OrganiHardcodedSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class TenantRegistrationController extends Controller
{
    /**
     * Mostra il form di registrazione nuovo tenant.
     */
    public function showRegistrationForm()
    {
        return Inertia::render('Saas/Register', [
            'plans' => config('saas.plans'),
            'trialDays' => config('saas.trial_days'),
        ]);
    }

    /**
     * Registra un nuovo tenant + utente admin.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'organization_name'  => ['required', 'string', 'max:255'],
            'name'               => ['required', 'string', 'max:255'],
            'email'              => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'           => ['required', 'confirmed', Password::defaults()],
            'plan'               => ['sometimes', 'string', 'in:free,basic,pro'],
            'organization_type'  => ['required', 'string', 'in:ets,cooperative'],
            'cooperative_type'   => ['nullable', 'string', 'in:lavoro,sociale_a,sociale_b,agricola,comunita,consumo,abitazione,consortile', 'required_if:organization_type,cooperative'],
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Crea l'utente
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // 2. Crea il tenant
            $slug = Str::slug($validated['organization_name']);
            // Assicura unicità dello slug
            $originalSlug = $slug;
            $counter = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $originalSlug.'-'.$counter++;
            }

            $tenant = Tenant::create([
                'name'              => $validated['organization_name'],
                'slug'              => $slug,
                'plan'              => $validated['plan'] ?? 'free',
                'plan_expires_at'   => now()->addDays(config('saas.trial_days', 14)),
                'is_active'         => true,
                'organization_type' => $validated['organization_type'],
                'cooperative_type'  => $validated['organization_type'] === 'cooperative'
                    ? $validated['cooperative_type']
                    : null,
            ]);

            // 3. Associa l'utente come admin del tenant
            $tenant->users()->attach($user->id, ['role' => 'admin']);

            // 4. Inizializza i dati del tenant
            app()->instance('current_tenant', $tenant);
            $this->seedTenantDefaults($tenant);

            // 5. Login automatico
            auth()->login($user);

            return redirect()->route('dashboard', ['tenant' => $tenant->slug]);
        });
    }

    /**
     * Inizializza dati base per un nuovo tenant.
     */
    private function seedTenantDefaults(Tenant $tenant): void
    {
        // Settings base
        Settings::set('quota_annuale', 50);
        Settings::set('nome_associazione', $tenant->name);
        Settings::set('indirizzo_associazione', '');
        Settings::set('codice_fiscale_associazione', '');
        Settings::set('partita_iva_associazione', '');
        Settings::set('causale_default_donazione', 'Erogazione liberale');
        Settings::set('causale_default_quota', 'Quota associativa');
        Settings::set('causale_default_rimborso', 'Rimborso spese');

        // Settings specifici cooperative
        if ($tenant->isCooperativa()) {
            Settings::set('quota_valore_unitario_coop', 50);
            Settings::set('quota_minima_quote_coop', 1);
            Settings::set('ristorno_percentuale_max', 100);
            Settings::set('riserva_legale_percentuale', 30);
            Settings::set('riserva_indivisibile_percentuale', 3);
            Settings::set('tasso_interesse_prestito', 0);
        }

        // Conto cassa default
        Conto::create([
            'code' => 'Cassa',
            'name' => 'Cassa contanti',
            'type' => 'cassa',
            'ordine' => 0,
            'attivo' => true,
            'tenant_id' => $tenant->id,
        ]);
    }
}
