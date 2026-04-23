<?php

namespace Database\Seeders;

use App\Models\Conto;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\PrimaNotaEntry;
use App\Models\Settings;
use App\Models\Spesa;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\RendicontoCassaSchema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder demo per "Associazione Culturale Marco Polo ETS" (tenant "default").
 *
 * Crea:
 *  - 3 conti (cassa, banca, paypal)
 *  - 12 soci con quote 2025
 *  - 5 donazioni
 *  - 12 spese variegate
 *  - 6 movimenti manuali extra (investimenti, fondi, rettifiche)
 *
 * Idempotente: usa firstOrCreate / skippa se il tenant non esiste.
 * Esegui: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tenant ────────────────────────────────────────────────────
        $tenant = Tenant::where('slug', 'default')->first();
        if (! $tenant) {
            $this->command->warn('Tenant "default" non trovato. Esegui prima l\'installer.');
            return;
        }

        // Rende il tenant corrente per BelongsToTenant
        app()->instance('current_tenant', $tenant);

        // ── Impostazioni associazione ──────────────────────────────────
        Settings::set('nome_associazione', 'Associazione Culturale Marco Polo ETS');
        Settings::set('indirizzo_associazione', 'Via della Repubblica 12, 20121 Milano (MI)');
        Settings::set('codice_fiscale_associazione', '97123456789');
        Settings::set('quota_annuale', 50);
        Settings::set('causale_default_quota', 'Quota associativa');
        Settings::set('causale_default_donazione', 'Erogazione liberale');
        Settings::set('causale_default_rimborso', 'Rimborso spese volontariato');

        // ── Conti ──────────────────────────────────────────────────────
        $contoCassa = Conto::firstOrCreate(
            ['name' => 'Cassa contanti', 'tenant_id' => $tenant->id],
            ['code' => 'CASSA', 'type' => 'cassa', 'ordine' => 1, 'attivo' => true],
        );
        $contoBanca = Conto::firstOrCreate(
            ['name' => 'Banca c/c BancoPosta', 'tenant_id' => $tenant->id],
            ['code' => 'BANCA', 'type' => 'banca', 'iban' => 'IT60 X054 2811 1010 0000 0123 456', 'ordine' => 2, 'attivo' => true],
        );
        $contoPaypal = Conto::firstOrCreate(
            ['name' => 'PayPal / Stripe', 'tenant_id' => $tenant->id],
            ['code' => 'PAYPAL', 'type' => 'banca', 'ordine' => 3, 'attivo' => true],
        );

        // ── Tipi socio ─────────────────────────────────────────────────
        $tipoSocio    = MemberType::firstOrCreate(['name' => 'socio'],        ['display_name' => 'Socio']);
        $tipoVolont   = MemberType::firstOrCreate(['name' => 'volontario'],   ['display_name' => 'Volontario']);

        // ── Soci ───────────────────────────────────────────────────────
        // Parte dal numero tessera più alto già presente per evitare conflitti unique
        $maxTessera = Member::where('tenant_id', $tenant->id)->max('numero_tessera') ?? 0;

        $soci = [
            ['nome' => 'Marco',    'cognome' => 'Bianchi',    'email' => 'marco.bianchi@demo.it',    'tipo' => $tipoSocio],
            ['nome' => 'Laura',    'cognome' => 'Rossi',      'email' => 'laura.rossi@demo.it',      'tipo' => $tipoSocio],
            ['nome' => 'Giorgio',  'cognome' => 'Ferrari',    'email' => 'giorgio.ferrari@demo.it',  'tipo' => $tipoSocio],
            ['nome' => 'Anna',     'cognome' => 'Conti',      'email' => 'anna.conti@demo.it',       'tipo' => $tipoSocio],
            ['nome' => 'Davide',   'cognome' => 'Esposito',   'email' => 'davide.esposito@demo.it',  'tipo' => $tipoSocio],
            ['nome' => 'Sofia',    'cognome' => 'Romano',     'email' => 'sofia.romano@demo.it',     'tipo' => $tipoSocio],
            ['nome' => 'Luca',     'cognome' => 'Lombardi',   'email' => 'luca.lombardi@demo.it',    'tipo' => $tipoSocio],
            ['nome' => 'Chiara',   'cognome' => 'Martini',    'email' => 'chiara.martini@demo.it',   'tipo' => $tipoVolont],
            ['nome' => 'Paolo',    'cognome' => 'Greco',      'email' => 'paolo.greco@demo.it',      'tipo' => $tipoVolont],
            ['nome' => 'Valeria',  'cognome' => 'Ricci',      'email' => 'valeria.ricci@demo.it',    'tipo' => $tipoSocio],
            ['nome' => 'Stefano',  'cognome' => 'Marino',     'email' => 'stefano.marino@demo.it',   'tipo' => $tipoSocio],
            ['nome' => 'Elena',    'cognome' => 'De Luca',    'email' => 'elena.deluca@demo.it',     'tipo' => $tipoSocio],
        ];

        $memberModels = [];
        foreach ($soci as $idx => $s) {
            $existing = Member::where('tenant_id', $tenant->id)->where('email', $s['email'])->first();
            if ($existing) {
                $memberModels[] = $existing;
                continue;
            }
            $member = Member::create([
                'email'          => $s['email'],
                'tenant_id'      => $tenant->id,
                'member_type_id' => $s['tipo']->id,
                'numero_tessera' => $maxTessera + $idx + 1,
                'nome'           => $s['nome'],
                'cognome'        => $s['cognome'],
                'data_iscrizione' => '2024-01-15',
                'stato'          => 'socio_attivo',
            ]);
            $memberModels[] = $member;
        }

        // ── Incassi (quote associative 2025) ──────────────────────────
        $quoteData = [
            ['member' => 0, 'data' => '2025-01-10', 'conto' => $contoCassa,  'amount' => 50.00],
            ['member' => 1, 'data' => '2025-01-12', 'conto' => $contoCassa,  'amount' => 50.00],
            ['member' => 2, 'data' => '2025-01-15', 'conto' => $contoBanca,  'amount' => 50.00],
            ['member' => 3, 'data' => '2025-01-20', 'conto' => $contoBanca,  'amount' => 50.00],
            ['member' => 4, 'data' => '2025-02-03', 'conto' => $contoCassa,  'amount' => 50.00],
            ['member' => 5, 'data' => '2025-02-05', 'conto' => $contoPaypal, 'amount' => 50.00],
            ['member' => 6, 'data' => '2025-02-10', 'conto' => $contoPaypal, 'amount' => 50.00],
            ['member' => 7, 'data' => '2025-03-01', 'conto' => $contoCassa,  'amount' => 50.00],
            ['member' => 8, 'data' => '2025-03-05', 'conto' => $contoBanca,  'amount' => 50.00],
            ['member' => 9, 'data' => '2025-03-10', 'conto' => $contoBanca,  'amount' => 50.00],
            ['member' => 10, 'data' => '2025-04-02', 'conto' => $contoCassa, 'amount' => 50.00],
            ['member' => 11, 'data' => '2025-04-05', 'conto' => $contoBanca, 'amount' => 50.00],
        ];

        foreach ($quoteData as $q) {
            $member = $memberModels[$q['member']];
            $existing = Incasso::where('tenant_id', $tenant->id)
                ->where('member_id', $member->id)
                ->where('type', 'quota')
                ->first();
            if ($existing) continue;

            $incasso = Incasso::create([
                'member_id'       => $member->id,
                'type'            => Incasso::TYPE_QUOTA,
                'amount'          => $q['amount'],
                'paid_at'         => $q['data'],
                'conto_id'        => $q['conto']->id,
                'description'     => 'Quota associativa 2025 – ' . $member->cognome . ' ' . $member->nome,
                'genera_prima_nota' => true,
            ]);

            PrimaNotaEntry::create([
                'conto_id'        => $q['conto']->id,
                'rendiconto_code' => RendicontoCassaSchema::CODE_QUOTA,
                'entryable_type'  => Incasso::class,
                'entryable_id'    => $incasso->id,
                'date'            => $q['data'],
                'amount'          => $q['amount'],
                'description'     => $incasso->description,
                'gestione'        => 'istituzionale',
                'competenza_cassa' => true,
            ]);
        }

        // ── Incassi (donazioni) ────────────────────────────────────────
        $donazioni = [
            ['donor' => 'Fondazione Banca Milano',   'data' => '2025-02-14', 'conto' => $contoBanca,  'amount' => 1500.00],
            ['donor' => 'Maria Giovanna Pellegrini', 'data' => '2025-03-20', 'conto' => $contoBanca,  'amount' => 300.00],
            ['donor' => 'Anonimo',                   'data' => '2025-04-01', 'conto' => $contoCassa,  'amount' => 100.00],
            ['donor' => 'Circolo Amici delle Arti',  'data' => '2025-04-08', 'conto' => $contoPaypal, 'amount' => 250.00],
            ['donor' => 'Roberto Castellani',        'data' => '2025-01-28', 'conto' => $contoBanca,  'amount' => 500.00],
        ];

        foreach ($donazioni as $d) {
            $existing = Incasso::where('tenant_id', $tenant->id)
                ->where('donor_name', $d['donor'])
                ->where('type', 'donazione')
                ->first();
            if ($existing) continue;

            $incasso = Incasso::create([
                'donor_name'      => $d['donor'],
                'type'            => Incasso::TYPE_DONAZIONE,
                'amount'          => $d['amount'],
                'paid_at'         => $d['data'],
                'conto_id'        => $d['conto']->id,
                'description'     => 'Erogazione liberale – ' . $d['donor'],
                'genera_prima_nota' => true,
            ]);

            PrimaNotaEntry::create([
                'conto_id'        => $d['conto']->id,
                'rendiconto_code' => RendicontoCassaSchema::CODE_DONAZIONE,
                'entryable_type'  => Incasso::class,
                'entryable_id'    => $incasso->id,
                'date'            => $d['data'],
                'amount'          => $d['amount'],
                'description'     => 'Erogazione liberale – ' . $d['donor'],
                'gestione'        => 'istituzionale',
                'competenza_cassa' => true,
            ]);
        }

        // ── Spese ──────────────────────────────────────────────────────
        $spese = [
            // Materiali/attrezzature
            ['date' => '2025-01-20', 'amount' => 180.00,  'conto' => $contoCassa,  'code' => 'EXP_A_1', 'desc' => 'Acquisto cartoleria e materiali per laboratorio'],
            ['date' => '2025-02-10', 'amount' => 320.00,  'conto' => $contoBanca,  'code' => 'EXP_A_1', 'desc' => 'Proiettore portatile per eventi'],
            // Servizi
            ['date' => '2025-01-31', 'amount' => 600.00,  'conto' => $contoBanca,  'code' => 'EXP_A_2', 'desc' => 'Hosting e dominio sito web (annuale)'],
            ['date' => '2025-03-15', 'amount' => 450.00,  'conto' => $contoBanca,  'code' => 'EXP_A_2', 'desc' => 'Servizi tipografia – stampa volantini evento'],
            ['date' => '2025-04-05', 'amount' => 200.00,  'conto' => $contoCassa,  'code' => 'EXP_A_2', 'desc' => 'Servizi fotografici – documentazione evento'],
            // Godimento beni di terzi (affitti)
            ['date' => '2025-01-05', 'amount' => 350.00,  'conto' => $contoBanca,  'code' => 'EXP_A_3', 'desc' => 'Affitto sala riunioni – gennaio 2025'],
            ['date' => '2025-02-05', 'amount' => 350.00,  'conto' => $contoBanca,  'code' => 'EXP_A_3', 'desc' => 'Affitto sala riunioni – febbraio 2025'],
            ['date' => '2025-03-05', 'amount' => 350.00,  'conto' => $contoBanca,  'code' => 'EXP_A_3', 'desc' => 'Affitto sala riunioni – marzo 2025'],
            // Rimborsi spese
            ['date' => '2025-02-28', 'amount' => 85.40,   'conto' => $contoCassa,  'code' => 'EXP_A_5', 'desc' => 'Rimborso spese trasferta – Conti Anna'],
            ['date' => '2025-03-25', 'amount' => 42.00,   'conto' => $contoCassa,  'code' => 'EXP_A_5', 'desc' => 'Rimborso carburante – Ferrari Giorgio'],
            // Oneri diversi (gestione commerciale)
            ['date' => '2025-03-31', 'amount' => 120.00,  'conto' => $contoBanca,  'code' => 'EXP_B_1', 'desc' => 'Commissioni PayPal Q1 2025', 'gestione' => 'commerciale'],
            // Utenze
            ['date' => '2025-02-20', 'amount' => 95.00,   'conto' => $contoBanca,  'code' => 'EXP_B_2', 'desc' => 'Telefonia e connessione internet – febbraio 2025'],
        ];

        foreach ($spese as $s) {
            $existing = Spesa::where('tenant_id', $tenant->id)
                ->whereDate('date', $s['date'])
                ->where('description', $s['desc'])
                ->first();
            if ($existing) continue;

            $gestione = $s['gestione'] ?? 'istituzionale';
            $spesa = Spesa::create([
                'date'              => $s['date'],
                'amount'            => $s['amount'],
                'conto_id'          => $s['conto']->id,
                'rendiconto_code'   => $s['code'],
                'description'       => $s['desc'],
                'genera_prima_nota' => true,
                'gestione'          => $gestione,
                'competenza_cassa'  => true,
            ]);

            PrimaNotaEntry::create([
                'conto_id'        => $s['conto']->id,
                'rendiconto_code' => $s['code'],
                'entryable_type'  => Spesa::class,
                'entryable_id'    => $spesa->id,
                'date'            => $s['date'],
                'amount'          => -abs($s['amount']),
                'description'     => $s['desc'],
                'gestione'        => $gestione,
                'competenza_cassa' => true,
            ]);
        }

        // ── Movimenti manuali extra ────────────────────────────────────
        $manuali = [
            // Saldo di apertura banca (entrata straordinaria)
            [
                'conto' => $contoBanca,
                'code'  => 'INC_D_1',
                'date'  => '2025-01-01',
                'amount' => 2500.00,
                'desc'  => 'Saldo apertura conto bancario – riporto anno precedente',
                'gestione' => 'istituzionale',
            ],
            // Saldo di apertura cassa
            [
                'conto' => $contoCassa,
                'code'  => 'INC_D_1',
                'date'  => '2025-01-01',
                'amount' => 350.00,
                'desc'  => 'Saldo apertura cassa – riporto anno precedente',
                'gestione' => 'istituzionale',
            ],
            // Contributo regionale
            [
                'conto' => $contoBanca,
                'code'  => 'INC_B_1',
                'date'  => '2025-03-10',
                'amount' => 800.00,
                'desc'  => 'Contributo Regione Lombardia – attività culturali 2025',
                'gestione' => 'istituzionale',
            ],
            // Ricavo corsi
            [
                'conto' => $contoPaypal,
                'code'  => 'INC_C_1',
                'date'  => '2025-04-10',
                'amount' => 420.00,
                'desc'  => 'Iscrizioni corso fotografia digitale',
                'gestione' => 'commerciale',
            ],
            // Giroconto: PayPal → Banca
            [
                'conto' => $contoPaypal,
                'code'  => 'EXP_D_1',
                'date'  => '2025-04-11',
                'amount' => -720.00,
                'desc'  => 'Giroconto PayPal → Banca c/c',
                'gestione' => 'istituzionale',
            ],
            [
                'conto' => $contoBanca,
                'code'  => 'INC_D_1',
                'date'  => '2025-04-11',
                'amount' => 720.00,
                'desc'  => 'Giroconto PayPal → Banca c/c',
                'gestione' => 'istituzionale',
            ],
        ];

        foreach ($manuali as $m) {
            $existing = PrimaNotaEntry::where('tenant_id', $tenant->id)
                ->whereDate('date', $m['date'])
                ->where('description', $m['desc'])
                ->where('conto_id', $m['conto']->id)
                ->first();
            if ($existing) continue;

            PrimaNotaEntry::create([
                'conto_id'         => $m['conto']->id,
                'rendiconto_code'  => $m['code'],
                'entryable_type'   => null,
                'entryable_id'     => null,
                'date'             => $m['date'],
                'amount'           => $m['amount'],
                'description'      => $m['desc'],
                'gestione'         => $m['gestione'],
                'competenza_cassa' => true,
            ]);
        }

        $this->command->info('✓ DemoSeeder completato:');
        $this->command->info('  Associazione: Associazione Culturale Marco Polo ETS');
        $this->command->info('  Conti: ' . Conto::where('tenant_id', $tenant->id)->count());
        $this->command->info('  Soci: ' . Member::where('tenant_id', $tenant->id)->count());
        $this->command->info('  Incassi: ' . Incasso::where('tenant_id', $tenant->id)->count());
        $this->command->info('  Spese: ' . Spesa::where('tenant_id', $tenant->id)->count());
        $this->command->info('  Prima nota (movimenti totali): ' . PrimaNotaEntry::where('tenant_id', $tenant->id)->count());
    }
}
