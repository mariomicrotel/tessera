<?php

namespace Database\Seeders;

use App\Models\CausaleContabile;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeder delle causali contabili di sistema per cooperative.
 *
 * Preconfigura le causali più comuni usate nel piano dei conti cooperativa.
 * Ogni causale è `di_sistema = true` e non eliminabile.
 *
 * Il metodo `perTenant()` è idempotente (firstOrCreate).
 */
class CausaliContabiliDiSistemaSeeder extends Seeder
{
    /**
     * Causali di sistema con [codice, descrizione, tipo].
     * Le causali non hanno conto_contropartita_default_id in questo seeder
     * (dipendono dal piano dei conti specifico del tenant).
     */
    private const CAUSALI = [
        // Acquisti e vendite
        ['FAC', 'Fattura di acquisto',              'fattura_acquisto'],
        ['FVC', 'Fattura di vendita',               'fattura_vendita'],
        ['NAC', 'Nota di credito da fornitore',     'fattura_acquisto'],
        ['NVC', 'Nota di credito a cliente',        'fattura_vendita'],

        // Incassi e pagamenti
        ['INC', 'Incasso da cliente',               'incasso'],
        ['PAG', 'Pagamento a fornitore',            'pagamento'],
        ['BON', 'Bonifico bancario',                'pagamento'],
        ['RIB', 'Rid / addebito diretto',           'pagamento'],

        // Giroconti e rettifiche
        ['GIR', 'Giroconto tra conti',              'giroconto'],
        ['RET', 'Rettifica contabile',              'generico'],

        // Scritture di rettifica
        ['AMM', 'Quota di ammortamento',            'ammortamento'],
        ['STI', 'Stipendi e competenze del personale', 'stipendi'],
        ['TFR', 'Accantonamento TFR',               'stipendi'],
        ['RAT', 'Rateo / risconto',                 'generico'],

        // Apertura e chiusura esercizio
        ['APE', 'Apertura conti (primo giorno esercizio)', 'apertura'],
        ['CHI', 'Chiusura conti (ultimo giorno esercizio)', 'chiusura'],
        ['DES', 'Destinazione risultato d\'esercizio', 'chiusura'],

        // Cooperative
        ['RIS', 'Ristorno ai soci',                 'generico'],
        ['DIV', 'Distribuzione dividendi ai soci',  'generico'],
        ['PRE', 'Prestito sociale (versamento)',     'generico'],
        ['RIM', 'Prestito sociale (rimborso)',       'generico'],
    ];

    /**
     * Preconfigura le causali per il tenant specificato (idempotente).
     */
    public function perTenant(Tenant $tenant): void
    {
        foreach (self::CAUSALI as [$codice, $descrizione, $tipo]) {
            CausaleContabile::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'codice'    => $codice,
                ],
                [
                    'descrizione' => $descrizione,
                    'tipo'        => $tipo,
                    'di_sistema'  => true,
                    'attivo'      => true,
                ]
            );
        }
    }

    /**
     * Esegui il seeder su tutti i tenant cooperative.
     */
    public function run(): void
    {
        Tenant::where('organization_type', 'cooperative')->each(
            fn (Tenant $t) => $this->perTenant($t)
        );
    }
}
