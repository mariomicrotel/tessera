<?php

namespace Database\Seeders;

use App\Models\Conto;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\PrestitoSocialeLibretto;
use App\Models\PrestitoSocialeMovimento;
use App\Models\Ristorno;
use App\Models\RistornoEntry;
use App\Models\Settings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CapitaleSocialeService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CooperativaSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🤝 Inizio seeding Cooperativa Demo...');

        // ──────────────────────────────────────────────────────────────────
        // 1. Crea/trova tenant cooperativa
        // ──────────────────────────────────────────────────────────────────
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'cooperativa-demo'],
            [
                'name'               => 'Cooperativa Demo',
                'organization_type'  => 'cooperative',
                'cooperative_type'   => 'lavoro',
                'codice_fiscale'     => '12345678901234',
            ]
        );
        $this->command->info("✓ Tenant: {$tenant->name} (#{$tenant->id})");

        // Binding per l'app
        app()->instance('current_tenant', $tenant);

        // ──────────────────────────────────────────────────────────────────
        // 2. Crea utente admin per la coop
        // ──────────────────────────────────────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'coop-admin@example.com'],
            [
                'name'              => 'Admin Cooperativa',
                'email_verified_at' => now(),
                'password'          => Hash::make('password'),
            ]
        );
        $this->command->info("✓ Utente: {$user->email}");

        // Allega al tenant con ruolo admin
        $tenant->users()->syncWithoutDetaching([
            $user->id => ['role' => 'admin'],
        ]);

        // ──────────────────────────────────────────────────────────────────
        // 3. Crea conto tesoreria se non esiste
        // ──────────────────────────────────────────────────────────────────
        $conto = Conto::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Cassa Cooperativa'],
            [
                'type'   => 'cassa',
                'attivo' => true,
            ]
        );
        $this->command->info("✓ Conto: {$conto->name}");

        // ──────────────────────────────────────────────────────────────────
        // 4. Crea 5 soci lavoratori
        // ──────────────────────────────────────────────────────────────────
        $soci = [];
        $nomi = [
            ['Mario', 'Rossi'],
            ['Anna', 'Bianchi'],
            ['Carlo', 'Verdi'],
            ['Lucia', 'Neri'],
            ['Paolo', 'Gialli'],
        ];

        // Prendi o crea il tipo "socio"
        $tipoSocio = MemberType::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'socio'],
            ['display_name' => 'Socio']
        );

        foreach ($nomi as [$nome, $cognome]) {
            $socio = Member::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => strtolower($nome . '.' . $cognome) . '@coop.local'],
                [
                    'member_type_id'   => $tipoSocio->id,
                    'nome'             => $nome,
                    'cognome'          => $cognome,
                    'codice_fiscale'   => strtoupper(substr($cognome, 0, 3) . substr($nome, 0, 3)) . '00000A000X',
                    'socio_lavoratore' => true,
                    'stato'            => 'attivo',
                    'data_iscrizione'  => now()->subMonths(12),
                ]
            );
            $soci[] = $socio;
            $this->command->info("  ✓ Socio lavoratore: {$socio->nomeCompleto()}");
        }

        // ──────────────────────────────────────────────────────────────────
        // 5. Sottoscrivi quote per ogni socio (10 quote da €50)
        // ──────────────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('📋 Sottoscrizione quote di capitale...');

        $service = new CapitaleSocialeService();
        foreach ($soci as $socio) {
            try {
                $service->sottoscriviQuote(
                    $socio,
                    10,     // numero_quote
                    50.00,  // valore_unitario
                    now()->subMonths(6)
                );
                $this->command->info("  ✓ {$socio->nomeCompleto()}: 10 quote da €50 = €500 sottoscritto");
            } catch (\Exception $e) {
                $this->command->error("  ✗ Errore per {$socio->nomeCompleto()}: " . $e->getMessage());
            }
        }

        // ──────────────────────────────────────────────────────────────────
        // 6. Crea 3 libretti prestito sociale con depositi iniziali
        // ──────────────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('💳 Apertura libretti prestito sociale...');

        $libretti = [];
        for ($i = 0; $i < 3; $i++) {
            $socio = $soci[$i];
            $numeroLibretto = 'PS-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT);

            $libretto = PrestitoSocialeLibretto::firstOrCreate(
                [
                    'tenant_id'       => $tenant->id,
                    'numero_libretto' => $numeroLibretto,
                ],
                [
                    'member_id'              => $socio->id,
                    'saldo_attuale'          => 1000.00,
                    'tasso_interesse_annuo'  => 0.0200, // 2% annuo
                    'data_apertura'          => now()->subMonths(3),
                    'status'                 => 'attivo',
                    'note'                   => "Libretto apertura demo per {$socio->nomeCompleto()}",
                ]
            );
            $libretti[] = $libretto;

            // Movimento iniziale: deposito €1000 (crea solo se non esiste)
            // deposito ha segno avere (aumenta il saldo)
            $dataDeposito = now()->subMonths(3);
            PrestitoSocialeMovimento::firstOrCreate(
                [
                    'tenant_id'   => $tenant->id,
                    'libretto_id' => $libretto->id,
                    'tipo'        => 'deposito',
                    'data_valuta' => $dataDeposito,
                ],
                [
                    'importo'            => 1000.00,
                    'segno'              => 'avere',
                    'saldo_dopo'         => 1000.00,
                    'data_registrazione' => $dataDeposito,
                    'anno_competenza'    => $dataDeposito->year,
                    'mese_competenza'    => $dataDeposito->month,
                    'descrizione'        => 'Deposito iniziale',
                ]
            );

            // Incasso per il deposito
            Incasso::create([
                'tenant_id'          => $tenant->id,
                'member_id'          => $socio->id,
                'amount'             => 1000.00,
                'paid_at'            => now()->subMonths(3),
                'conto_id'           => $conto->id,
                'description'        => "Deposito prestito sociale libretto {$libretto->numero_libretto}",
                'genera_prima_nota'  => false, // Non genera prima nota per demo
                'type'               => 'prestito_sociale',
            ]);

            $this->command->info("  ✓ Libretto {$libretto->numero_libretto} ({$socio->nomeCompleto()}): saldo €1000");
        }

        // ──────────────────────────────────────────────────────────────────
        // 7. Crea ristorno per l'anno scorso: €500 distribuiti ai 5 soci
        // ──────────────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('💰 Creazione ristorno anno precedente...');

        $annoScorso = now()->year - 1;
        $importoTotaleLordo = 500.00;
        $aliquotaRitenuta = 0.26; // 26% ritenuta d'acconto

        $ristorno = Ristorno::create([
            'tenant_id'                   => $tenant->id,
            'anno'                        => $annoScorso,
            'importo_totale_deliberato'   => $importoTotaleLordo,
            'aliquota_ritenuta'           => $aliquotaRitenuta,
            'data_delibera_assemblea'     => Carbon::create($annoScorso, 6, 15),
            'status'                      => 'deliberato',
            'note'                        => "Ristorno demo anno {$annoScorso}",
        ]);

        // Distribuisci il ristorno ai 5 soci in parti uguali
        $importoPerSocio = round($importoTotaleLordo / count($soci), 2);
        foreach ($soci as $socio) {
            $calcolo = RistornoEntry::calcolaFromLordo($importoPerSocio, $aliquotaRitenuta);
            RistornoEntry::create([
                'tenant_id'         => $tenant->id,
                'ristorno_id'       => $ristorno->id,
                'member_id'         => $socio->id,
                'importo_lordo'     => $calcolo['importo_lordo'],
                'aliquota_ritenuta' => $calcolo['aliquota_ritenuta'],
                'importo_ritenuta'  => $calcolo['importo_ritenuta'],
                'importo_netto'     => $calcolo['importo_netto'],
                'status'            => 'deliberato',
            ]);
        }

        $this->command->info("  ✓ Ristorno {$annoScorso}: €{$importoTotaleLordo} lordi (€{$importoPerSocio} per socio)");
        $aliquotaPercent = round($aliquotaRitenuta * 100, 0);
        $this->command->info("  ✓ Aliquota ritenuta: {$aliquotaPercent}%");

        // ──────────────────────────────────────────────────────────────────
        // 8. Crea Settings specifiche della cooperativa
        // ──────────────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('⚙️ Configurazione cooperative-specific settings...');

        $settings = [
            'quota_valore_unitario_coop' => '50.00',
            'quota_minima_quote_coop'    => '10',
            'riserva_legale_percentuale' => '30',
        ];

        foreach ($settings as $key => $value) {
            Settings::set($key, $value);
            $this->command->info("  ✓ {$key} = {$value}");
        }

        $this->command->info('');
        $this->command->info('✨ Seeding Cooperativa Demo completato!');
        $this->command->info("📊 Riepilogo:");
        $this->command->info("   - Tenant: {$tenant->name}");
        $this->command->info("   - Soci lavoratori: " . count($soci));
        $this->command->info("   - Capitale sottoscritto totale: €" . (count($soci) * 500));
        $this->command->info("   - Libretti prestito sociale: " . count($libretti));
        $this->command->info("   - Saldo totale prestiti: €" . (count($libretti) * 1000));
        $this->command->info("   - Ristorno {$annoScorso}: €{$importoTotaleLordo}");
    }
}
