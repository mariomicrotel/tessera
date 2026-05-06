<?php

namespace Database\Seeders;

use App\Models\CaricaSociale;
use App\Models\Conto;
use App\Models\EtsAttoCostituivo;
use App\Models\EtsStatuto;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\Organo;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ets\ComplianceRuleEngine;
use Illuminate\Database\Seeder;

/**
 * Seeder completo per un tenant di test.
 * Crea: soci, volontari, organi, cariche, elezioni,
 * statuto, atto costitutivo, conti, incassi, movimenti, etc.
 *
 * Uso: php artisan db:seed --class=TenantTestDataSeeder
 * (Crea test data su tutti i tenant attivi)
 */
class TenantTestDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::where('is_active', true)->get() as $tenant) {
            app()->instance('current_tenant', $tenant);
            $this->seedTenant($tenant);
        }
    }

    private function seedTenant(Tenant $tenant): void
    {
        $this->command->info("🌱 Seeding test data per tenant: {$tenant->name}");

        // Member Types
        $tipoSocio     = MemberType::firstOrCreate(['name' => 'Socio', 'tenant_id' => $tenant->id]);
        $tipoVolontario = MemberType::firstOrCreate(['name' => 'Volontario', 'tenant_id' => $tenant->id]);

        // ─────────────────────────────────────────────────────────────
        // SOCI
        // ─────────────────────────────────────────────────────────────

        $soci = collect([
            ['nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'mario.rossi@example.com', 'codice_fiscale' => 'RSSMRA90A01H501T'],
            ['nome' => 'Giulia', 'cognome' => 'Bianchi', 'email' => 'giulia.bianchi@example.com', 'codice_fiscale' => 'BNCCGL92B41L736K'],
            ['nome' => 'Alessandro', 'cognome' => 'Verdi', 'email' => 'alex.verdi@example.com', 'codice_fiscale' => 'VRDLSS88C15F205S'],
            ['nome' => 'Francesca', 'cognome' => 'Neri', 'email' => 'francesca.neri@example.com', 'codice_fiscale' => 'NRCFNC95D52E625L'],
            ['nome' => 'Marco', 'cognome' => 'Ferrari', 'email' => 'marco.ferrari@example.com', 'codice_fiscale' => 'FRRMRC87H22B963Q'],
        ])->map(function ($data) use ($tenant, $tipoSocio) {
            return Member::firstOrCreate(
                ['codice_fiscale' => $data['codice_fiscale'], 'tenant_id' => $tenant->id],
                array_merge($data, [
                    'tenant_id'       => $tenant->id,
                    'member_type_id'  => $tipoSocio->id,
                    'stato'           => 'attivo',
                    'data_iscrizione' => now()->subMonths(rand(1, 24)),
                ])
            );
        });

        $this->command->info("✓ {$soci->count()} soci creati");

        // ─────────────────────────────────────────────────────────────
        // VOLONTARI
        // ─────────────────────────────────────────────────────────────

        $volontari = collect([
            ['nome' => 'Laura', 'cognome' => 'Conti', 'email' => 'laura.conti@example.com', 'codice_fiscale' => 'CNTLRA91E61G273T'],
            ['nome' => 'Paolo', 'cognome' => 'Gallo', 'email' => 'paolo.gallo@example.com', 'codice_fiscale' => 'GLLPLA89F05D969R'],
        ])->map(function ($data) use ($tenant, $tipoVolontario) {
            return Member::firstOrCreate(
                ['codice_fiscale' => $data['codice_fiscale'], 'tenant_id' => $tenant->id],
                array_merge($data, [
                    'tenant_id'       => $tenant->id,
                    'member_type_id'  => $tipoVolontario->id,
                    'stato'           => 'attivo',
                    'data_iscrizione' => now()->subMonths(rand(1, 12)),
                ])
            );
        });

        $this->command->info("✓ {$volontari->count()} volontari creati");

        // ─────────────────────────────────────────────────────────────
        // ORGANI E CARICHE
        // ─────────────────────────────────────────────────────────────

        $cd = Organo::firstOrCreate(
            ['slug' => 'consiglio-direttivo', 'tenant_id' => $tenant->id],
            [
                'tenant_id'                         => $tenant->id,
                'nome'                              => 'Consiglio Direttivo',
                'durata_mesi'                       => 24,
                'richiedi_elezioni_fine_mandato'    => true,
                'mandato_da'                        => now()->startOfYear(),
            ]
        );

        $cariche = [
            ['nome' => 'Presidente', 'ordine' => 1],
            ['nome' => 'Segretario', 'ordine' => 2],
            ['nome' => 'Tesoriere', 'ordine' => 3],
            ['nome' => 'Membro', 'ordine' => 4, 'multiplo' => true],
        ];

        foreach ($cariche as $carica) {
            CaricaSociale::firstOrCreate(
                ['organo_id' => $cd->id, 'nome' => $carica['nome']],
                array_merge(['tenant_id' => $tenant->id], $carica)
            );
        }

        $this->command->info("✓ Organi e cariche creati");

        // ─────────────────────────────────────────────────────────────
        // CONTI (Cash & Bank)
        // ─────────────────────────────────────────────────────────────

        $contoCassa = Conto::firstOrCreate(
            ['name' => 'Cassa', 'tenant_id' => $tenant->id],
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Cassa',
                'code'      => 'CASSA001',
                'type'      => 'cassa',
                'ordine'    => 1,
                'attivo'    => true,
            ]
        );

        $contoBanca = Conto::firstOrCreate(
            ['name' => 'Conto Corrente', 'tenant_id' => $tenant->id],
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Conto Corrente',
                'code'      => 'BANCA001',
                'type'      => 'banca',
                'ordine'    => 2,
                'attivo'    => true,
            ]
        );

        $this->command->info("✓ 2 conti creati");

        // ─────────────────────────────────────────────────────────────
        // INCASSI (Donazioni, Quote)
        // ─────────────────────────────────────────────────────────────

        $incassi = 0;
        foreach ($soci as $socio) {
            Incasso::firstOrCreate(
                ['member_id' => $socio->id, 'tenant_id' => $tenant->id, 'type' => 'quota', 'paid_at' => now()->subMonths(1)->toDateString()],
                [
                    'tenant_id'     => $tenant->id,
                    'member_id'     => $socio->id,
                    'conto_id'      => $contoCassa->id,
                    'type'          => 'quota',
                    'amount'        => 50,
                    'paid_at'       => now()->subMonths(1),
                    'description'   => 'Quota associativa annuale',
                ]
            );
            $incassi++;
        }

        Incasso::firstOrCreate(
            ['donor_name' => 'Donazione Anonima', 'tenant_id' => $tenant->id, 'type' => 'donazione', 'paid_at' => now()->toDateString()],
            [
                'tenant_id'     => $tenant->id,
                'donor_name'    => 'Donazione Anonima',
                'conto_id'      => $contoBanca->id,
                'type'          => 'donazione',
                'amount'        => 250,
                'paid_at'       => now(),
                'description'   => 'Erogazione liberale',
            ]
        );
        $incassi++;

        $this->command->info("✓ {$incassi} incassi creati");

        // ─────────────────────────────────────────────────────────────
        // STATUTO (solo se ETS)
        // ─────────────────────────────────────────────────────────────

        if ($tenant->isEts()) {
            $statuto = EtsStatuto::firstOrCreate(
                ['tenant_id' => $tenant->id, 'versione' => now()->year . '-v1'],
                [
                    'tenant_id'          => $tenant->id,
                    'versione'           => now()->year . '-v1',
                    'titolo'             => 'Statuto ' . $tenant->name,
                    'stato'              => 'approvato',
                    'data_approvazione'  => now()->subMonths(6),
                    'data_deposito'      => now()->subMonths(5),
                    'note'               => 'Statuto approvato dall\'assemblea del ' . now()->subMonths(6)->format('d/m/Y'),
                ]
            );

            // Clausole
            $clausole = [
                ['numero_articolo' => '1', 'titolo' => 'Denominazione e natura giuridica', 'articolo_cts' => 'art. 21 CTS'],
                ['numero_articolo' => '2', 'titolo' => 'Durata', 'articolo_cts' => 'art. 21 CTS'],
                ['numero_articolo' => '3', 'titolo' => 'Sede legale', 'articolo_cts' => 'art. 21 CTS'],
                ['numero_articolo' => '4', 'titolo' => 'Scopi e attività', 'articolo_cts' => 'art. 5 CTS'],
                ['numero_articolo' => '5', 'titolo' => 'Organi dell\'ente', 'articolo_cts' => 'art. 26 CTS'],
                ['numero_articolo' => '6', 'titolo' => 'Assemblea', 'articolo_cts' => 'art. 26 CTS'],
                ['numero_articolo' => '7', 'titolo' => 'Consiglio Direttivo', 'articolo_cts' => 'art. 26 CTS'],
                ['numero_articolo' => '8', 'titolo' => 'Bilancio', 'articolo_cts' => 'art. 13 CTS'],
            ];

            foreach ($clausole as $i => $c) {
                $statuto->clausole()->firstOrCreate(
                    ['numero_articolo' => $c['numero_articolo'], 'statuto_id' => $statuto->id],
                    array_merge([
                        'tenant_id'    => $tenant->id,
                        'testo'        => 'Articolo ' . $c['numero_articolo'] . ': ' . $c['titolo'],
                        'compliance_ok' => true,
                        'ordine'       => $i,
                    ], $c)
                );
            }

            $this->command->info("✓ Statuto e {$statuto->clausole->count()} clausole creati");

            // ─────────────────────────────────────────────────────────────
            // ATTO COSTITUTIVO
            // ─────────────────────────────────────────────────────────────

            EtsAttoCostituivo::firstOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'tenant_id'              => $tenant->id,
                    'notaio'                 => 'Dott. Franco Notari',
                    'repertorio'             => '12345/2023',
                    'data_atto'              => now()->subYear(),
                    'data_registrazione_ae'  => now()->subYear()->addDays(10),
                    'ufficio_registro'       => 'Agenzia Entrate - Ufficio Registro',
                    'numero_registro'        => '2023/12345',
                    'stato'                  => 'registrato',
                    'note'                   => 'Atto notarile costitutivo dell\'ente',
                ]
            );

            $this->command->info("✓ Atto costitutivo creato");

            // ─────────────────────────────────────────────────────────────
            // COMPLIANCE CHECK
            // ─────────────────────────────────────────────────────────────

            $engine = new ComplianceRuleEngine();
            $engine->esegui($tenant, User::where('is_super_admin', true)->first());

            $this->command->info("✓ Compliance check eseguito");
        }

        $this->command->info("✅ Test data completato per {$tenant->name}");
    }
}
