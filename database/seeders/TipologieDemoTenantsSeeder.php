<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Onboarding\TipologiaAziendaCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Crea un tenant demo per ognuna delle forme giuridiche supportate dal catalogo,
 * con credenziali admin distinte e wizard onboarding già completato.
 *
 * Output:
 *  - Tabella riassuntiva in console
 *  - File markdown con tutte le credenziali in storage/app/credenziali-demo.md
 *  - 1 superadmin globale (super-admin@demo.local) per gestire tutti i tenant
 */
class TipologieDemoTenantsSeeder extends Seeder
{
    /** Password globale per il superadmin (sovrascrivibile via env). */
    private const SUPER_ADMIN_EMAIL = 'super-admin@demo.local';
    private const SUPER_ADMIN_PASSWORD = 'SuperAdmin#2026';

    public function run(): void
    {
        $this->command->info('🌱 Inizio seeding tenant demo per ogni tipologia di organizzazione...');
        $this->command->newLine();

        // Assicurati che i ruoli esistano
        $this->call(RoleSeeder::class);
        $adminRole = Role::where('name', 'admin')->first();

        // 1. Crea il superadmin globale
        $superAdmin = $this->creaSuperAdmin();

        // 2. Per ogni forma giuridica del catalogo, crea tenant + admin
        $rows = [];
        foreach (Tenant::formaGiuridicaLabels() as $formaGiuridica => $label) {
            $row = $this->creaTenantPerForma($formaGiuridica, $label, $adminRole);
            $rows[] = $row;
        }

        // 3. Esporta credenziali in markdown
        $this->esportaCredenziali($rows, $superAdmin);

        // 4. Stampa tabella riassuntiva
        $this->command->newLine();
        $this->command->info('✅ Seeding completato!');
        $this->command->newLine();
        $this->command->table(
            ['Forma Giuridica', 'Slug', 'Email Admin', 'Password'],
            array_map(fn ($r) => [
                Str::limit($r['label'], 35),
                $r['slug'],
                $r['email'],
                $r['password'],
            ], $rows)
        );

        $this->command->newLine();
        $this->command->warn('🔐 SUPER ADMIN GLOBALE:');
        $this->command->info('   Email:    ' . self::SUPER_ADMIN_EMAIL);
        $this->command->info('   Password: ' . self::SUPER_ADMIN_PASSWORD);
        $this->command->newLine();
        $this->command->info('📄 Credenziali complete salvate in: storage/app/private/credenziali-demo.md');
    }

