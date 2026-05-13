<?php

namespace Database\Seeders;

use App\Models\AdministrativeMovement;
use App\Models\AdministrativeMovementCategory;
use App\Models\ConsultantAssignment;
use App\Models\ConsultantNote;
use App\Models\ConsultantRequest;
use App\Models\Conto;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder completo per testare Fase 3 (Consultant Workspace) e Fase 5 (Administrative Movements).
 *
 * Crea:
 *  - 3 utenti consulenti con ruolo "consultant"
 *  - Assegnazioni di consulenti a 2 tenant (default + uno aggiuntivo)
 *  - Richieste di documenti in vari stati e priorità
 *  - Note condivise e interne
 *  - Categorie di movimenti amministrativi (entrata, uscita)
 *  - Movimenti amministrativi (entrata, uscita, giroconto)
 *
 * Idempotente: usa firstOrCreate / skippa se i dati esistono.
 * Esegui: php artisan db:seed --class=ConsultantAndAdministrativeMovementsSeeder
 */
class ConsultantAndAdministrativeMovementsSeeder extends Seeder
{
    public function run(): void
    {
        // ── Verifica e setup tenant ────────────────────────────────────
        $tenantDefault = Tenant::where('slug', 'default')->first();
        if (! $tenantDefault) {
            $this->command->warn('Tenant "default" non trovato. Esegui prima l\'installer.');
            return;
        }

        // Tenant aggiuntivo per testare assegnazioni multiple
        $tenantSecondo = Tenant::firstOrCreate(
            ['slug' => 'seconda-cooperativa'],
            ['name' => 'Seconda Cooperativa ETS'],
        );

        // ── Ruolo consulente ───────────────────────────────────────────
        $roleConsultant = Role::firstOrCreate(
            ['name' => 'consultant'],
            ['display_name' => 'Consulente'],
        );

        // ── Utenti consulenti ──────────────────────────────────────────
        $consultants = [];
        $consultantData = [
            [
                'email' => 'valentina.conti@demo.it',
                'name'  => 'Valentina Conti',
                'spec'  => 'Organizzazione e comunicazione',
            ],
            [
                'email' => 'marco.silva@demo.it',
                'name'  => 'Marco Silva',
                'spec'  => 'Finanze e contabilità',
            ],
            [
                'email' => 'laura.moretti@demo.it',
                'name'  => 'Laura Moretti',
                'spec'  => 'Risorse umane e legale',
            ],
        ];

        foreach ($consultantData as $cData) {
            $user = User::firstOrCreate(
                ['email' => $cData['email']],
                ['name' => $cData['name'], 'password' => bcrypt('password')],
            );

            // Assegna ruolo consultant
            if (! $user->roles()->where('name', 'consultant')->exists()) {
                $user->roles()->attach($roleConsultant);
            }

            $consultants[] = [
                'user'  => $user,
                'name'  => $cData['name'],
                'spec'  => $cData['spec'],
            ];
        }

        // ── Assegnazioni consulenti ────────────────────────────────────
        $assignments = [];

        // Valentina (organizzazione) → default + seconda
        $val = $consultants[0]['user'];
        $assignment1 = ConsultantAssignment::firstOrCreate(
            [
                'consultant_user_id' => $val->id,
                'tenant_id'          => $tenantDefault->id,
            ],
            [
                'ruolo'        => 'primario',
                'active'       => true,
                'started_at'   => '2025-05-01',
                'assigned_by_user_id' => User::where('email', 'test@example.com')->first()->id ?? null,
                'note'         => 'Specialista in processi organizzativi e comunicazione interna',
            ],
        );
        $assignment2 = ConsultantAssignment::firstOrCreate(
            [
                'consultant_user_id' => $val->id,
                'tenant_id'          => $tenantSecondo->id,
            ],
            [
                'ruolo'        => 'secondario',
                'active'       => true,
                'started_at'   => '2025-05-10',
                'assigned_by_user_id' => User::where('email', 'test@example.com')->first()->id ?? null,
                'note'         => 'Supporto su riorganizzazione comunicazione',
            ],
        );
        $assignments[] = $assignment1;
        $assignments[] = $assignment2;

        // Marco (finanze) → default
        $mar = $consultants[1]['user'];
        $assignment3 = ConsultantAssignment::firstOrCreate(
            [
                'consultant_user_id' => $mar->id,
                'tenant_id'          => $tenantDefault->id,
            ],
            [
                'ruolo'        => 'primario',
                'active'       => true,
                'started_at'   => '2025-04-15',
                'assigned_by_user_id' => User::where('email', 'test@example.com')->first()->id ?? null,
                'note'         => 'Revisore contabile e consulente finanze',
            ],
        );
        $assignments[] = $assignment3;

        // Laura (HR/legale) → seconda
        $lau = $consultants[2]['user'];
        $assignment4 = ConsultantAssignment::firstOrCreate(
            [
                'consultant_user_id' => $lau->id,
                'tenant_id'          => $tenantSecondo->id,
            ],
            [
                'ruolo'        => 'primario',
                'active'       => true,
                'started_at'   => '2025-05-05',
                'assigned_by_user_id' => User::where('email', 'test@example.com')->first()->id ?? null,
                'note'         => 'Consulente di diritto del terzo settore e gestione personale',
            ],
        );
        $assignments[] = $assignment4;

        // ── Richieste di documenti (per Valentina nel default) ────────
        app()->instance('current_tenant', $tenantDefault);

        $requestsData = [
            [
                'titolo'       => 'Strategia comunicazione 2025',
                'descrizione'  => 'Abbiamo bisogno di una review della strategia comunicativa per l\'anno in corso. In particolare sui canali social e sul newsletter.',
                'priorita'     => 'alta',
                'stato'        => 'aperta',
                'scadenza'     => '2025-05-25',
            ],
            [
                'titolo'       => 'Analisi audit interno',
                'descrizione'  => 'Richiesta di audit interno sugli ultimi 6 mesi. Fornire accesso ai verbali delle riunioni e ai documenti contabili.',
                'priorita'     => 'urgente',
                'stato'        => 'in_attesa_risposta',
                'scadenza'     => '2025-05-15',
            ],
            [
                'titolo'       => 'Politiche interne',
                'descrizione'  => 'Abbiamo preparato bozze di policy su: discriminazione, whistleblowing, privacy.',
                'priorita'     => 'normale',
                'stato'        => 'risposta_ricevuta',
                'scadenza'     => '2025-06-10',
            ],
            [
                'titolo'       => 'Documentazione archivio storico',
                'descrizione'  => 'Richiesta di copia digitale della documentazione dell\'associazione dal 2020 al 2024.',
                'priorita'     => 'bassa',
                'stato'        => 'chiusa',
                'scadenza'     => '2025-07-01',
            ],
        ];

        $requests = [];
        foreach ($requestsData as $rData) {
            $request = ConsultantRequest::firstOrCreate(
                [
                    'tenant_id'          => $tenantDefault->id,
                    'consultant_user_id' => $val->id,
                    'titolo'             => $rData['titolo'],
                ],
                [
                    'descrizione'     => $rData['descrizione'],
                    'priorita'        => $rData['priorita'],
                    'stato'           => $rData['stato'],
                    'data_scadenza'   => $rData['scadenza'],
                ],
            );
            $requests[] = $request;
        }

        // ── Note del consulente ────────────────────────────────────────
        $notesData = [
            [
                'testo'       => 'Osservazione canale Instagram: Il profilo Instagram è poco aggiornato. Ultimi post da marzo. Suggerirei pianificazione settimanale con contenuti preparati in advance.',
                'visibilita'  => 'condivisa',
                'fissata'     => true,
            ],
            [
                'testo'       => 'Follow-up riunione 12 maggio: Discusso sulla transizione del ruolo di comunicazione. Erano presenti: presidente, segretario, responsabile web.',
                'visibilita'  => 'condivisa',
                'fissata'     => false,
            ],
            [
                'testo'       => 'Appunti personali - impressioni: Team molto disponibile. Ho notato però mancanza di gestione documentale centralizzata. Suggerire sistema di archiviazione.',
                'visibilita'  => 'interna',
                'fissata'     => false,
            ],
        ];

        foreach ($notesData as $nData) {
            $existing = ConsultantNote::where('tenant_id', $tenantDefault->id)
                ->where('consultant_user_id', $val->id)
                ->where('testo', $nData['testo'])
                ->first();

            if ($existing) {
                continue;
            }

            $note = ConsultantNote::create([
                'tenant_id'          => $tenantDefault->id,
                'consultant_user_id' => $val->id,
                'testo'              => $nData['testo'],
                'visibilita'         => $nData['visibilita'],
                'fissata'            => $nData['fissata'],
            ]);
        }

        // ── Categorie movimenti amministrativi ──────────────────────────
        $categoriesData = [
            // Entrate
            [
                'nome'      => 'Quote associative',
                'tipo'      => 'entrata',
                'colore'    => '#10b981',
                'icona'     => '💳',
                'ordine'    => 1,
            ],
            [
                'nome'      => 'Donazioni',
                'tipo'      => 'entrata',
                'colore'    => '#8b5cf6',
                'icona'     => '❤️',
                'ordine'    => 2,
            ],
            [
                'nome'      => 'Contributi pubblici',
                'tipo'      => 'entrata',
                'colore'    => '#0ea5e9',
                'icona'     => '🏛️',
                'ordine'    => 3,
            ],
            [
                'nome'      => 'Ricavi attività',
                'tipo'      => 'entrata',
                'colore'    => '#f59e0b',
                'icona'     => '💰',
                'ordine'    => 4,
            ],
            // Uscite
            [
                'nome'      => 'Affitti e utenze',
                'tipo'      => 'uscita',
                'colore'    => '#ef4444',
                'icona'     => '🏠',
                'ordine'    => 5,
            ],
            [
                'nome'      => 'Forniture e materiali',
                'tipo'      => 'uscita',
                'colore'    => '#f97316',
                'icona'     => '📦',
                'ordine'    => 6,
            ],
            [
                'nome'      => 'Personale',
                'tipo'      => 'uscita',
                'colore'    => '#ec4899',
                'icona'     => '👨‍💼',
                'ordine'    => 7,
            ],
            [
                'nome'      => 'Servizi professionali',
                'tipo'      => 'uscita',
                'colore'    => '#a855f7',
                'icona'     => '👨‍⚖️',
                'ordine'    => 8,
            ],
            [
                'nome'      => 'Rimborsi volontari',
                'tipo'      => 'uscita',
                'colore'    => '#6b7280',
                'icona'     => '↩️',
                'ordine'    => 9,
            ],
            // Generiche
            [
                'nome'      => 'Trasferimenti interni',
                'tipo'      => 'qualsiasi',
                'colore'    => '#64748b',
                'icona'     => '⇄',
                'ordine'    => 10,
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $cData) {
            $cat = AdministrativeMovementCategory::firstOrCreate(
                [
                    'tenant_id' => $tenantDefault->id,
                    'nome'      => $cData['nome'],
                ],
                [
                    'tipo'    => $cData['tipo'],
                    'colore'  => $cData['colore'],
                    'icona'   => $cData['icona'],
                    'ordine'  => $cData['ordine'],
                    'attiva'  => true,
                ],
            );
            $categories[] = $cat;
        }

        // ── Conti (per movimenti amministrativi) ────────────────────────
        // Usa i conti esistenti dal DemoSeeder oppure creane di nuovi
        $contoCassa = Conto::where('tenant_id', $tenantDefault->id)
            ->where('code', 'CASSA')
            ->first();
        if (! $contoCassa) {
            $contoCassa = Conto::create([
                'tenant_id' => $tenantDefault->id,
                'name'      => 'Cassa contanti',
                'code'      => 'CASSA',
                'type'      => 'cassa',
                'ordine'    => 1,
                'attivo'    => true,
            ]);
        }

        $contoBanca = Conto::where('tenant_id', $tenantDefault->id)
            ->where('code', 'BANCA')
            ->first();
        if (! $contoBanca) {
            $contoBanca = Conto::create([
                'tenant_id' => $tenantDefault->id,
                'name'      => 'Banca c/c BancoPosta',
                'code'      => 'BANCA',
                'type'      => 'banca',
                'iban'      => 'IT60 X054 2811 1010 0000 0123 456',
                'ordine'    => 2,
                'attivo'    => true,
            ]);
        }

        // ── Movimenti amministrativi ───────────────────────────────────
        $movementsData = [
            // Entrate
            [
                'data'        => '2025-05-01',
                'tipo'        => 'entrata',
                'importo'     => 150.00,
                'descrizione' => 'Quote associative mese di maggio',
                'categoria'   => 'Quote associative',
                'conto'       => $contoCassa,
                'riferimento' => 'Quote-202505',
                'note'        => '3 quote da 50€ ciascuna',
            ],
            [
                'data'        => '2025-05-03',
                'tipo'        => 'entrata',
                'importo'     => 500.00,
                'descrizione' => 'Donazione Fondazione X',
                'categoria'   => 'Donazioni',
                'conto'       => $contoBanca,
                'riferimento' => 'DON-2025-001',
                'note'        => 'Erogazione liberale per attività culturali',
            ],
            [
                'data'        => '2025-05-05',
                'tipo'        => 'entrata',
                'importo'     => 1200.00,
                'descrizione' => 'Contributo Regione Lombardia',
                'categoria'   => 'Contributi pubblici',
                'conto'       => $contoBanca,
                'riferimento' => 'REG-LOM-2025-A',
                'note'        => 'Contributo per progetto didattico',
            ],
            [
                'data'        => '2025-05-08',
                'tipo'        => 'entrata',
                'importo'     => 280.00,
                'descrizione' => 'Iscrizioni corso fotografia',
                'categoria'   => 'Ricavi attività',
                'conto'       => $contoCassa,
                'riferimento' => 'CORSO-FOTO-MAG',
                'note'        => '4 partecipanti da 70€ ciascuno',
            ],
            // Uscite
            [
                'data'        => '2025-05-02',
                'tipo'        => 'uscita',
                'importo'     => 350.00,
                'descrizione' => 'Affitto sala per maggio',
                'categoria'   => 'Affitti e utenze',
                'conto'       => $contoBanca,
                'riferimento' => 'AFFITTO-202505',
                'note'        => 'Sala polivalente centro città',
            ],
            [
                'data'        => '2025-05-04',
                'tipo'        => 'uscita',
                'importo'     => 120.00,
                'descrizione' => 'Materiali per laboratorio',
                'categoria'   => 'Forniture e materiali',
                'conto'       => $contoCassa,
                'riferimento' => 'MAT-LAB-001',
                'note'        => 'Colori, tele, pennelli per corso pittura',
            ],
            [
                'data'        => '2025-05-06',
                'tipo'        => 'uscita',
                'importo'     => 450.00,
                'descrizione' => 'Compenso consulente esterno',
                'categoria'   => 'Servizi professionali',
                'conto'       => $contoBanca,
                'riferimento' => 'CONS-COM-001',
                'note'        => 'Consulenza progetto comunicazione',
            ],
            [
                'data'        => '2025-05-09',
                'tipo'        => 'uscita',
                'importo'     => 85.50,
                'descrizione' => 'Rimborso spese trasferta - Conti Anna',
                'categoria'   => 'Rimborsi volontari',
                'conto'       => $contoCassa,
                'riferimento' => 'RIM-CONTI-001',
                'note'        => 'Trasferta per evento a Roma',
            ],
            // Giroconto
            [
                'data'        => '2025-05-07',
                'tipo'        => 'giroconto',
                'importo'     => 500.00,
                'descrizione' => 'Giroconto cassa → banca',
                'categoria'   => 'Trasferimenti interni',
                'conto'       => $contoCassa,
                'conto_dest'  => $contoBanca,
                'riferimento' => 'GIRO-070525',
                'note'        => 'Deposito incassi giornalieri',
            ],
        ];

        foreach ($movementsData as $mData) {
            $categoria = collect($categories)
                ->firstWhere('nome', $mData['categoria']);

            $esistente = AdministrativeMovement::where('tenant_id', $tenantDefault->id)
                ->whereDate('data', $mData['data'])
                ->where('descrizione', $mData['descrizione'])
                ->first();

            if ($esistente) {
                continue;
            }

            $movimento = AdministrativeMovement::create([
                'tenant_id'              => $tenantDefault->id,
                'data'                   => $mData['data'],
                'tipo'                   => $mData['tipo'],
                'importo'                => $mData['importo'],
                'descrizione'            => $mData['descrizione'],
                'category_id'            => $categoria?->id,
                'conto_id'               => $mData['conto']->id,
                'conto_destinazione_id'  => $mData['conto_dest']->id ?? null,
                'riferimento'            => $mData['riferimento'],
                'note'                   => $mData['note'],
                'tags'                   => json_encode([]),
            ]);
        }

        // ── Output ─────────────────────────────────────────────────────
        $this->command->info('✓ ConsultantAndAdministrativeMovementsSeeder completato:');
        $this->command->info('  Utenti consulenti: ' . count($consultants));
        $this->command->info('  Assegnazioni consulenti: ' . ConsultantAssignment::count());
        $this->command->info('  Richieste documenti: ' . ConsultantRequest::where('tenant_id', $tenantDefault->id)->count());
        $this->command->info('  Note consulente: ' . ConsultantNote::where('tenant_id', $tenantDefault->id)->count());
        $this->command->info('  Categorie movimenti: ' . AdministrativeMovementCategory::where('tenant_id', $tenantDefault->id)->count());
        $this->command->info('  Movimenti amministrativi: ' . AdministrativeMovement::where('tenant_id', $tenantDefault->id)->count());
    }
}
