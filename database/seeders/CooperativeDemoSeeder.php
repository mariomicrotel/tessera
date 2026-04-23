<?php

namespace Database\Seeders;

use App\Models\Conto;
use App\Models\CooperativeShare;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\PrimaNotaEntry;
use App\Models\PrestitoSocialeLibretto;
use App\Models\PrestitoSocialeMovimento;
use App\Models\Ristorno;
use App\Models\RistornoEntry;
use App\Models\Settings;
use App\Models\Spesa;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RendicontoCassaSchemaCooperativa;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder demo per "Cooperativa Lavoro Insieme" (tenant "cooperativa-test").
 *
 * Crea / aggiorna:
 *  - Tenant di tipo cooperative + impostazioni
 *  - Utente admin cooperativa (se non esiste)
 *  - 3 conti (cassa, banca, banca2)
 *  - 10 soci lavoratori + 2 soci sovventori
 *  - Quote capitale sociale (versate / parzialmente versate / sottoscritte)
 *  - 3 libretti di prestito sociale con movimenti
 *  - Incassi: quote annuali, versamenti capitale, depositi prestito, proventi
 *  - Spese: personale, servizi, affitti, oneri finanziari
 *  - Movimenti manuali prima nota (saldi apertura, accantonamenti riserve)
 *  - Ristorno 2024 deliberato + liquidato, ristorno 2025 deliberato
 *
 * Idempotente: usa firstOrCreate / skip se già esistente.
 * Esegui: php artisan db:seed --class=CooperativeDemoSeeder
 */
class CooperativeDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tenant ───────────────────────────────────────────────────────────
        $tenant = Tenant::where('slug', 'cooperativa-test')->first();
        if (! $tenant) {
            $tenant = Tenant::create([
                'id'                => (string) Str::uuid(),
                'name'              => 'Cooperativa Lavoro Insieme',
                'slug'              => 'cooperativa-test',
                'organization_type' => 'cooperative',
                'cooperative_type'  => 'lavoro',
                'is_active'         => true,
                'codice_fiscale'    => '12345678901',
                'partita_iva'       => '12345678901',
                'capitale_sottoscritto' => 25000.00,
                'capitale_versato'      => 22000.00,
            ]);
            $this->command->info('Tenant cooperativa creato.');
        } else {
            $tenant->update([
                'name'              => 'Cooperativa Lavoro Insieme',
                'organization_type' => 'cooperative',
                'cooperative_type'  => 'lavoro',
                'capitale_sottoscritto' => 25000.00,
                'capitale_versato'      => 22000.00,
            ]);
        }

        app()->instance('current_tenant', $tenant);

        // ── Impostazioni ──────────────────────────────────────────────────────
        Settings::set('nome_associazione', 'Cooperativa Lavoro Insieme s.c.r.l.');
        Settings::set('indirizzo_associazione', 'Via dell\'Industria 45, 10127 Torino (TO)');
        Settings::set('codice_fiscale_associazione', '12345678901');
        Settings::set('quota_annuale', 120);
        Settings::set('causale_default_quota', 'Quota associativa annuale');
        Settings::set('causale_default_donazione', 'Contributo liberale');
        Settings::set('causale_default_rimborso', 'Rimborso spese');

        // ── Utente admin ───────────────────────────────────────────────────────
        $adminUser = User::where('email', 'coop@example.com')->first();
        if (! $adminUser) {
            $adminUser = User::create([
                'name'     => 'Admin Cooperativa',
                'email'    => 'coop@example.com',
                'password' => Hash::make('password'),
            ]);
            $this->command->info('Utente coop@example.com creato (password: password)');
        }
        // Collega al tenant se non già collegato
        if (! $adminUser->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $adminUser->tenants()->attach($tenant->id, ['role' => 'admin']);
        }

        // ── Conti ──────────────────────────────────────────────────────────────
        $contoCassa = Conto::firstOrCreate(
            ['name' => 'Cassa', 'tenant_id' => $tenant->id],
            ['code' => 'CASSA', 'type' => 'cassa', 'ordine' => 1, 'attivo' => true],
        );
        $contoBanca = Conto::firstOrCreate(
            ['name' => 'Banca c/c UniCredit', 'tenant_id' => $tenant->id],
            ['code' => 'BANCA_UC', 'type' => 'banca', 'iban' => 'IT60 X0200 8011 0000 1234 5678 901', 'ordine' => 2, 'attivo' => true],
        );
        $contoBanca2 = Conto::firstOrCreate(
            ['name' => 'Banca c/c Prestiti Soci', 'tenant_id' => $tenant->id],
            ['code' => 'BANCA_PS', 'type' => 'banca', 'ordine' => 3, 'attivo' => true],
        );

        // ── Tipi socio ─────────────────────────────────────────────────────────
        $tipoLavoratore  = MemberType::firstOrCreate(['name' => 'socio_lavoratore'],  ['display_name' => 'Socio Lavoratore']);
        $tipoSovventore  = MemberType::firstOrCreate(['name' => 'socio_sovventore'],  ['display_name' => 'Socio Sovventore']);

        // ── Soci ───────────────────────────────────────────────────────────────
        $maxTessera = Member::where('tenant_id', $tenant->id)->max('numero_tessera') ?? 0;

        $sociDati = [
            // Soci lavoratori
            ['nome' => 'Alessandro', 'cognome' => 'Vitale',    'email' => 'a.vitale@coop-demo.it',    'cf' => 'VTLSSD80A01L219X', 'tipo' => $tipoLavoratore, 'quote' => 20, 'val' => 500.00, 'versato' => 10000.00],
            ['nome' => 'Beatrice',   'cognome' => 'Fontana',   'email' => 'b.fontana@coop-demo.it',   'cf' => 'FNTBRC82B42F205P', 'tipo' => $tipoLavoratore, 'quote' => 15, 'val' => 500.00, 'versato' => 7500.00],
            ['nome' => 'Carmine',    'cognome' => 'Pagano',    'email' => 'c.pagano@coop-demo.it',    'cf' => 'PGNCMN77C01H501Z', 'tipo' => $tipoLavoratore, 'quote' => 10, 'val' => 500.00, 'versato' => 5000.00],
            ['nome' => 'Diana',      'cognome' => 'Colombo',   'email' => 'd.colombo@coop-demo.it',   'cf' => 'CLMDNI85D52G224B', 'tipo' => $tipoLavoratore, 'quote' => 10, 'val' => 500.00, 'versato' => 4500.00],
            ['nome' => 'Emanuele',   'cognome' => 'Serra',     'email' => 'e.serra@coop-demo.it',     'cf' => 'SRRMML90E01L219N', 'tipo' => $tipoLavoratore, 'quote' => 8,  'val' => 500.00, 'versato' => 4000.00],
            ['nome' => 'Federica',   'cognome' => 'Barbieri',  'email' => 'f.barbieri@coop-demo.it',  'cf' => 'BRBFRC88F50D969P', 'tipo' => $tipoLavoratore, 'quote' => 8,  'val' => 500.00, 'versato' => 4000.00],
            ['nome' => 'Giorgio',    'cognome' => 'Mancini',   'email' => 'g.mancini@coop-demo.it',   'cf' => 'MNCGRG75G01C351Q', 'tipo' => $tipoLavoratore, 'quote' => 6,  'val' => 500.00, 'versato' => 2500.00],
            ['nome' => 'Helena',     'cognome' => 'Marchetti', 'email' => 'h.marchetti@coop-demo.it', 'cf' => 'MRCHLN92H45C351M', 'tipo' => $tipoLavoratore, 'quote' => 5,  'val' => 500.00, 'versato' => 2500.00],
            ['nome' => 'Ivan',       'cognome' => 'Testa',     'email' => 'i.testa@coop-demo.it',     'cf' => 'TSTIVN86I01L219B', 'tipo' => $tipoLavoratore, 'quote' => 4,  'val' => 500.00, 'versato' => 2000.00],
            ['nome' => 'Jessica',    'cognome' => 'Riva',      'email' => 'j.riva@coop-demo.it',      'cf' => 'RVAJSC91I51A794K', 'tipo' => $tipoLavoratore, 'quote' => 4,  'val' => 500.00, 'versato' => 1500.00],
            // Soci sovventori
            ['nome' => 'Kapital',    'cognome' => 'Invest Srl','email' => 'kapital@coop-demo.it',     'cf' => '02345678901',       'tipo' => $tipoSovventore, 'quote' => 20, 'val' => 500.00, 'versato' => 10000.00],
            ['nome' => 'Luciana',    'cognome' => 'Amadori',   'email' => 'l.amadori@coop-demo.it',   'cf' => 'MDRLCN55L58L219X', 'tipo' => $tipoSovventore, 'quote' => 10, 'val' => 500.00, 'versato' => 5000.00],
        ];

        $memberModels = [];
        foreach ($sociDati as $idx => $s) {
            $existing = Member::where('tenant_id', $tenant->id)->where('email', $s['email'])->first();
            if ($existing) {
                $memberModels[] = $existing;
                continue;
            }
            $member = Member::create([
                'tenant_id'       => $tenant->id,
                'member_type_id'  => $s['tipo']->id,
                'numero_tessera'  => $maxTessera + $idx + 1,
                'nome'            => $s['nome'],
                'cognome'         => $s['cognome'],
                'email'           => $s['email'],
                'codice_fiscale'  => $s['cf'],
                'data_iscrizione' => '2022-03-15',
                'stato'           => 'socio_attivo',
            ]);
            $memberModels[] = $member;
        }

        // ── Capitale sociale (CooperativeShare) ───────────────────────────────
        foreach ($sociDati as $i => $s) {
            $member = $memberModels[$i];
            $existing = CooperativeShare::where('tenant_id', $tenant->id)
                ->where('member_id', $member->id)
                ->first();
            if ($existing) continue;

            $sottoscritto = $s['quote'] * $s['val'];
            $versato      = min($s['versato'], $sottoscritto);
            $status       = match (true) {
                $versato >= $sottoscritto               => 'versata',
                $versato > 0 && $versato < $sottoscritto => 'parzialmente_versata',
                default                                 => 'sottoscritta',
            };

            CooperativeShare::create([
                'tenant_id'          => $tenant->id,
                'member_id'          => $member->id,
                'numero_quote'       => $s['quote'],
                'valore_unitario'    => $s['val'],
                'totale_sottoscritto' => $sottoscritto,
                'totale_versato'     => $versato,
                'data_sottoscrizione' => '2022-03-15',
                'data_versamento'    => $status === 'versata' ? '2022-06-30' : null,
                'status'             => $status,
            ]);

            // Incasso tipo capitale + prima nota A01
            if ($versato > 0) {
                $incasso = Incasso::create([
                    'tenant_id'        => $tenant->id,
                    'member_id'        => $member->id,
                    'type'             => Incasso::TYPE_CAPITALE,
                    'amount'           => $versato,
                    'paid_at'          => '2022-04-01',
                    'conto_id'         => $contoBanca->id,
                    'description'      => 'Versamento capitale sociale – ' . $member->cognome . ' ' . $member->nome,
                    'genera_prima_nota' => true,
                ]);
                PrimaNotaEntry::create([
                    'tenant_id'        => $tenant->id,
                    'conto_id'         => $contoBanca->id,
                    'rendiconto_code'  => RendicontoCassaSchemaCooperativa::CODE_CAPITALE_SOCIALE,
                    'entryable_type'   => Incasso::class,
                    'entryable_id'     => $incasso->id,
                    'date'             => '2022-04-01',
                    'amount'           => $versato,
                    'description'      => $incasso->description,
                    'gestione'         => 'istituzionale',
                    'competenza_cassa' => true,
                ]);
            }
        }

        // ── Quote associative annuali (incassi tipo quota) ─────────────────────
        $quoteAnnuali = [
            // 2024
            [0, '2024-01-15', 120.00], [1, '2024-01-20', 120.00], [2, '2024-02-02', 120.00],
            [3, '2024-02-10', 120.00], [4, '2024-03-01', 120.00], [5, '2024-03-05', 120.00],
            [6, '2024-04-10', 120.00], [7, '2024-04-15', 120.00], [8, '2024-05-02', 120.00],
            [9, '2024-05-10', 120.00],
            // 2025
            [0, '2025-01-10', 120.00], [1, '2025-01-18', 120.00], [2, '2025-02-03', 120.00],
            [3, '2025-02-08', 120.00], [4, '2025-03-01', 120.00], [5, '2025-03-07', 120.00],
            [6, '2025-04-05', 120.00], [7, '2025-04-12', 120.00],
        ];
        foreach ($quoteAnnuali as [$midx, $date, $amount]) {
            $member = $memberModels[$midx];
            $year   = substr($date, 0, 4);
            $exists = Incasso::where('tenant_id', $tenant->id)
                ->where('member_id', $member->id)->where('type', 'quota')
                ->whereYear('paid_at', $year)->exists();
            if ($exists) continue;

            $incasso = Incasso::create([
                'tenant_id'        => $tenant->id,
                'member_id'        => $member->id,
                'type'             => Incasso::TYPE_QUOTA,
                'amount'           => $amount,
                'paid_at'          => $date,
                'conto_id'         => $contoCassa->id,
                'description'      => "Quota associativa {$year} – {$member->cognome} {$member->nome}",
                'genera_prima_nota' => true,
            ]);
            PrimaNotaEntry::create([
                'tenant_id'        => $tenant->id,
                'conto_id'         => $contoCassa->id,
                'rendiconto_code'  => RendicontoCassaSchemaCooperativa::CODE_QUOTA,
                'entryable_type'   => Incasso::class,
                'entryable_id'     => $incasso->id,
                'date'             => $date,
                'amount'           => $amount,
                'description'      => $incasso->description,
                'gestione'         => 'istituzionale',
                'competenza_cassa' => true,
            ]);
        }

        // ── Proventi mutualistici (attività della cooperativa) ─────────────────
        $proventi = [
            ['2024-03-31', 8500.00, 'Corrispettivi servizi Q1 2024'],
            ['2024-06-30', 9200.00, 'Corrispettivi servizi Q2 2024'],
            ['2024-09-30', 7800.00, 'Corrispettivi servizi Q3 2024'],
            ['2024-12-31', 8100.00, 'Corrispettivi servizi Q4 2024'],
            ['2025-03-31', 9400.00, 'Corrispettivi servizi Q1 2025'],
            ['2025-06-30', 8900.00, 'Corrispettivi servizi Q2 2025'],
        ];
        foreach ($proventi as [$date, $amount, $desc]) {
            $exists = PrimaNotaEntry::where('tenant_id', $tenant->id)
                ->where('description', $desc)->exists();
            if ($exists) continue;
            PrimaNotaEntry::create([
                'tenant_id'        => $tenant->id,
                'conto_id'         => $contoBanca->id,
                'rendiconto_code'  => RendicontoCassaSchemaCooperativa::CODE_PROVENTI_MUTUALISTICI,
                'date'             => $date,
                'amount'           => $amount,
                'description'      => $desc,
                'gestione'         => 'istituzionale',
                'competenza_cassa' => true,
            ]);
        }

        // ── Spese operative ────────────────────────────────────────────────────
        $spese = [
            // Personale
            ['2024-01-31', 2800.00, $contoBanca, 'B03', 'Stipendi gennaio 2024',          'istituzionale'],
            ['2024-02-28', 2800.00, $contoBanca, 'B03', 'Stipendi febbraio 2024',          'istituzionale'],
            ['2024-03-31', 2800.00, $contoBanca, 'B03', 'Stipendi marzo 2024',             'istituzionale'],
            ['2024-04-30', 2800.00, $contoBanca, 'B03', 'Stipendi aprile 2024',            'istituzionale'],
            ['2024-05-31', 2800.00, $contoBanca, 'B03', 'Stipendi maggio 2024',            'istituzionale'],
            ['2024-06-30', 3200.00, $contoBanca, 'B03', 'Stipendi + TFR Q2 giugno 2024',  'istituzionale'],
            ['2025-01-31', 3000.00, $contoBanca, 'B03', 'Stipendi gennaio 2025',           'istituzionale'],
            ['2025-02-28', 3000.00, $contoBanca, 'B03', 'Stipendi febbraio 2025',          'istituzionale'],
            ['2025-03-31', 3000.00, $contoBanca, 'B03', 'Stipendi marzo 2025',             'istituzionale'],
            // Servizi
            ['2024-01-15', 450.00, $contoBanca,  'B02', 'Software gestionale (canone annuo)', 'istituzionale'],
            ['2024-02-20', 280.00, $contoBanca,  'B02', 'Consulenza contabile Q1 2024',        'istituzionale'],
            ['2024-08-15', 280.00, $contoBanca,  'B02', 'Consulenza contabile Q3 2024',        'istituzionale'],
            ['2025-02-15', 310.00, $contoBanca,  'B02', 'Consulenza contabile Q1 2025',        'istituzionale'],
            // Affitti
            ['2024-01-05', 800.00, $contoBanca,  'B04', 'Affitto sede gennaio 2024',   'istituzionale'],
            ['2024-02-05', 800.00, $contoBanca,  'B04', 'Affitto sede febbraio 2024',  'istituzionale'],
            ['2024-03-05', 800.00, $contoBanca,  'B04', 'Affitto sede marzo 2024',     'istituzionale'],
            ['2025-01-05', 850.00, $contoBanca,  'B04', 'Affitto sede gennaio 2025',   'istituzionale'],
            ['2025-02-05', 850.00, $contoBanca,  'B04', 'Affitto sede febbraio 2025',  'istituzionale'],
            ['2025-03-05', 850.00, $contoBanca,  'B04', 'Affitto sede marzo 2025',     'istituzionale'],
            // Oneri finanziari (interessi passivi prestito sociale)
            ['2024-12-31', 620.00, $contoBanca2, 'B06', 'Interessi passivi prestito sociale 2024', 'istituzionale'],
            // Tasse
            ['2024-11-30', 950.00, $contoBanca,  'B10', 'IRAP 2024 – saldo',           'istituzionale'],
            ['2025-06-30', 200.00, $contoBanca,  'B10', 'IRAP 2025 – acconto',          'istituzionale'],
        ];

        foreach ($spese as [$date, $amount, $conto, $code, $desc, $gestione]) {
            $exists = Spesa::where('tenant_id', $tenant->id)
                ->whereDate('date', $date)->where('description', $desc)->exists();
            if ($exists) continue;

            $spesa = Spesa::create([
                'tenant_id'         => $tenant->id,
                'date'              => $date,
                'amount'            => $amount,
                'conto_id'          => $conto->id,
                'rendiconto_code'   => $code,
                'description'       => $desc,
                'genera_prima_nota' => true,
                'gestione'          => $gestione,
                'competenza_cassa'  => true,
            ]);
            PrimaNotaEntry::create([
                'tenant_id'        => $tenant->id,
                'conto_id'         => $conto->id,
                'rendiconto_code'  => $code,
                'entryable_type'   => Spesa::class,
                'entryable_id'     => $spesa->id,
                'date'             => $date,
                'amount'           => -abs($amount),
                'description'      => $desc,
                'gestione'         => $gestione,
                'competenza_cassa' => true,
            ]);
        }

        // ── Prestito Sociale (libretti + movimenti) ────────────────────────────
        // Soci 0, 1, 2 hanno un libretto
        $libretti = [
            ['midx' => 0, 'numero' => 'PS-001', 'tasso' => 0.025, 'apertura' => '2023-01-10'],
            ['midx' => 1, 'numero' => 'PS-002', 'tasso' => 0.025, 'apertura' => '2023-03-15'],
            ['midx' => 2, 'numero' => 'PS-003', 'tasso' => 0.020, 'apertura' => '2024-01-20'],
        ];

        $movimentiPS = [
            'PS-001' => [
                // [data, tipo, importo, segno]
                ['2023-01-10', 'deposito', 5000.00, 'avere'],
                ['2023-06-15', 'deposito', 2000.00, 'avere'],
                ['2024-01-10', 'deposito', 1000.00, 'avere'],
                ['2024-12-31', 'interessi', 200.00, 'avere'],
            ],
            'PS-002' => [
                ['2023-03-15', 'deposito', 3000.00, 'avere'],
                ['2023-10-01', 'prelievo', 500.00, 'dare'],
                ['2024-12-31', 'interessi', 120.00, 'avere'],
            ],
            'PS-003' => [
                ['2024-01-20', 'deposito', 2500.00, 'avere'],
                ['2024-07-01', 'deposito', 1000.00, 'avere'],
                ['2024-12-31', 'interessi', 70.00, 'avere'],
            ],
        ];

        foreach ($libretti as $lb) {
            $member = $memberModels[$lb['midx']];
            $existingLib = PrestitoSocialeLibretto::where('tenant_id', $tenant->id)
                ->where('numero_libretto', $lb['numero'])->first();
            if ($existingLib) continue;

            // Calcola saldo da movimenti
            $movs = $movimentiPS[$lb['numero']];
            $saldo = 0.0;
            foreach ($movs as $mv) {
                if ($mv[3] === 'avere') {
                    $saldo += $mv[2];
                } else {
                    $saldo -= $mv[2];
                }
            }

            $libretto = PrestitoSocialeLibretto::create([
                'tenant_id'             => $tenant->id,
                'member_id'             => $member->id,
                'numero_libretto'       => $lb['numero'],
                'saldo_attuale'         => round($saldo, 2),
                'tasso_interesse_annuo' => $lb['tasso'],
                'data_apertura'         => $lb['apertura'],
                'status'                => 'attivo',
            ]);

            // Movimenti
            $saldoProg = 0.0;
            foreach ($movs as $mv) {
                [$data, $tipo, $importo, $segno] = $mv;
                if ($segno === 'avere') {
                    $saldoProg += $importo;
                } else {
                    $saldoProg -= $importo;
                }
                $mov = PrestitoSocialeMovimento::create([
                    'tenant_id'          => $tenant->id,
                    'libretto_id'        => $libretto->id,
                    'tipo'               => $tipo,
                    'importo'            => $importo,
                    'segno'              => $segno,
                    'saldo_dopo'         => round($saldoProg, 2),
                    'data_valuta'        => $data,
                    'data_registrazione' => $data,
                    'anno_competenza'    => (int) substr($data, 0, 4),
                    'mese_competenza'    => (int) substr($data, 5, 2),
                    'aliquota_ritenuta'  => $tipo === 'interessi' ? 0.26 : null,
                    'importo_ritenuta'   => $tipo === 'interessi' ? round($importo * 0.26, 2) : null,
                    'importo_netto'      => $tipo === 'interessi' ? round($importo * 0.74, 2) : null,
                    'descrizione'        => ucfirst($tipo) . ' libretto ' . $lb['numero'],
                ]);

                // Incasso per deposito
                if ($tipo === 'deposito') {
                    $incasso = Incasso::create([
                        'tenant_id'        => $tenant->id,
                        'member_id'        => $member->id,
                        'type'             => Incasso::TYPE_PRESTITO_SOCIALE,
                        'amount'           => $importo,
                        'paid_at'          => $data,
                        'conto_id'         => $contoBanca2->id,
                        'description'      => 'Deposito prestito sociale – ' . $lb['numero'] . ' – ' . $member->cognome,
                        'genera_prima_nota' => true,
                    ]);
                    PrimaNotaEntry::create([
                        'tenant_id'        => $tenant->id,
                        'conto_id'         => $contoBanca2->id,
                        'rendiconto_code'  => RendicontoCassaSchemaCooperativa::CODE_CAPITALE_SOCIALE,
                        'entryable_type'   => Incasso::class,
                        'entryable_id'     => $incasso->id,
                        'date'             => $data,
                        'amount'           => $importo,
                        'description'      => $incasso->description,
                        'gestione'         => 'istituzionale',
                        'competenza_cassa' => true,
                    ]);
                }
            }
        }

        // ── Movimenti manuali di apertura e riserve ────────────────────────────
        $manuali = [
            ['2024-01-01', $contoBanca,  'A09', 5200.00,  'Saldo apertura anno 2024 – banca UC'],
            ['2024-01-01', $contoCassa,  'A09', 800.00,   'Saldo apertura anno 2024 – cassa'],
            ['2025-01-01', $contoBanca,  'A09', 7100.00,  'Saldo apertura anno 2025 – banca UC'],
            ['2025-01-01', $contoCassa,  'A09', 650.00,   'Saldo apertura anno 2025 – cassa'],
            // Accantonamento riserva legale 2024
            ['2024-12-31', $contoBanca,  'B08', -1200.00, 'Accantonamento riserva legale 2024 (30% utili)'],
            // Accantonamento riserva indivisibile 2024
            ['2024-12-31', $contoBanca,  'B09', -360.00,  'Accantonamento riserva indivisibile 2024 (3% utili)'],
        ];

        foreach ($manuali as [$date, $conto, $code, $amount, $desc]) {
            $exists = PrimaNotaEntry::where('tenant_id', $tenant->id)
                ->where('description', $desc)->exists();
            if ($exists) continue;
            PrimaNotaEntry::create([
                'tenant_id'        => $tenant->id,
                'conto_id'         => $conto->id,
                'rendiconto_code'  => $code,
                'date'             => $date,
                'amount'           => $amount,
                'description'      => $desc,
                'gestione'         => 'istituzionale',
                'competenza_cassa' => true,
            ]);
        }

        // ── Ristorni ──────────────────────────────────────────────────────────
        // Ristorno 2024 (liquidato)
        $existsRist2024 = Ristorno::where('tenant_id', $tenant->id)->where('anno', 2024)->exists();
        if (! $existsRist2024) {
            $importoTotale2024 = 4000.00;
            $aliquota = 0.30;

            $ristorno2024 = Ristorno::create([
                'tenant_id'                  => $tenant->id,
                'anno'                       => 2024,
                'importo_totale_deliberato'  => $importoTotale2024,
                'aliquota_ritenuta'          => $aliquota,
                'data_delibera_assemblea'    => '2025-03-20',
                'data_pagamento'             => '2025-04-05',
                'status'                     => Ristorno::STATUS_PAGATO,
                'note'                       => 'Delibera assemblea soci del 20/03/2025. Ristorni proporzionali alle ore lavorate nel 2024.',
            ]);

            // Entries per i soci lavoratori (idx 0-9)
            $ripartizionePerc = [15, 12, 11, 10, 10, 10, 8, 8, 8, 8]; // percentuali (somma = 100)
            foreach (array_slice($memberModels, 0, 10) as $i => $m) {
                $lordo = round($importoTotale2024 * $ripartizionePerc[$i] / 100, 2);
                $calc  = RistornoEntry::calcolaFromLordo($lordo, $aliquota);
                RistornoEntry::create(array_merge($calc, [
                    'tenant_id'   => $tenant->id,
                    'ristorno_id' => $ristorno2024->id,
                    'member_id'   => $m->id,
                    'status'      => RistornoEntry::STATUS_PAGATO,
                    'data_pagamento' => '2025-04-05',
                ]));
            }

            // Prima nota B07 uscita
            PrimaNotaEntry::create([
                'tenant_id'        => $tenant->id,
                'conto_id'         => $contoBanca->id,
                'rendiconto_code'  => RendicontoCassaSchemaCooperativa::CODE_RISTORNI_SOCI,
                'entryable_type'   => Ristorno::class,
                'entryable_id'     => $ristorno2024->id,
                'date'             => '2025-04-05',
                'amount'           => -abs($importoTotale2024),
                'description'      => 'Ristorno ai soci anno 2024 (delibera 20/03/2025)',
                'gestione'         => 'istituzionale',
                'competenza_cassa' => true,
            ]);
        }

        // Ristorno 2025 (solo deliberato, non ancora pagato)
        $existsRist2025 = Ristorno::where('tenant_id', $tenant->id)->where('anno', 2025)->exists();
        if (! $existsRist2025) {
            $importoTotale2025 = 4500.00;
            $aliquota = 0.30;

            $ristorno2025 = Ristorno::create([
                'tenant_id'                  => $tenant->id,
                'anno'                       => 2025,
                'importo_totale_deliberato'  => $importoTotale2025,
                'aliquota_ritenuta'          => $aliquota,
                'data_delibera_assemblea'    => '2026-04-10',
                'status'                     => Ristorno::STATUS_DELIBERATO,
                'note'                       => 'Delibera assemblea soci del 10/04/2026. In attesa di liquidazione.',
            ]);

            $ripartizionePerc = [15, 12, 11, 10, 10, 10, 8, 8, 8, 8];
            foreach (array_slice($memberModels, 0, 10) as $i => $m) {
                $lordo = round($importoTotale2025 * $ripartizionePerc[$i] / 100, 2);
                $calc  = RistornoEntry::calcolaFromLordo($lordo, $aliquota);
                RistornoEntry::create(array_merge($calc, [
                    'tenant_id'   => $tenant->id,
                    'ristorno_id' => $ristorno2025->id,
                    'member_id'   => $m->id,
                    'status'      => RistornoEntry::STATUS_DELIBERATO,
                ]));
            }
        }

        // ── Riepilogo ─────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('✓ CooperativeDemoSeeder completato:');
        $this->command->info('  Tenant: ' . $tenant->name . ' (' . $tenant->cooperative_type . ')');
        $this->command->info('  Login: coop@example.com / password');
        $this->command->info('  Soci: ' . Member::where('tenant_id', $tenant->id)->count());
        $this->command->info('  Quote capitale: ' . CooperativeShare::where('tenant_id', $tenant->id)->count());
        $this->command->info('  Libretti prestito: ' . PrestitoSocialeLibretto::where('tenant_id', $tenant->id)->count());
        $this->command->info('  Incassi: ' . Incasso::where('tenant_id', $tenant->id)->count());
        $this->command->info('  Spese: ' . Spesa::where('tenant_id', $tenant->id)->count());
        $this->command->info('  Prima nota: ' . PrimaNotaEntry::where('tenant_id', $tenant->id)->count() . ' movimenti');
        $this->command->info('  Ristorni: ' . Ristorno::where('tenant_id', $tenant->id)->count() . ' (2024 pagato + 2025 deliberato)');
    }
}