    private function creaSuperAdmin(): User
    {
        $user = User::firstOrCreate(
            ['email' => self::SUPER_ADMIN_EMAIL],
            [
                'name'              => 'Super Admin',
                'password'          => Hash::make(self::SUPER_ADMIN_PASSWORD),
                'is_super_admin'    => true,
                'email_verified_at' => now(),
            ]
        );

        $user->forceFill([
            'is_super_admin'    => true,
            'password'          => Hash::make(self::SUPER_ADMIN_PASSWORD),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->command->info("👑 Superadmin: {$user->email}");

        return $user;
    }

    /**
     * @return array{forma_giuridica:string,label:string,slug:string,email:string,password:string,piano_conti:string,schema_bilancio:string,moduli_count:int}
     */
    private function creaTenantPerForma(string $formaGiuridica, string $label, Role $adminRole): array
    {
        $profilo = TipologiaAziendaCatalog::profilo($formaGiuridica);
        $dimensione = $profilo['dimensioni_disponibili'][0] ?? Tenant::DIM_NON_APPLICABILE;
        $regimeContabile = $profilo['regimi_contabili'][0] ?? Tenant::RC_NON_APPLICABILE;
        $regimeIva = $profilo['regimi_iva'][0] ?? Tenant::IVA_NON_APPLICABILE;

        $slug = 'demo-' . str_replace('_', '-', $formaGiuridica);
        $email = "admin-{$formaGiuridica}@demo.local";
        $password = $this->generaPassword($formaGiuridica);

        // Determina organization_type/cooperative_type legacy per backward-compat
        [$orgType, $coopType] = $this->mappaLegacy($formaGiuridica);

        $datiAnagrafici = $this->datiAnagraficiFittizi($formaGiuridica, $label);

        $tenant = Tenant::firstOrCreate(
            ['slug' => $slug],
            array_merge([
                'name'                       => "Demo — {$label}",
                'plan'                       => 'pro',
                'is_active'                  => true,
                // legacy
                'organization_type'          => $orgType,
                'cooperative_type'           => $coopType,
                // W1 — onboarding
                'forma_giuridica'            => $formaGiuridica,
                'dimensione_bilancio'        => $dimensione,
                'regime_contabile'           => $regimeContabile,
                'regime_iva'                 => $regimeIva,
                'wizard_completato_at'       => now(),
                'wizard_step_corrente'       => 5,
            ], $datiAnagrafici)
        );

        // Aggiorna sempre i campi onboarding (caso il tenant esistesse già senza)
        $tenant->forceFill(array_merge([
            'forma_giuridica'      => $formaGiuridica,
            'dimensione_bilancio'  => $dimensione,
            'regime_contabile'     => $regimeContabile,
            'regime_iva'           => $regimeIva,
            'wizard_completato_at' => $tenant->wizard_completato_at ?? now(),
            'wizard_step_corrente' => 5,
            'organization_type'    => $orgType,
            'cooperative_type'     => $coopType,
        ], $datiAnagrafici))->save();

        // Crea admin del tenant
        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name'              => "Admin {$label}",
                'password'          => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $admin->forceFill([
            'password'          => Hash::make($password),
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();

        // Allega al tenant come admin
        $tenant->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'admin'],
        ]);

        // Assegna ruolo globale admin (se la pivot esiste)
        if (! $admin->roles()->where('name', 'admin')->exists()) {
            $admin->roles()->attach($adminRole);
        }

        $this->command->info("  ✓ {$label} → {$email}");

        return [
            'forma_giuridica' => $formaGiuridica,
            'label'           => $label,
            'slug'            => $slug,
            'email'           => $email,
            'password'        => $password,
            'piano_conti'     => $profilo['piano_conti_template'],
            'schema_bilancio' => TipologiaAziendaCatalog::schemaBilancio($formaGiuridica, $dimensione),
            'moduli_count'    => count(TipologiaAziendaCatalog::moduliAbilitati($formaGiuridica, $dimensione, $regimeContabile)),
        ];
    }

    /**
     * Genera una password deterministica ma forte per ogni forma giuridica.
     * Formato: "Demo!{ABBR}#2026" — esempio: "Demo!ETSODV#2026"
     */
    private function generaPassword(string $formaGiuridica): string
    {
        $abbr = strtoupper(str_replace('_', '', $formaGiuridica));
        return "Demo!{$abbr}#2026";
    }

    /**
     * Mappa la forma giuridica ai campi legacy organization_type / cooperative_type.
     */
    private function mappaLegacy(string $formaGiuridica): array
    {
        if (str_starts_with($formaGiuridica, 'ets_')) {
            return ['ets', null];
        }
        if (str_starts_with($formaGiuridica, 'coop_')) {
            $coopType = match ($formaGiuridica) {
                Tenant::FG_COOP_LAVORO     => 'lavoro',
                Tenant::FG_COOP_SOCIALE_A  => 'sociale_a',
                Tenant::FG_COOP_SOCIALE_B  => 'sociale_b',
                Tenant::FG_COOP_AGRICOLA   => 'agricola',
                Tenant::FG_COOP_CONSORTILE => 'consortile',
                Tenant::FG_COOP_CONSUMO    => 'consumo',
                Tenant::FG_COOP_ABITAZIONE => 'abitazione',
                Tenant::FG_COOP_COMUNITA   => 'comunita',
                default                    => 'lavoro',
            };
            return ['cooperative', $coopType];
        }
        // Per società/autonomi/altro: la colonna legacy è NOT NULL; usiamo 'ets' come default
        // (il campo è deprecato e sostituito da forma_giuridica).
        return ['ets', null];
    }

    /**
     * Restituisce dati anagrafici fittizi coerenti con la forma giuridica.
     */
    private function datiAnagraficiFittizi(string $formaGiuridica, string $label): array
    {
        $cf = $this->generaCodiceFiscaleFittizio($formaGiuridica);
        $piva = $this->generaPartitaIvaFittizia($formaGiuridica);

        $base = [
            'codice_fiscale' => $cf,
            'pec'            => "demo-{$formaGiuridica}@pec.demo.local",
            'indirizzo'      => 'Via Roma, 1',
            'cap'            => '00100',
            'citta'          => 'Roma',
            'provincia'      => 'RM',
            'nazione'        => 'IT',
            'telefono'       => '+39 06 12345678',
            'sito_web'       => "https://demo-{$formaGiuridica}.local",
        ];

        // P.IVA solo se richiesta dal profilo
        $profilo = TipologiaAziendaCatalog::profilo($formaGiuridica);
        if (in_array('partita_iva', $profilo['campi_obbligatori'], true)) {
            $base['partita_iva'] = $piva;
        }

        // REA per società di capitali e persone
        if (in_array('rea_numero', $profilo['campi_obbligatori'], true)) {
            $base['rea_numero'] = 'RM-' . random_int(100000, 999999);
            $base['rea_citta']  = 'RM';
        }

        // Albo cooperative
        if (in_array('numero_iscrizione_albo_coop', $profilo['campi_obbligatori'], true)) {
            $base['numero_iscrizione_albo_coop'] = 'A' . random_int(100000, 999999);
            $base['capitale_sottoscritto']       = 25000.00;
            $base['capitale_versato']            = 25000.00;
        }

        // ATECO per forfettario
        if (in_array('attivita_ateco', $profilo['campi_obbligatori'], true)) {
            $base['attivita_ateco'] = '74.10.10'; // design / consulenza
        }

        return $base;
    }

    private function generaCodiceFiscaleFittizio(string $formaGiuridica): string
    {
        // 11 cifre fittizie deterministiche basate sulla forma
        $hash = abs(crc32($formaGiuridica));
        return str_pad((string) $hash, 11, '0', STR_PAD_LEFT);
    }

    private function generaPartitaIvaFittizia(string $formaGiuridica): string
    {
        $hash = abs(crc32($formaGiuridica . '_piva'));
        return str_pad((string) $hash, 11, '0', STR_PAD_LEFT);
    }

    /**
     * Esporta tutte le credenziali in un file markdown leggibile.
     */
    private function esportaCredenziali(array $rows, User $superAdmin): void
    {
        $now = now()->format('Y-m-d H:i:s');
        $md = "# Credenziali Demo Tenant — Tessera (ETS-OK)\n\n";
        $md .= "_Generato il {$now} dal seeder `TipologieDemoTenantsSeeder`._\n\n";
        $md .= "> **⚠️ ATTENZIONE:** queste credenziali sono per **AMBIENTI DEMO/SVILUPPO**. ";
        $md .= "Non usare in produzione.\n\n";

        $md .= "## 👑 Super Admin (gestione globale tenant)\n\n";
        $md .= "| Email | Password | URL Pannello |\n";
        $md .= "|-------|----------|--------------|\n";
        $md .= "| `" . self::SUPER_ADMIN_EMAIL . "` | `" . self::SUPER_ADMIN_PASSWORD . "` | http://localhost:8090/admin |\n\n";

        $md .= "## 🏢 Tenant per Tipologia di Organizzazione\n\n";
        $md .= "Ogni tenant ha il wizard onboarding già completato con: forma giuridica, dimensione bilancio, regime contabile, regime IVA, dati anagrafici fittizi.\n\n";

        // Raggruppa per "gruppo" del catalogo
        $perGruppo = [];
        foreach ($rows as $r) {
            $gruppo = TipologiaAziendaCatalog::gruppo($r['forma_giuridica']);
            $perGruppo[$gruppo][] = $r;
        }

        foreach ($perGruppo as $gruppo => $tenantRows) {
            $md .= "### {$gruppo}\n\n";
            $md .= "| Forma Giuridica | Slug | Email Admin | Password | Piano Conti | Schema Bilancio |\n";
            $md .= "|-----------------|------|-------------|----------|-------------|------------------|\n";
            foreach ($tenantRows as $r) {
                $md .= sprintf(
                    "| %s | `%s` | `%s` | `%s` | %s | %s |\n",
                    $r['label'],
                    $r['slug'],
                    $r['email'],
                    $r['password'],
                    $r['piano_conti'],
                    $r['schema_bilancio']
                );
            }
            $md .= "\n";
        }

        $md .= "## 📋 Note operative\n\n";
        $md .= "- **URL applicazione:** http://localhost:8090\n";
        $md .= "- **Database:** MySQL su `localhost:3307` (container `ets-ok-main-db-1`)\n";
        $md .= "- **Login:** ogni email è univoca; il superadmin viene reindirizzato al pannello `/admin`, gli altri admin alla selezione tenant.\n";
        $md .= "- **Schema password:** `Demo!{FORMA_GIURIDICA_UPPER}#2026` (es. `Demo!ETSODV#2026`).\n";
        $md .= "- **Re-seed:** `php artisan db:seed --class=TipologieDemoTenantsSeeder` (idempotente, aggiorna password e dati anagrafici).\n";

        Storage::disk('local')->put('credenziali-demo.md', $md);
    }
}
