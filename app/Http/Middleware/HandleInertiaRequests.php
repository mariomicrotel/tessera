<?php

namespace App\Http\Middleware;

use App\Models\Attachment;
use App\Models\Settings;
use App\Providers\AppServiceProvider;
use App\Support\Tessera;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /** TTL cache URL firmato logo (secondi), come in PublicSiteController. */
    private const LOGO_SIGNED_URL_CACHE_TTL = 3000;

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $logoUrl = null;
        $user = null;
        $authMember = null;

        $appKey = config('app.key');
        if (! empty($appKey) && $appKey !== AppServiceProvider::INSTALL_PLACEHOLDER_KEY) {
            $logoAttachment = Attachment::forSetting('logo')->first();
            if ($logoAttachment) {
                $logoUrl = Cache::remember('public.logo.signed_url', self::LOGO_SIGNED_URL_CACHE_TTL, function () use ($logoAttachment) {
                    return URL::temporarySignedRoute(
                        'public.logo.show',
                        now()->addMinutes(60),
                        ['attachment' => $logoAttachment->id]
                    );
                });
            }
            $user = $request->user();
            if ($user && $user->member) {
                $m = $user->member;
                $authMember = ['id' => $m->id, 'full_name' => $m->full_name];
            }
        }

        return [
            ...parent::share($request),
            // Ziggy: route disponibili in JS; aggiornato a ogni richiesta così dopo login (navigazione Inertia) route('dashboard') funziona
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
                'defaults' => array_filter([
                    'tenant' => app()->bound('current_tenant') ? app('current_tenant')->slug : null,
                ]),
            ],
            // Token CSRF per form nativi (es. upload allegati)
            'csrf_token' => csrf_token(),
            // Ruoli utente per menu e permessi frontend; superadmin = admin ovunque.
            // Il consulente (commercialista) attivo sul tenant corrente acquisisce
            // anche i ruoli admin/contabile/segreteria per gestire i dati dell'ente.
            'userRoles' => function () use ($user) {
                if (! $user) {
                    return [];
                }
                if ($user->is_super_admin) {
                    return ['admin'];
                }
                $roles = $user->roles->pluck('name')->toArray();
                if (app()->bound('current_tenant') && $user->isConsultantForCurrentTenant()) {
                    $roles = array_values(array_unique(array_merge($roles, ['admin', 'contabile', 'segreteria'])));
                }
                return $roles;
            },
            // Tenant corrente (se risolto dal middleware)
            'currentTenant' => fn () => app()->bound('current_tenant') ? [
                'id' => app('current_tenant')->id,
                'name' => app('current_tenant')->name,
                'slug' => app('current_tenant')->slug,
                'plan' => app('current_tenant')->plan,
            ] : null,
            // Ruolo utente nel tenant corrente
            'tenantRole' => fn () => $request->attributes->get('tenant_role'),
            // Super admin piattaforma
            'isSuperAdmin' => $user?->is_super_admin ?? false,
            // True se l'utente sta operando come consulente (commercialista) sul tenant corrente
            'isConsultantInTenant' => fn () => $user
                && app()->bound('current_tenant')
                && $user->isConsultantForCurrentTenant(),
            // Socio collegato (per area self-service: menu "Il mio profilo", Modifica su Show)
            'authMember' => $authMember,
            // URL logo associazione (presigned, in cache; per header/layout e pagina login)
            'logo_url' => $logoUrl,
            // Flash message (redirect con with('flash', ['type' => ..., 'message' => ...]))
            'flash' => $request->session()->pull('flash'),
            // Versioni API disponibili (per documentazione Token API)
            'apiVersions' => config('api.versions', ['v1']),
            // Versione applicazione (semver) visibile in area riservata e pagine auth
            'appVersion' => config('app.version'),
            // Nome associazione per titolo browser e UI (al posto di ETS-OK/APP_NAME)
            'nome_associazione' => ! empty($appKey) && $appKey !== AppServiceProvider::INSTALL_PLACEHOLDER_KEY
                ? Settings::get('nome_associazione', config('app.name'))
                : config('app.name'),
            // Tipo organizzazione: 'ets' | 'cooperative' | null (lazy: nessuna query su route pubbliche)
            'organization_type' => fn () => app()->bound('current_tenant')
                ? app('current_tenant')->organization_type
                : null,
            // Sotto-tipo cooperativa: 'lavoro' | 'sociale_a' | ... | null
            'cooperative_type' => fn () => app()->bound('current_tenant')
                ? app('current_tenant')->cooperative_type
                : null,
            // Helper booleano per Vue: true se il tenant è una cooperativa
            'is_cooperativa' => fn () => app()->bound('current_tenant')
                ? app('current_tenant')->isCooperativa()
                : false,
            // Periodicità liquidazione IVA predefinita: 'mensile' | 'trimestrale' | 'annuale'
            'periodicita_liquidazione_iva' => fn () => app()->bound('current_tenant')
                ? Settings::get('periodicita_liquidazione_iva', 'mensile')
                : null,
            // Regime forfettario L. 398/1991 (ETS/associazioni con proventi commerciali ≤ 400.000 €)
            'regime_398_1991' => fn () => app()->bound('current_tenant')
                ? (bool) Settings::get('regime_398_1991', false)
                : false,
            // Tessera: moduli abilitati (feature flags) — usato dal frontend per filtrare menu/voci
            'tessera_modules' => fn () => Tessera::allModules(),
            // Tessera: etichette rinominate (es. "Contabilità" → "Amministrazione semplificata")
            'tessera_labels' => fn () => config('tessera.labels', []),
            // Badge non-letti posta IMAP (lazy: nessuna query su route pubbliche o senza tenant)
            'mail_unread_count' => fn () => app()->bound('current_tenant')
                ? \App\Models\MailMessage::query()->where('is_read', false)->count()
                : 0,
        ];
    }
}
