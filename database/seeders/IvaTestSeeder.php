<?php

namespace Database\Seeders;

use App\Models\CodiceIva;
use App\Models\FatturaPassiva;
use App\Models\FatturaAttiva;
use App\Models\LiquidazioneIva;
use App\Models\RigaFatturaPassiva;
use App\Models\RigaFatturaAttiva;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeder di test per il modulo IVA.
 *
 * Popola un tenant cooperativa con:
 * - Fatture passive di test (2026-01, 2026-02)
 * - Fatture attive di test (2026-01, 2026-02)
 * - Liquidazione IVA trimestrale in bozza (Q1 2026)
 *
 * Uso: php artisan db:seed --class=IvaTestSeeder
 */
class IvaTestSeeder extends Seeder
{
    public function run(): void
    {
        // Seleziona il primo tenant cooperativa (o lo crea se non esiste)
        $tenant = Tenant::where('organization_type', 'cooperative')->first();
        if (! $tenant) {
            $tenant = Tenant::create([
                'name'              => 'Cooperativa Test IVA',
                'slug'              => 'coop-iva-test',
                'organization_type' => 'cooperative',
                'plan'              => 'free',
                'is_active'         => true,
            ]);
        }

        app()->instance('current_tenant', $tenant);

        // Carica i codici IVA di sistema (precaricati dall'Observer)
        $iva22 = CodiceIva::where('codice', '22')->first();
        $iva10 = CodiceIva::where('codice', '10')->first();
        $iva4 = CodiceIva::where('codice', '4')->first();

        // --- Fatture Passive (acquisti) ---
        // Fattura 1: gennaio 2026
        $fp1 = FatturaPassiva::create([
            'numero_fattura'     => 'FP-2026-001',
            'data_fattura'       => '2026-01-15',
            'data_registrazione' => '2026-01-15',
            'data_scadenza'      => '2026-02-14',
            'esigibilita'        => FatturaPassiva::ESIGIBILITA_IMMEDIATA,
            'stato_pagamento'    => FatturaPassiva::STATO_DA_PAGARE,
        ]);

        RigaFatturaPassiva::create([
            'fattura_passiva_id'       => $fp1->id,
            'codice_iva_id'            => $iva22->id,
            'descrizione'              => 'Cancelleria ufficio',
            'quantita'                 => 1,
            'prezzo_unitario'          => 100,
            'imponibile'               => 100,
            'iva'                      => 22,
            'totale'                   => 122,
            'indetraibile_percentuale' => 0,
            'iva_indetraibile'         => 0,
        ]);

        RigaFatturaPassiva::create([
            'fattura_passiva_id'       => $fp1->id,
            'codice_iva_id'            => $iva10->id,
            'descrizione'              => 'Servizio consulenza',
            'quantita'                 => 1,
            'prezzo_unitario'          => 500,
            'imponibile'               => 500,
            'iva'                      => 50,
            'totale'                   => 550,
            'indetraibile_percentuale' => 0,
            'iva_indetraibile'         => 0,
        ]);

        $fp1->ricalcolaTotali();
        $fp1->save();

        // Fattura 2: febbraio 2026
        $fp2 = FatturaPassiva::create([
            'numero_fattura'     => 'FP-2026-002',
            'data_fattura'       => '2026-02-10',
            'data_registrazione' => '2026-02-10',
            'data_scadenza'      => '2026-03-10',
            'esigibilita'        => FatturaPassiva::ESIGIBILITA_IMMEDIATA,
            'stato_pagamento'    => FatturaPassiva::STATO_PAGATA,
        ]);

        RigaFatturaPassiva::create([
            'fattura_passiva_id'       => $fp2->id,
            'codice_iva_id'            => $iva4->id,
            'descrizione'              => 'Forniture per disabili',
            'quantita'                 => 5,
            'prezzo_unitario'          => 20,
            'imponibile'               => 100,
            'iva'                      => 4,
            'totale'                   => 104,
            'indetraibile_percentuale' => 0,
            'iva_indetraibile'         => 0,
        ]);

        $fp2->ricalcolaTotali();
        $fp2->save();

        // --- Fatture Attive (vendite) ---
        // Fattura 1: gennaio 2026
        $fa1 = FatturaAttiva::create([
            'sezionale'          => '',
            'anno'               => 2026,
            'progressivo'        => 1,
            'numero_fattura'     => '2026/1',
            'data_fattura'       => '2026-01-20',
            'data_scadenza'      => '2026-02-20',
            'esigibilita'        => FatturaAttiva::ESIGIBILITA_IMMEDIATA,
            'tipo_documento'     => 'TD01',
            'stato'              => FatturaAttiva::STATO_EMESSA,
            'stato_pagamento'    => FatturaAttiva::STATO_PAG_DA_INCASSARE,
        ]);

        RigaFatturaAttiva::create([
            'fattura_attiva_id'  => $fa1->id,
            'codice_iva_id'      => $iva22->id,
            'descrizione'        => 'Prestazione professionale',
            'quantita'           => 1,
            'prezzo_unitario'    => 1000,
            'sconto_percentuale' => 0,
            'imponibile'         => 1000,
            'iva'                => 220,
            'totale'             => 1220,
        ]);

        $fa1->ricalcolaTotali();
        $fa1->save();

        // Fattura 2: febbraio 2026
        $fa2 = FatturaAttiva::create([
            'sezionale'          => '',
            'anno'               => 2026,
            'progressivo'        => 2,
            'numero_fattura'     => '2026/2',
            'data_fattura'       => '2026-02-15',
            'data_scadenza'      => '2026-03-15',
            'esigibilita'        => FatturaAttiva::ESIGIBILITA_IMMEDIATA,
            'tipo_documento'     => 'TD01',
            'stato'              => FatturaAttiva::STATO_EMESSA,
            'stato_pagamento'    => FatturaAttiva::STATO_PAG_INCASSATA,
        ]);

        RigaFatturaAttiva::create([
            'fattura_attiva_id'  => $fa2->id,
            'codice_iva_id'      => $iva10->id,
            'descrizione'        => 'Vendita beni',
            'quantita'           => 10,
            'prezzo_unitario'    => 50,
            'sconto_percentuale' => 5,
            'imponibile'         => 475,
            'iva'                => 47.5,
            'totale'             => 522.5,
        ]);

        $fa2->ricalcolaTotali();
        $fa2->save();

        // --- Liquidazione IVA Q1 2026 ---
        $liq = LiquidazioneIva::create([
            'anno'         => 2026,
            'periodo'      => 1,
            'tipo_periodo' => LiquidazioneIva::TIPO_TRIMESTRALE,
            'data_inizio'  => '2026-01-01',
            'data_fine'    => '2026-03-31',
            'iva_debito'   => 0,
            'iva_credito'  => 0,
            'status'       => LiquidazioneIva::STATUS_BOZZA,
        ]);

        $this->command->info("✓ Seeder IVA completato per tenant: {$tenant->name}");
        $this->command->info("  - 2 Fatture passive");
        $this->command->info("  - 2 Fatture attive");
        $this->command->info("  - 1 Liquidazione IVA (bozza)");
        $this->command->info("\n  Visita: http://localhost:8090/app/{$tenant->slug}/iva");
    }
}
