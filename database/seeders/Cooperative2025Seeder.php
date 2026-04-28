<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\ContoContabile;
use App\Models\MovimentoContabile;
use App\Models\RigaMovimentoContabile;
use App\Models\CooperativeShare;
use App\Models\PrestitoSocialeLibretto;
use App\Models\PrestitoSocialeMovimento;
use App\Models\Ristorno;
use App\Models\RistornoEntry;
use App\Models\Settings;
use App\Models\Incasso;
use App\Services\CapitaleSocialeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class Cooperative2025Seeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Inizio seeding Cooperative 2025...');

        // ────────────────────────────────────────────────────────────────────
        // 1. TENANT E UTENTI
        // ────────────────────────────────────────────────────────────────────

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'cooperativa-2025'],
            [
                'name' => 'Cooperativa di Lavoro 2025',
                'organization_type' => 'cooperative',
                'cooperative_type' => 'lavoro',
                'codice_fiscale' => '98765432109876',
                'partita_iva' => '98765432109',
                'is_active' => true,
            ]
        );

        $this->command->info("✓ Tenant: {$tenant->name}");

        // Binding tenant
        app()->instance('current_tenant', $tenant);

        // Admin user (già attivato)
        $admin = User::firstOrCreate(
            ['email' => 'admin-2025@cooperativa.local'],
            [
                'name' => 'Admin 2025',
                'email_verified_at' => now(),  // ✓ Account attivato
                'password' => Hash::make('password'),
            ]
        );

        // Se l'utente esiste ma non è attivato, attivalo
        if (!$admin->email_verified_at) {
            $admin->update(['email_verified_at' => now()]);
        }

        $tenant->users()->syncWithoutDetaching([$admin->id => ['role' => 'admin']]);
        $this->command->info("✓ Utente admin: {$admin->email}");

        // ────────────────────────────────────────────────────────────────────
        // 2. ESERCIZI CONTABILI
        // ────────────────────────────────────────────────────────────────────

        $esercizio2025 = \App\Models\EsercizioContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'anno' => 2025],
            [
                'stato' => 'aperto',
                'data_apertura' => Carbon::parse('2025-01-01'),
                'data_chiusura' => null,
            ]
        );

        $this->command->info("✓ Esercizio 2025 creato");

        // ────────────────────────────────────────────────────────────────────
        // 3. TIPI SOCIO
        // ────────────────────────────────────────────────────────────────────

        $type_worker = MemberType::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Socio Lavoratore'],
            ['display_name' => 'Socio Lavoratore']
        );

        $type_support = MemberType::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Socio Sostenitore'],
            ['display_name' => 'Socio Sostenitore']
        );

        // ────────────────────────────────────────────────────────────────────
        // 4. SOCI (15 totali: 12 lavoratori + 3 sostenitori)
        // ────────────────────────────────────────────────────────────────────

        $this->command->info('');
        $this->command->info('👥 Creazione soci...');

        $members = [];
        $nomi_cognomi = [
            ['Marco', 'Rossi'],
            ['Anna', 'Bianchi'],
            ['Paolo', 'Verdi'],
            ['Giulia', 'Ferrari'],
            ['Luigi', 'Russo'],
            ['Maria', 'Giordano'],
            ['Antonio', 'Coppola'],
            ['Francesca', 'Ricci'],
            ['Giovanni', 'Morelli'],
            ['Elena', 'Rizzo'],
            ['Carlo', 'Greco'],
            ['Laura', 'Bruno'],
            ['Roberto', 'Gallo'],
            ['Silvia', 'Lombardi'],
            ['Matteo', 'Conti'],
        ];

        for ($i = 0; $i < 15; $i++) {
            [$nome, $cognome] = $nomi_cognomi[$i];
            $is_worker = $i < 12;
            $tipo = $is_worker ? $type_worker : $type_support;

            $member = Member::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'email' => strtolower($nome . '.' . $cognome . '@cooperativa.local'),
                ],
                [
                    'nome' => $nome,
                    'cognome' => $cognome,
                    'codice_fiscale' => strtoupper(
                        substr($cognome, 0, 3) . substr($nome, 0, 3) . '85' . sprintf('%03d', $i + 1)
                    ),
                    'member_type_id' => $tipo->id,
                    'data_iscrizione' => Carbon::parse('2023-01-15'),
                    'stato' => 'attivo',
                    'socio_lavoratore' => $is_worker,
                ]
            );

            $members[$i] = $member;
            $this->command->info("  ✓ {$nome} {$cognome}");
        }

        // ────────────────────────────────────────────────────────────────────
        // 5. SOTTOSCRIZIONE QUOTE (10 quote da €50 = €500 per socio)
        // ────────────────────────────────────────────────────────────────────

        $this->command->info('');
        $this->command->info('📋 Sottoscrizione quote di capitale...');

        $service = new CapitaleSocialeService();
        foreach ($members as $member) {
            try {
                $service->sottoscriviQuote(
                    $member,
                    10,     // numero_quote
                    50.00,  // valore_unitario
                    Carbon::parse('2025-01-20')
                );
                $this->command->info("  ✓ {$member->nome} {$member->cognome}: 10 quote da €50");
            } catch (\Exception $e) {
                $this->command->warn("  ⚠ {$member->nome}: " . $e->getMessage());
            }
        }

        // ────────────────────────────────────────────────────────────────────
        // 6. PRESTITI SOCIALI (2 libretti)
        // ────────────────────────────────────────────────────────────────────

        $this->command->info('');
        $this->command->info('💳 Apertura libretti prestito sociale...');

        $prestito1 = PrestitoSocialeLibretto::firstOrCreate(
            ['tenant_id' => $tenant->id, 'numero_libretto' => 'PS-001'],
            [
                'member_id' => $members[0]->id,
                'saldo_attuale' => 3000.00,
                'tasso_interesse_annuo' => 0.02,
                'data_apertura' => Carbon::parse('2023-06-15'),
                'status' => 'attivo',
                'note' => 'Libretto per ' . $members[0]->nome,
            ]
        );

        $prestito2 = PrestitoSocialeLibretto::firstOrCreate(
            ['tenant_id' => $tenant->id, 'numero_libretto' => 'PS-002'],
            [
                'member_id' => $members[3]->id,
                'saldo_attuale' => 2000.00,
                'tasso_interesse_annuo' => 0.02,
                'data_apertura' => Carbon::parse('2024-03-20'),
                'status' => 'attivo',
                'note' => 'Libretto per ' . $members[3]->nome,
            ]
        );

        $this->command->info("  ✓ Libretto PS-001: €3000");
        $this->command->info("  ✓ Libretto PS-002: €2000");

        // Movimenti di rimborso parziale
        PrestitoSocialeMovimento::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'libretto_id' => $prestito1->id,
                'tipo' => 'prelievo',
                'data_valuta' => Carbon::parse('2025-03-15'),
            ],
            [
                'importo' => 500.00,
                'segno' => 'dare',
                'saldo_dopo' => 2500.00,
                'data_registrazione' => Carbon::parse('2025-03-15'),
                'anno_competenza' => 2025,
                'mese_competenza' => 3,
                'descrizione' => 'Rimborso parziale prestito',
            ]
        );

        PrestitoSocialeMovimento::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'libretto_id' => $prestito2->id,
                'tipo' => 'prelievo',
                'data_valuta' => Carbon::parse('2025-06-20'),
            ],
            [
                'importo' => 200.00,
                'segno' => 'dare',
                'saldo_dopo' => 1800.00,
                'data_registrazione' => Carbon::parse('2025-06-20'),
                'anno_competenza' => 2025,
                'mese_competenza' => 6,
                'descrizione' => 'Rimborso parziale prestito',
            ]
        );

        // ────────────────────────────────────────────────────────────────────
        // 7. CONTI CONTABILI ESSENZIALI
        // ────────────────────────────────────────────────────────────────────

        $this->command->info('');
        $this->command->info('📊 Creazione conti contabili...');

        $conti = [];

        // Attivo
        $conti['banca'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '1.2.2.1'],
            [
                'descrizione' => 'Conto Corrente Bancario',
                'natura' => 'transitorio',
                'segno_naturale' => 'dare',
                'livello' => 3,
                'attivo' => true,
            ]
        );

        $conti['crediti'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '1.2.1'],
            [
                'descrizione' => 'Crediti verso Clienti',
                'natura' => 'attivo',
                'segno_naturale' => 'dare',
                'livello' => 2,
                'attivo' => true,
            ]
        );

        // Passivo
        $conti['debiti'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '2.1.1'],
            [
                'descrizione' => 'Debiti verso Fornitori',
                'natura' => 'passivo',
                'segno_naturale' => 'avere',
                'livello' => 2,
                'attivo' => true,
            ]
        );

        $conti['iva_debito'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '2.1.2.1'],
            [
                'descrizione' => 'IVA a Debito',
                'natura' => 'passivo',
                'segno_naturale' => 'avere',
                'livello' => 3,
                'attivo' => true,
            ]
        );

        // Patrimonio Netto
        $conti['capitale'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '3.1.1'],
            [
                'descrizione' => 'Capitale Versato',
                'natura' => 'patrimonio_netto',
                'segno_naturale' => 'avere',
                'livello' => 2,
                'attivo' => true,
            ]
        );

        $conti['riserva'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '3.2.1'],
            [
                'descrizione' => 'Riserva Legale',
                'natura' => 'patrimonio_netto',
                'segno_naturale' => 'avere',
                'livello' => 2,
                'attivo' => true,
            ]
        );

        // Ricavi
        $conti['ricavi'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '4.1.1'],
            [
                'descrizione' => 'Ricavi da Servizi',
                'natura' => 'ricavo',
                'segno_naturale' => 'avere',
                'livello' => 2,
                'attivo' => true,
            ]
        );

        $conti['quote_ass'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '4.2.1'],
            [
                'descrizione' => 'Quote Associative',
                'natura' => 'ricavo',
                'segno_naturale' => 'avere',
                'livello' => 2,
                'attivo' => true,
            ]
        );

        // Costi
        $conti['stipendi'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '5.1.1'],
            [
                'descrizione' => 'Stipendi e Salari',
                'natura' => 'costo',
                'segno_naturale' => 'dare',
                'livello' => 2,
                'attivo' => true,
            ]
        );

        $conti['affitti'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '5.2.1'],
            [
                'descrizione' => 'Affitti e Locazioni',
                'natura' => 'costo',
                'segno_naturale' => 'dare',
                'livello' => 2,
                'attivo' => true,
            ]
        );

        $conti['utenze'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '5.2.2'],
            [
                'descrizione' => 'Utenze (Luce, Gas, Acqua)',
                'natura' => 'costo',
                'segno_naturale' => 'dare',
                'livello' => 2,
                'attivo' => true,
            ]
        );

        $conti['servizi'] = ContoContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => '5.2.3'],
            [
                'descrizione' => 'Servizi Professionali',
                'natura' => 'costo',
                'segno_naturale' => 'dare',
                'livello' => 2,
                'attivo' => true,
            ]
        );

        $this->command->info("  ✓ 13 conti contabili creati");

        // ────────────────────────────────────────────────────────────────────
        // 8. MOVIMENTI CONTABILI 2025
        // ────────────────────────────────────────────────────────────────────

        // Cerchiamo o creiamo una causale generica
        $causale = \App\Models\CausaleContabile::firstOrCreate(
            ['tenant_id' => $tenant->id, 'codice' => 'GEN'],
            [
                'descrizione' => 'Causale Generica',
                'attivo' => true,
            ]
        );

        $this->command->info('');
        $this->command->info('📝 Creazione movimenti contabili 2025...');

        $total_mov = 0;
        $numero_mov = 1;

        // Ricavi mensili
        $mesi_ricavi = [
            1 => 15000, 2 => 16000, 3 => 18000, 4 => 17000, 5 => 19000, 6 => 20000,
            7 => 18000, 8 => 16000, 9 => 19000, 10 => 21000, 11 => 22000, 12 => 24000,
        ];

        foreach ($mesi_ricavi as $month => $ricavi) {
            $data = Carbon::create(2025, $month, 15);
            $iva = $ricavi * 0.22;

            $mov = MovimentoContabile::create([
                'tenant_id' => $tenant->id,
                'anno_esercizio' => 2025,
                'numero' => $numero_mov++,
                'data_registrazione' => $data,
                'data_competenza' => $data,
                'causale_id' => $causale->id,
                'descrizione' => "Fatture Mese {$month} - Ricavi Servizi",
                'stato' => 'definitivo',
                'gestione' => 'commerciale',
            ]);

            RigaMovimentoContabile::create([
                'movimento_id' => $mov->id,
                'conto_contabile_id' => $conti['ricavi']->id,
                'importo_dare' => 0,
                'importo_avere' => $ricavi,
                'descrizione' => 'Ricavi da Servizi',
            ]);

            RigaMovimentoContabile::create([
                'movimento_id' => $mov->id,
                'conto_contabile_id' => $conti['iva_debito']->id,
                'importo_dare' => 0,
                'importo_avere' => $iva,
                'descrizione' => 'IVA a Debito 22%',
            ]);

            RigaMovimentoContabile::create([
                'movimento_id' => $mov->id,
                'conto_contabile_id' => $conti['banca']->id,
                'importo_dare' => $ricavi + $iva,
                'importo_avere' => 0,
                'descrizione' => 'Incasso da Clienti',
            ]);

            $total_mov++;
        }

        $this->command->info("  ✓ 12 movimenti ricavi creati");

        // Stipendi mensili (€36.000/mese lordo + 40% contributi)
        $stipendio_lordo = 36000;
        $contributi = $stipendio_lordo * 0.40;

        for ($month = 1; $month <= 12; $month++) {
            $data = Carbon::create(2025, $month, 25);

            $mov = MovimentoContabile::create([
                'tenant_id' => $tenant->id,
                'anno_esercizio' => 2025,
                'numero' => $numero_mov++,
                'data_registrazione' => $data,
                'data_competenza' => $data,
                'causale_id' => $causale->id,
                'descrizione' => "Stipendi Mese {$month}",
                'stato' => 'definitivo',
                'gestione' => 'commerciale',
            ]);

            RigaMovimentoContabile::create([
                'movimento_id' => $mov->id,
                'conto_contabile_id' => $conti['stipendi']->id,
                'importo_dare' => $stipendio_lordo + $contributi,
                'importo_avere' => 0,
                'descrizione' => 'Stipendi e Contributi',
            ]);

            RigaMovimentoContabile::create([
                'movimento_id' => $mov->id,
                'conto_contabile_id' => $conti['banca']->id,
                'importo_dare' => 0,
                'importo_avere' => $stipendio_lordo + $contributi,
                'descrizione' => 'Pagamento Stipendi',
            ]);

            $total_mov++;
        }

        $this->command->info("  ✓ 12 movimenti stipendi creati");

        // Affitti mensili (€850/mese)
        for ($month = 1; $month <= 12; $month++) {
            $data = Carbon::create(2025, $month, 5);
            $affitto = 850;

            $mov = MovimentoContabile::create([
                'tenant_id' => $tenant->id,
                'anno_esercizio' => 2025,
                'numero' => $numero_mov++,
                'data_registrazione' => $data,
                'data_competenza' => $data,
                'causale_id' => $causale->id,
                'descrizione' => "Affitto Locali Mese {$month}",
                'stato' => 'definitivo',
                'gestione' => 'commerciale',
            ]);

            RigaMovimentoContabile::create([
                'movimento_id' => $mov->id,
                'conto_contabile_id' => $conti['affitti']->id,
                'importo_dare' => $affitto,
                'importo_avere' => 0,
                'descrizione' => 'Affitti Locali Operativi',
            ]);

            RigaMovimentoContabile::create([
                'movimento_id' => $mov->id,
                'conto_contabile_id' => $conti['banca']->id,
                'importo_dare' => 0,
                'importo_avere' => $affitto,
                'descrizione' => 'Pagamento Affitto',
            ]);

            $total_mov++;
        }

        $this->command->info("  ✓ 12 movimenti affitti creati");

        // ────────────────────────────────────────────────────────────────────
        // 9. RISTORNO 2025 (Deliberato)
        // ────────────────────────────────────────────────────────────────────

        $this->command->info('');
        $this->command->info('💰 Creazione ristorno 2025...');

        $importo_totale = 8000;
        $aliquota_ritenuta = 0.26;

        $ristorno = Ristorno::create([
            'tenant_id' => $tenant->id,
            'anno' => 2025,
            'importo_totale_deliberato' => $importo_totale,
            'aliquota_ritenuta' => $aliquota_ritenuta,
            'data_delibera_assemblea' => Carbon::parse('2025-11-15'),
            'status' => 'deliberato',
            'note' => 'Ristorno 2025 - Deliberato in assemblea novembre',
        ]);

        // Distribuisci ai 12 soci lavoratori
        $importo_per_socio = round($importo_totale / 12, 2);
        foreach (array_slice($members, 0, 12) as $member) {
            $calcolo = RistornoEntry::calcolaFromLordo($importo_per_socio, $aliquota_ritenuta);
            RistornoEntry::create([
                'tenant_id' => $tenant->id,
                'ristorno_id' => $ristorno->id,
                'member_id' => $member->id,
                'importo_lordo' => $calcolo['importo_lordo'],
                'aliquota_ritenuta' => $calcolo['aliquota_ritenuta'],
                'importo_ritenuta' => $calcolo['importo_ritenuta'],
                'importo_netto' => $calcolo['importo_netto'],
                'status' => 'deliberato',
            ]);
        }

        $this->command->info("  ✓ Ristorno 2025: €{$importo_totale} lordi (€{$importo_per_socio} per socio)");

        // ────────────────────────────────────────────────────────────────────
        // 10. SUMMARY
        // ────────────────────────────────────────────────────────────────────

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('✅ SEEDING COMPLETATO CON SUCCESSO!');
        $this->command->info('========================================');
        $this->command->info('');
        $this->command->info('📊 DATI CARICATI:');
        $this->command->info('   👥 15 soci (12 lavoratori + 3 sostenitori)');
        $this->command->info('   📈 Quote: 150 quote da €50 = €7.500 totale');
        $this->command->info('   💳 Prestiti sociali: 2 libretti attivi');
        $this->command->info('   📝 Movimenti contabili: ' . ($total_mov + 2) . ' movimenti');
        $this->command->info('   💰 Ricavi 2025: €227.000 + IVA €49.940');
        $this->command->info('   📉 Costi 2025: Stipendi €1.680K, Affitti €10.2K');
        $this->command->info('   🏆 Ristorni 2025: €8.000 deliberati');
        $this->command->info('');
        $this->command->info('🔐 ACCESSO:');
        $this->command->info('   URL: http://localhost:8090/app/cooperativa-2025/');
        $this->command->info('   Email: admin-2025@cooperativa.local');
        $this->command->info('   Password: password');
        $this->command->info('');
    }
}
