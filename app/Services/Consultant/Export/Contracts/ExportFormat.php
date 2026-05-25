<?php

namespace App\Services\Consultant\Export\Contracts;

use App\Models\ConsultantExportBundle;
use Carbon\CarbonInterface;

/**
 * Strategia di scrittura per un formato di export.
 *
 * Riceve una lista di DataSource e produce un file dentro $workDir.
 * Il file prodotto sarà poi incluso (eventualmente con altri formati)
 * nel ZIP finale del bundle.
 *
 * Convenzioni:
 *  - Il formato NON conosce nulla di DB / Eloquent: itera solo DataSource::rows().
 *  - Il formato decide nome file finale e estensione.
 *  - Streaming: scrivere row-by-row, mai accumulare in memoria.
 *  - Idempotente: deve poter essere riavviato senza side-effect (no cache esterna).
 *
 * Per aggiungere un nuovo formato (es. TeamSystem CSV):
 *   1. Crea classe che implementa ExportFormat
 *   2. Registrala in ExportFormatRegistry::all()
 *   3. Nessuna altra modifica al core
 */
interface ExportFormat
{
    /**
     * Identificatore stabile (snake_case). Usato in DB e nel form UI.
     * Esempi: 'csv_generic', 'agenzia_entrate_xml', 'teamsystem_csv'.
     */
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /**
     * MIME type del file prodotto (può essere zip se il formato emette già un archivio).
     */
    public function mimeType(): string;

    /**
     * Estensione file (es. 'csv', 'xml', 'zip').
     */
    public function extension(): string;

    /**
     * Restituisce l'elenco dei DataSource che QUESTO formato supporta.
     * Esempio: il formato Agenzia Entrate XML accetta solo fatture_attive e fatture_passive.
     * Restituisce null per indicare "tutti i DataSource disponibili".
     * Restituire [] (array vuoto) se requiresDataSources() = false (formato self-contained).
     *
     * @return array<string>|null  array di DataSource::key() supportati, o null = wildcard
     */
    public function supportedDataSources(): ?array;

    /**
     * Indica se il formato consuma DataSource selezionati dall'utente o
     * se è "self-contained": attinge i dati direttamente da modelli/servizi
     * propri (es. AgenziaEntrateXmlFormat genera LIPE da LiquidazioneIva,
     * indipendentemente dalle DataSource selezionate).
     *
     * Quando false:
     *  - La UI nasconde lo step "Tabelle" se è l'unico formato selezionato
     *  - Il controller accetta data_types vuoto
     *  - generate() ignora il parametro $dataSources
     */
    public function requiresDataSources(): bool;

    /**
     * Genera il file di output nella working directory specificata.
     *
     * @param  ConsultantExportBundle  $bundle       Bundle in corso di generazione
     * @param  array<DataSource>       $dataSources  Sorgenti dati (già filtrate per supportedDataSources)
     * @param  string                  $workDir      Directory temp dove scrivere il file
     * @param  callable|null           $progress     Callback(int $processedRows, int $totalRows) per status update
     *
     * @return array<string>  Lista path file generati (relativi a $workDir), da includere nel ZIP finale
     *
     * @throws \Throwable  Se la generazione fallisce
     */
    public function generate(
        ConsultantExportBundle $bundle,
        array $dataSources,
        string $workDir,
        ?callable $progress = null,
    ): array;
}
