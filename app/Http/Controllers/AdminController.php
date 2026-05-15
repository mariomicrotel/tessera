<?php

namespace App\Http\Controllers;

use App\Exceptions\OpenApiCompanyException;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Onboarding\AmbitiInteresseGenerale;
use App\Services\Onboarding\TipologiaAziendaCatalog;
use App\Services\OpenApiCompanyClient;
use App\Services\CompanyEnrichmentService;
use Illuminate\Http\JsonResponse;
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
            ->paginate(20)
            ->through(fn ($t) => array_merge($t->toArray(), [
                'wizard_pendente'      => $t->wizardPendente(),
                'forma_giuridica_label' => $t->formaGiuridicaLabel(),
            ]));

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

        return redirect()->route('admin.tenants.wizard', $tenant->slug)
            ->with('flash', ['type' => 'success', 'message' => "Tenant \"{$tenant->name}\" creato. Completa la configurazione."]);
    }

    /**
     * Mostra il wizard di onboarding/configurazione di un tenant.
     * Permette al superadmin di impostare forma giuridica, dimensione bilancio,
     * regimi contabile/IVA, dati anagrafici.
     */
    public function wizard(Tenant $tenant)
    {
        $this->authorizeSuperAdmin();

        $formaCorrente = $tenant->forma_giuridica;

        // Filtra le forme giuridiche in base al tipo di organizzazione del tenant:
        // ETS → solo gruppi ETS e Associazioni; Cooperative → solo Cooperative.
        $tutteLeFormeRaggruppate = TipologiaAziendaCatalog::tutteRaggruppate();
        $orgType = $tenant->organization_type ?? 'ets';
        if ($orgType === 'cooperative') {
            $gruppiConsentiti = ['Cooperative'];
        } else {
            $gruppiConsentiti = ['ETS — Enti del Terzo Settore', 'Associazioni e fondazioni'];
        }
        $formeRaggruppate = array_filter(
            $tutteLeFormeRaggruppate,
            fn ($k) => in_array($k, $gruppiConsentiti),
            ARRAY_FILTER_USE_KEY
        );

        return Inertia::render('Admin/Tenants/Wizard', [
            'tenant'              => $tenant->only([
                'id', 'slug', 'name', 'forma_giuridica', 'dimensione_bilancio',
                'regime_contabile', 'regime_iva', 'attivita_ateco',
                'codice_fiscale', 'partita_iva', 'numero_iscrizione_albo_coop',
                'capitale_sottoscritto', 'capitale_versato',
                'pec', 'rea_numero', 'rea_citta', 'indirizzo', 'cap', 'citta',
                'provincia', 'nazione', 'telefono', 'sito_web',
                // ETS
                'runts_numero', 'runts_sezione', 'runts_data_iscrizione',
                'personalita_giuridica', 'patrimonio_destinato',
                'ambiti_attivita', 'attivita_principale', 'fascia_entrate',
                'assicurazione_volontari_polizza', 'assicurazione_volontari_compagnia',
                'assicurazione_volontari_scadenza', 'bilancio_url_pubblicazione',
                'wizard_completato_at', 'wizard_step_corrente',
                'organization_type',
            ]),
            'formeRaggruppate'    => $formeRaggruppate,
            'formaLabels'         => Tenant::formaGiuridicaLabels(),
            // Profilo della forma corrente (se selezionata): determina dimensioni/regimi/campi
            'profiloCorrente'     => $formaCorrente
                ? $this->profiloPerVue($formaCorrente, $tenant->dimensione_bilancio)
                : null,
            // Note normative + regole di compatibilità (per UI dinamica)
            'noteNormative'       => [
                'dimensione'       => TipologiaAziendaCatalog::noteDimensione(),
                'regime_contabile' => TipologiaAziendaCatalog::noteRegimeContabile(),
                'regime_iva'       => TipologiaAziendaCatalog::noteRegimeIva(),
            ],
            // Catalogo ETS art. 5 D.Lgs. 117/2017
            'etsCatalog'          => [
                'sezioni_runts'        => AmbitiInteresseGenerale::sezioniRunts(),
                'ambiti'               => AmbitiInteresseGenerale::tutti(),
                'sezione_suggerita'    => $formaCorrente ? AmbitiInteresseGenerale::sezioneSuggerita($formaCorrente) : null,
                'fasce_entrate'        => [
                    'sotto_60k'  => 'Fino a 60.000 € — adempimenti minimi',
                    'sotto_220k' => '60.000 € – 220.000 € — Rendiconto per cassa (Modello D)',
                    'sotto_1m'   => '220.000 € – 1.000.000 € — SP + Rendiconto Gestionale (Modelli A+B)',
                    'sopra_1m'   => 'Oltre 1.000.000 € — bilancio sociale obbligatorio (art. 14 CTS)',
                ],
            ],
        ]);
    }

    /**
     * Salva uno step del wizard. Riceve un payload parziale con tutti i campi
     * compilati fino a quel momento e marca il wizard completato all'ultimo step.
     */
    public function wizardSave(Request $request, Tenant $tenant)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'step'                          => ['required', 'integer', 'between:1,6'],
            'forma_giuridica'               => ['nullable', 'string', 'in:' . implode(',', array_keys(Tenant::formaGiuridicaLabels()))],
            'dimensione_bilancio'           => ['nullable', 'string'],
            'regime_contabile'              => ['nullable', 'string'],
            'regime_iva'                    => ['nullable', 'string'],
            'attivita_ateco'                => ['nullable', 'string', 'max:20'],
            'codice_fiscale'                => ['nullable', 'string', 'max:16'],
            'partita_iva'                   => ['nullable', 'string', 'max:11'],
            'numero_iscrizione_albo_coop'   => ['nullable', 'string', 'max:30'],
            'capitale_sottoscritto'         => ['nullable', 'numeric', 'min:0'],
            'capitale_versato'              => ['nullable', 'numeric', 'min:0'],
            'pec'                           => ['nullable', 'email', 'max:255'],
            'rea_numero'                    => ['nullable', 'string', 'max:30'],
            'rea_citta'                     => ['nullable', 'string', 'max:5'],
            'indirizzo'                     => ['nullable', 'string', 'max:255'],
            'cap'                           => ['nullable', 'string', 'max:5'],
            'citta'                         => ['nullable', 'string', 'max:100'],
            'provincia'                     => ['nullable', 'string', 'max:5'],
            'nazione'                       => ['nullable', 'string', 'max:5'],
            'telefono'                      => ['nullable', 'string', 'max:30'],
            'sito_web'                      => ['nullable', 'string', 'max:255'],
            // ── ETS compliance ──
            'runts_numero'                  => ['nullable', 'string', 'max:30'],
            'runts_sezione'                 => ['nullable', 'string', 'in:a,b,c,d,e,f,g'],
            'runts_data_iscrizione'         => ['nullable', 'date'],
            'personalita_giuridica'         => ['nullable', 'boolean'],
            'patrimonio_destinato'          => ['nullable', 'numeric', 'min:0'],
            'ambiti_attivita'               => ['nullable', 'array'],
            'ambiti_attivita.*'             => ['string', 'in:a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z'],
            'attivita_principale'           => ['nullable', 'string', 'max:5'],
            'fascia_entrate'                => ['nullable', 'string', 'in:sotto_60k,sotto_220k,sotto_1m,sopra_1m'],
            'assicurazione_volontari_polizza'   => ['nullable', 'string', 'max:50'],
            'assicurazione_volontari_compagnia' => ['nullable', 'string', 'max:100'],
            'assicurazione_volontari_scadenza'  => ['nullable', 'date'],
            'bilancio_url_pubblicazione'    => ['nullable', 'url', 'max:500'],
            'completa'                      => ['sometimes', 'boolean'],
        ]);

        $step = (int) $validated['step'];
        $completa = (bool) ($validated['completa'] ?? false);

        // Stato consolidato per il check anagrafica (step 4): fallback al tenant
        $forma = $validated['forma_giuridica'] ?? $tenant->forma_giuridica;

        // Per la validazione dei vincoli normativi prendiamo i valori del payload corrente.
        // Il fallback al tenant si applica SOLO al completamento finale (step 5), perché
        // nelle fasi intermedie il tenant può avere ancora i default DB ('non_applicabile')
        // che non sono compatibili con la forma giuridica appena scelta.
        if ($completa) {
            $dim     = $validated['dimensione_bilancio'] ?? $tenant->dimensione_bilancio;
            $regCont = $validated['regime_contabile']    ?? $tenant->regime_contabile;
            $regIva  = $validated['regime_iva']          ?? $tenant->regime_iva;
        } else {
            $dim     = $validated['dimensione_bilancio'] ?? null;
            $regCont = $validated['regime_contabile']    ?? null;
            $regIva  = $validated['regime_iva']          ?? null;
        }

        // Filtra i default DB residui ('non_applicabile') che non sono ammessi
        // per la forma giuridica corrente: significa "non ancora scelto", non un valore reale.
        if ($forma) {
            $profilo = TipologiaAziendaCatalog::profilo($forma);
            if ($dim === Tenant::DIM_NON_APPLICABILE && ! in_array(Tenant::DIM_NON_APPLICABILE, $profilo['dimensioni_disponibili'], true)) {
                $dim = null;
            }
            if ($regCont === Tenant::RC_NON_APPLICABILE && ! in_array(Tenant::RC_NON_APPLICABILE, $profilo['regimi_contabili'], true)) {
                $regCont = null;
            }
            if ($regIva === Tenant::IVA_NON_APPLICABILE && ! in_array(Tenant::IVA_NON_APPLICABILE, $profilo['regimi_iva'], true)) {
                $regIva = null;
            }
        }

        // ── Validazione vincoli normativi (con extra ETS) ──
        if ($forma) {
            $extraEts = [
                'runts_sezione'                   => $validated['runts_sezione']                  ?? $tenant->runts_sezione,
                'personalita_giuridica'           => $validated['personalita_giuridica']          ?? $tenant->personalita_giuridica,
                'patrimonio_destinato'            => $validated['patrimonio_destinato']           ?? $tenant->patrimonio_destinato,
                'fascia_entrate'                  => $validated['fascia_entrate']                 ?? $tenant->fascia_entrate,
                'ambiti_attivita'                 => $validated['ambiti_attivita']                ?? $tenant->ambiti_attivita,
                'assicurazione_volontari_polizza' => $validated['assicurazione_volontari_polizza'] ?? $tenant->assicurazione_volontari_polizza,
            ];
            $vincoli = TipologiaAziendaCatalog::validaCombinazione($forma, $dim, $regCont, $regIva, $extraEts);
            if (! $vincoli['valid']) {
                $errs = [];
                foreach ($vincoli['errors'] as $i => $msg) {
                    $errs["vincolo_{$i}"] = $msg;
                }
                return back()->withErrors($errs);
            }
        }

        // ── Validazioni anagrafica (solo step 4 in poi o al completamento) ──
        if ($step >= 4 && $forma) {
            $obbligatori = TipologiaAziendaCatalog::campiObbligatori($forma);
            $errors = [];
            foreach ($obbligatori as $campo) {
                $valore = $validated[$campo] ?? $tenant->{$campo};
                if (empty($valore)) {
                    $errors[$campo] = "Campo obbligatorio per questa forma giuridica.";
                }
            }
            if (! empty($errors) && ($step === 4 || $completa)) {
                return back()->withErrors($errors);
            }
        }

        // Aggiorna i campi forniti (filtra null/'' per non sovrascrivere con valori vuoti)
        $payload = collect($validated)
            ->except(['step', 'completa'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->toArray();

        // Se la forma è appena stata cambiata, azzera i campi enum incompatibili al default 'non_applicabile'
        // così la prossima validazione non risale i valori vecchi non più validi.
        if ($forma && ($validated['forma_giuridica'] ?? null) === $forma && $forma !== ($tenant->forma_giuridica ?? null)) {
            $profilo = TipologiaAziendaCatalog::profilo($forma);
            if (! in_array($tenant->dimensione_bilancio, $profilo['dimensioni_disponibili'], true)
                && empty($payload['dimensione_bilancio'])) {
                $payload['dimensione_bilancio'] = Tenant::DIM_NON_APPLICABILE;
            }
            if (! in_array($tenant->regime_contabile, $profilo['regimi_contabili'], true)
                && empty($payload['regime_contabile'])) {
                $payload['regime_contabile'] = Tenant::RC_NON_APPLICABILE;
            }
            if (! in_array($tenant->regime_iva, $profilo['regimi_iva'], true)
                && empty($payload['regime_iva'])) {
                $payload['regime_iva'] = Tenant::IVA_NON_APPLICABILE;
            }
        }

        $stepMax = ($forma && str_starts_with($forma, 'ets_')) ? 6 : 5;
        $payload['wizard_step_corrente'] = $completa ? $stepMax : max($step, $tenant->wizard_step_corrente ?? 1);

        if ($completa) {
            $payload['wizard_completato_at'] = now();
            // Mantieni legacy organization_type/cooperative_type se forma è ETS/Coop
            if (! empty($payload['forma_giuridica'] ?? $tenant->forma_giuridica)) {
                $fg = $payload['forma_giuridica'] ?? $tenant->forma_giuridica;
                if (str_starts_with($fg, 'ets_')) {
                    $payload['organization_type'] = 'ets';
                    $payload['cooperative_type'] = null;
                } elseif (str_starts_with($fg, 'coop_')) {
                    $payload['organization_type'] = 'cooperative';
                    $payload['cooperative_type'] = match ($fg) {
                        Tenant::FG_COOP_LAVORO     => 'lavoro',
                        Tenant::FG_COOP_SOCIALE_A  => 'sociale_a',
                        Tenant::FG_COOP_SOCIALE_B  => 'sociale_b',
                        Tenant::FG_COOP_AGRICOLA   => 'agricola',
                        Tenant::FG_COOP_CONSORTILE => 'consortile',
                        Tenant::FG_COOP_CONSUMO    => 'consumo',
                        Tenant::FG_COOP_ABITAZIONE => 'abitazione',
                        Tenant::FG_COOP_COMUNITA   => 'comunita',
                        default                    => null,
                    };
                }
            }
        }

        $tenant->update($payload);

        if ($completa) {
            return redirect()->route('admin.tenants.show', $tenant->slug)
                ->with('flash', ['type' => 'success', 'message' => 'Wizard completato. Tenant configurato.']);
        }

        return back()->with('flash', ['type' => 'success', 'message' => "Step {$step} salvato."]);
    }

    /**
     * Costruisce il profilo da passare al frontend per popolare i select dinamici.
     */
    private function profiloPerVue(string $forma, ?string $dimensione = null): array
    {
        $profilo = TipologiaAziendaCatalog::profilo($forma);
        return [
            'forma_giuridica'         => $forma,
            'piano_conti_template'    => $profilo['piano_conti_template'],
            'schema_bilancio'         => TipologiaAziendaCatalog::schemaBilancio($forma, $dimensione ?? Tenant::DIM_NON_APPLICABILE),
            'moduli'                  => TipologiaAziendaCatalog::moduliAbilitati($forma, $dimensione ?? Tenant::DIM_NON_APPLICABILE),
            'dimensioni_disponibili'  => $profilo['dimensioni_disponibili'],
            'regimi_contabili'        => $profilo['regimi_contabili'],
            'regimi_iva'              => $profilo['regimi_iva'],
            'campi_obbligatori'       => $profilo['campi_obbligatori'],
            'gruppo'                  => $profilo['gruppo'],
            'descrizione_breve'       => $profilo['descrizione_breve'],
            // Mappa per il frontend: regimeContabile → lista regimi IVA compatibili
            'regimi_iva_per_contabile' => collect($profilo['regimi_contabili'])
                ->mapWithKeys(fn ($rc) => [$rc => TipologiaAziendaCatalog::regimiIvaCompatibili($rc, $profilo['regimi_iva'])])
                ->toArray(),
        ];
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
    /**
     * Enrichment API per il wizard (senza contesto tenant).
     */
    public function companyEnrichment(Request $request, OpenApiCompanyClient $client): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'identifier' => 'required|string|min:6|max:30',
        ]);

        try {
            $raw = $client->getItalianCompanyStart($request->input('identifier'));
            $mapped = CompanyEnrichmentService::mapItStartToAnagrafica($raw);

            return response()->json([
                'success' => true,
                'source' => 'api',
                'data' => $mapped,
            ]);
        } catch (OpenApiCompanyException $e) {
            $code = in_array($e->getCode(), [204, 400, 401, 402, 404, 406, 408, 417, 429]) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $code);
        }
    }

    public function companyEnrichmentUsage(): JsonResponse
    {
        $this->authorizeSuperAdmin();

        return response()->json(['status' => 'ok']);
    }

    private function authorizeSuperAdmin(): void
    {
        if (! auth()->user()?->is_super_admin) {
            abort(403, 'Accesso riservato al super admin della piattaforma.');
        }
    }
}
