<?php

namespace App\Services\Consultant\Export\Contracts;

use Carbon\CarbonInterface;

/**
 * Una sorgente dati esportabile (es. "Fatture attive", "Incassi").
 *
 * Principio cardine: streaming via Generator → backpressure naturale,
 * footprint memoria O(1) anche su 100k+ righe.
 *
 * Ogni DataSource può essere consumato da N ExportFormat senza duplicare la query:
 * il formato decide come SCRIVERE, il DataSource decide cosa LEGGERE.
 */
interface DataSource
{
    /**
     * Identificatore stabile (snake_case) — usato in DB nella colonna data_types
     * e come chiave nel form di selezione UI. Non cambiarlo mai dopo il rilascio.
     *
     * Esempi: 'fatture_attive', 'incassi', 'movimenti_bancari'
     */
    public function key(): string;

    /**
     * Etichetta human-readable per la UI.
     */
    public function label(): string;

    /**
     * Descrizione breve per il form di selezione.
     */
    public function description(): string;

    /**
     * Nome file consigliato dentro il bundle ZIP (senza directory, con estensione).
     * Esempio: 'fatture_attive.csv'.
     */
    public function fileName(): string;

    /**
     * Header colonne nell'ordine in cui devono apparire nelle rows().
     * Stesso ordine sia per CSV che per qualsiasi altro formato tabulare.
     */
    public function headers(): array;

    /**
     * Stream lazy delle righe nel periodo.
     * MUST emettere array indicizzati nello stesso ordine di headers().
     *
     * Implementazione tipica:
     *   foreach (DB::table(...)->where(...)->lazy() as $row) {
     *       yield [$row->col1, $row->col2, ...];
     *   }
     *
     * @param  string  $tenantId  UUID del tenant (filtro esplicito, no BelongsToTenant)
     * @return \Generator<int, array<int|string, mixed>>
     */
    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator;

    /**
     * Conta approssimativa per progress bar / dimensionamento.
     * Può essere 0 se non disponibile o costosa da calcolare.
     */
    public function estimatedCount(string $tenantId, CarbonInterface $from, CarbonInterface $to): int;
}
