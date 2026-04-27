<?php

namespace Database\Seeders;

use App\Models\CodiceIva;
use Illuminate\Database\Seeder;

/**
 * Seeder per codici IVA standard italiani + configurabili.
 *
 * Codici "di_sistema=true" sono standard non eliminabili.
 * Codici standard custom possono essere aggiunti/modificati.
 */
class CodiciIvaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound('current_tenant')) {
            return;
        }

        $tenant = app('current_tenant');
        if (! $tenant) {
            return;
        }

        // Codici IVA Standard Italiani — Aliquote ordinarie
        $codici = [
            // Aliquote ordinarie
            [
                'codice'                  => '22',
                'descrizione'             => 'IVA Ordinaria 22%',
                'percentuale'             => 22.00,
                'tipo'                    => 'normale',
                'natura_sdi'              => 'N',
                'indetraibile_percentuale' => 0.00,
                'di_sistema'              => true,
            ],
            [
                'codice'                  => '10',
                'descrizione'             => 'IVA Ridotta 10%',
                'percentuale'             => 10.00,
                'tipo'                    => 'normale',
                'natura_sdi'              => 'N',
                'indetraibile_percentuale' => 0.00,
                'di_sistema'              => true,
            ],
            [
                'codice'                  => '5',
                'descrizione'             => 'IVA Superridotta 5%',
                'percentuale'             => 5.00,
                'tipo'                    => 'normale',
                'natura_sdi'              => 'N',
                'indetraibile_percentuale' => 0.00,
                'di_sistema'              => true,
            ],
            [
                'codice'                  => '4',
                'descrizione'             => 'IVA Super Ridotta 4%',
                'percentuale'             => 4.00,
                'tipo'                    => 'normale',
                'natura_sdi'              => 'N',
                'indetraibile_percentuale' => 0.00,
                'di_sistema'              => true,
            ],

            // Aliquote speciali
            [
                'codice'                  => '0',
                'descrizione'             => 'IVA Esente',
                'percentuale'             => 0.00,
                'tipo'                    => 'esente',
                'natura_sdi'              => 'E',
                'indetraibile_percentuale' => 0.00,
                'di_sistema'              => true,
            ],
            [
                'codice'                  => 'FC',
                'descrizione'             => 'Fuori Campo IVA',
                'percentuale'             => 0.00,
                'tipo'                    => 'fuori_campo',
                'natura_sdi'              => 'F',
                'indetraibile_percentuale' => 0.00,
                'di_sistema'              => true,
            ],
            [
                'codice'                  => 'NI',
                'descrizione'             => 'Non Imponibile',
                'percentuale'             => 0.00,
                'tipo'                    => 'non_imponibile',
                'natura_sdi'              => 'L',
                'indetraibile_percentuale' => 0.00,
                'di_sistema'              => true,
            ],

            // Reverse charge e split payment
            [
                'codice'                  => 'RC',
                'descrizione'             => 'Reverse Charge (IVA a carico cliente)',
                'percentuale'             => 22.00,
                'tipo'                    => 'reverse_charge',
                'natura_sdi'              => 'R',
                'indetraibile_percentuale' => 0.00,
                'di_sistema'              => true,
            ],
            [
                'codice'                  => 'SP',
                'descrizione'             => 'Split Payment (PA)',
                'percentuale'             => 22.00,
                'tipo'                    => 'split_payment',
                'natura_sdi'              => 'S',
                'indetraibile_percentuale' => 0.00,
                'di_sistema'              => true,
            ],

            // IVA non detraibile (parcelle professionisti, ecc.)
            [
                'codice'                  => '22ND',
                'descrizione'             => 'IVA 22% Non Detraibile',
                'percentuale'             => 22.00,
                'tipo'                    => 'normale',
                'natura_sdi'              => 'N',
                'indetraibile_percentuale' => 100.00,
                'di_sistema'              => true,
            ],
        ];

        foreach ($codici as $dati) {
            CodiceIva::firstOrCreate(
                ['tenant_id' => $tenant->id, 'codice' => $dati['codice']],
                array_merge($dati, ['tenant_id' => $tenant->id])
            );
        }
    }
}
