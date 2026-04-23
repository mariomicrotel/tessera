<?php

namespace Database\Seeders;

use App\Models\CodiceIva;
use App\Models\FatturaPassiva;
use App\Models\RigaFatturaPassiva;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeder per generare dati di test: fornitori e fatture passive.
 *
 * Usa:
 *   php artisan db:seed --class=FatturePassiveTestSeeder
 */
class FatturePassiveTestSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->error('Nessun tenant trovato. Eseguire prima: php artisan db:seed');
            return;
        }

        // ── Fornitori ──────────────────────────────────────────────────────
        $fornitori = [
            [
                'name'                  => 'TIM - Telecom Italia',
                'ragione_sociale'       => 'Telecom Italia S.p.A.',
                'partita_iva'           => '00488410010',
                'codice_fiscale'        => '00488410010',
                'codice_sdi'            => '0000011',
                'email'                 => 'fatture@tim.it',
                'phone'                 => '+39 06 3688 1234',
                'indirizzo'             => 'Via Gaetano Negri, 1',
                'cap'                   => '20123',
                'citta'                 => 'Milano',
                'provincia'             => 'MI',
                'nazione'               => 'IT',
                'iban'                  => 'IT07U0100003300000000000500',
                'condizioni_pagamento'  => '30gg',
                'categoria'             => 'servizi',
                'attivo'                => true,
            ],
            [
                'name'                  => 'Enel Energia',
                'ragione_sociale'       => 'Enel Energia S.p.A.',
                'partita_iva'           => '00850210122',
                'codice_fiscale'        => '00850210122',
                'codice_sdi'            => '0000012',
                'email'                 => 'commerciale@enel.it',
                'phone'                 => '+39 800 900 800',
                'indirizzo'             => 'Via Luciano Gaudenzio, 108',
                'cap'                   => '70124',
                'citta'                 => 'Bari',
                'provincia'             => 'BA',
                'nazione'               => 'IT',
                'iban'                  => 'IT41U0200812101000055385017',
                'condizioni_pagamento'  => '60gg',
                'categoria'             => 'servizi',
                'attivo'                => true,
            ],
            [
                'name'                  => 'Global Logistic',
                'ragione_sociale'       => 'Global Logistic S.r.l.',
                'partita_iva'           => '12345678901',
                'codice_fiscale'        => '12345678901',
                'codice_sdi'            => 'AAABBB1',
                'email'                 => 'info@globallogistic.it',
                'phone'                 => '+39 02 5555 1111',
                'indirizzo'             => 'Via dell\'Industria, 42',
                'cap'                   => '25010',
                'citta'                 => 'Desenzano',
                'provincia'             => 'BS',
                'nazione'               => 'IT',
                'iban'                  => 'IT92U0311102850000000050000',
                'condizioni_pagamento'  => '30gg',
                'categoria'             => 'servizi',
                'attivo'                => true,
            ],
            [
                'name'                  => 'CartaPlus Group',
                'ragione_sociale'       => 'CartaPlus Distribuzione S.r.l.',
                'partita_iva'           => '09876543210',
                'codice_fiscale'        => '09876543210',
                'codice_sdi'            => 'CCCDDD2',
                'email'                 => 'ordini@cartaplus.it',
                'phone'                 => '+39 051 1234567',
                'indirizzo'             => 'Via Emilia Est, 780',
                'cap'                   => '40011',
                'citta'                 => 'Anzola',
                'provincia'             => 'BO',
                'nazione'               => 'IT',
                'iban'                  => 'IT16U0306902248100000010000',
                'condizioni_pagamento'  => '45gg',
                'categoria'             => 'beni',
                'attivo'                => true,
            ],
            [
                'name'                  => 'Studio Legale Romano',
                'ragione_sociale'       => 'Studio Legale Romano e Associati',
                'partita_iva'           => '11122233344',
                'codice_fiscale'        => 'RMNMRA80A01H501J',
                'codice_sdi'            => 'ROMANO1',
                'email'                 => 'amministrazione@studioromano.it',
                'phone'                 => '+39 06 9876543',
                'indirizzo'             => 'Via Nazionale, 100',
                'cap'                   => '00184',
                'citta'                 => 'Roma',
                'provincia'             => 'RM',
                'nazione'               => 'IT',
                'iban'                  => 'IT22U0200805274000400012345',
                'condizioni_pagamento'  => 'immediato',
                'categoria'             => 'professionista',
                'attivo'                => true,
            ],
        ];

        $suppliersDb = [];
        foreach ($fornitori as $data) {
            $data['tenant_id'] = $tenant->id;
            $supplier = Supplier::firstOrCreate(
                ['tenant_id' => $tenant->id, 'partita_iva' => $data['partita_iva']],
                $data
            );
            $suppliersDb[] = $supplier;
        }

        $this->command->info('✅ Creati ' . count($suppliersDb) . ' fornitori');

        // ── Codici IVA ──────────────────────────────────────────────────────
        // Crea codici IVA se non esistono
        $codiciIvaData = [
            ['codice' => '22', 'percentuale' => 22.00, 'descrizione' => 'IVA 22%'],
            ['codice' => '10', 'percentuale' => 10.00, 'descrizione' => 'IVA 10%'],
            ['codice' => '5', 'percentuale' => 5.00, 'descrizione' => 'IVA 5%'],
            ['codice' => '4', 'percentuale' => 4.00, 'descrizione' => 'IVA 4%'],
        ];

        $codiciIva = [];
        foreach ($codiciIvaData as $data) {
            $iva = CodiceIva::firstOrCreate(
                ['tenant_id' => $tenant->id, 'codice' => $data['codice']],
                ['percentuale' => $data['percentuale'], 'descrizione' => $data['descrizione']]
            );
            $codiciIva[$data['codice']] = $iva;
        }

        if (empty($codiciIva)) {
            $this->command->warn('⚠️  Impossibile creare codici IVA.');
            return;
        }

        // ── Fatture Passive ─────────────────────────────────────────────────
        $today = now();
        $fattureData = [
            // Fattura scaduta (15 giorni fa)
            [
                'supplier_id'           => $suppliersDb[0]->id,
                'numero_fattura'        => 'FT2026-001',
                'data_fattura'          => $today->copy()->subDays(20),
                'data_ricezione'        => $today->copy()->subDays(18),
                'data_registrazione'    => $today->copy()->subDays(18),
                'data_scadenza'         => $today->copy()->subDays(15),
                'esigibilita'           => 'immediata',
                'tipo_documento'        => 'TD01',
                'stato_pagamento'       => 'da_pagare',
                'note'                  => 'Pagamento scaduto',
            ],
            // Fattura in scadenza (3 giorni)
            [
                'supplier_id'           => $suppliersDb[1]->id,
                'numero_fattura'        => 'EL2026-0042',
                'data_fattura'          => $today->copy()->subDays(5),
                'data_ricezione'        => $today->copy()->subDays(4),
                'data_registrazione'    => $today->copy()->subDays(4),
                'data_scadenza'         => $today->copy()->addDays(3),
                'esigibilita'           => 'immediata',
                'tipo_documento'        => 'TD01',
                'stato_pagamento'       => 'da_pagare',
                'note'                  => 'Bolletta energetica',
            ],
            // Fattura in scadenza (7 giorni)
            [
                'supplier_id'           => $suppliersDb[2]->id,
                'numero_fattura'        => 'GL2026-128',
                'data_fattura'          => $today->copy()->subDays(10),
                'data_ricezione'        => $today->copy()->subDays(9),
                'data_registrazione'    => $today->copy()->subDays(9),
                'data_scadenza'         => $today->copy()->addDays(7),
                'esigibilita'           => 'immediata',
                'tipo_documento'        => 'TD01',
                'stato_pagamento'       => 'da_pagare',
                'note'                  => 'Servizi di trasporto',
            ],
            // Fattura in scadenza (20 giorni)
            [
                'supplier_id'           => $suppliersDb[3]->id,
                'numero_fattura'        => 'CP2026-0055',
                'data_fattura'          => $today->copy()->subDays(8),
                'data_ricezione'        => $today->copy()->subDays(7),
                'data_registrazione'    => $today->copy()->subDays(7),
                'data_scadenza'         => $today->copy()->addDays(20),
                'esigibilita'           => 'immediata',
                'tipo_documento'        => 'TD01',
                'stato_pagamento'       => 'da_pagare',
                'note'                  => 'Fornitura carta e articoli',
            ],
            // Fattura con scadenza lontana (60 giorni)
            [
                'supplier_id'           => $suppliersDb[4]->id,
                'numero_fattura'        => 'SLR2026-12',
                'data_fattura'          => $today->copy()->subDays(2),
                'data_ricezione'        => $today->copy()->subDays(1),
                'data_registrazione'    => $today->copy()->subDays(1),
                'data_scadenza'         => $today->copy()->addDays(60),
                'esigibilita'           => 'immediata',
                'tipo_documento'        => 'TD01',
                'stato_pagamento'       => 'da_pagare',
                'note'                  => 'Consulenza legale',
            ],
            // Fattura già pagata
            [
                'supplier_id'           => $suppliersDb[0]->id,
                'numero_fattura'        => 'FT2026-002',
                'data_fattura'          => $today->copy()->subDays(60),
                'data_ricezione'        => $today->copy()->subDays(58),
                'data_registrazione'    => $today->copy()->subDays(58),
                'data_scadenza'         => $today->copy()->subDays(30),
                'esigibilita'           => 'immediata',
                'tipo_documento'        => 'TD01',
                'stato_pagamento'       => 'pagata',
                'note'                  => 'Pagamento effettuato il 15/03/2026',
            ],
            // Fattura parzialmente pagata
            [
                'supplier_id'           => $suppliersDb[1]->id,
                'numero_fattura'        => 'EL2026-0041',
                'data_fattura'          => $today->copy()->subDays(40),
                'data_ricezione'        => $today->copy()->subDays(38),
                'data_registrazione'    => $today->copy()->subDays(38),
                'data_scadenza'         => $today->copy()->subDays(10),
                'esigibilita'           => 'immediata',
                'tipo_documento'        => 'TD01',
                'stato_pagamento'       => 'parzialmente_pagata',
                'note'                  => 'Acconto pagato, saldo da versare',
            ],
        ];

        foreach ($fattureData as $data) {
            $data['tenant_id'] = $tenant->id;
            $data['imponibile_totale'] = 0;
            $data['iva_totale'] = 0;
            $data['totale_documento'] = 0;

            $fattura = FatturaPassiva::create($data);

            // Righe fattura
            $righeData = [
                [
                    'codice_iva_id'             => $codiciIva['22']->id,
                    'descrizione'               => 'Servizio professionale',
                    'quantita'                  => 1,
                    'prezzo_unitario'           => 1500.00,
                    'indetraibile_percentuale'  => 0,
                ],
                [
                    'codice_iva_id'             => $codiciIva['10']->id,
                    'descrizione'               => 'Fornitura materiali',
                    'quantita'                  => 25,
                    'prezzo_unitario'           => 45.50,
                    'indetraibile_percentuale'  => 0,
                ],
            ];

            // Crea righe (varia il numero di righe)
            $numRighe = ($fattura->id % 2) + 1;
            foreach (array_slice($righeData, 0, $numRighe) as $rigaDati) {
                $rigaDati['tenant_id'] = $tenant->id;
                $rigaDati['fattura_passiva_id'] = $fattura->id;

                $riga = RigaFatturaPassiva::create($rigaDati);

                // Calcola totali
                $qta = (float) $riga->quantita;
                $prezzo = (float) $riga->prezzo_unitario;
                $imp = round($qta * $prezzo, 2);
                $aliq = (float) $riga->codiceIva->percentuale;
                $iva = round($imp * $aliq / 100, 2);
                $indPct = (float) ($riga->indetraibile_percentuale ?? 0);
                $ivaInd = round($iva * $indPct / 100, 2);

                $riga->update([
                    'imponibile'       => $imp,
                    'iva'              => $iva,
                    'iva_indetraibile' => $ivaInd,
                    'totale'           => round($imp + $iva, 2),
                ]);
            }

            // Ricalcola totali fattura
            $fattura->ricalcolaTotali();
            $fattura->save();
        }

        $this->command->info('✅ Create ' . count($fattureData) . ' fatture passive con righe');
        $this->command->line('');
        $this->command->line('📊 Dati inseriti:');
        $this->command->line('   - Fornitori: ' . count($suppliersDb));
        $this->command->line('   - Fatture: ' . count($fattureData));
        $this->command->line('   - Stati: da_pagare (4), pagata (1), parzialmente_pagata (1), scadute (1)');
        $this->command->line('   - Scadenze: scaduta (1), entro 7gg (2), entro 30gg (1), oltre 30gg (2)');
        $this->command->line('');
        $this->command->info('✨ Pronto per testare il frontend!');
    }
}
