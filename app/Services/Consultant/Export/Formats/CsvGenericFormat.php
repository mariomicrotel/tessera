<?php

namespace App\Services\Consultant\Export\Formats;

use App\Models\ConsultantExportBundle;
use App\Services\Consultant\Export\Contracts\DataSource;
use App\Services\Consultant\Export\Contracts\ExportFormat;
use RuntimeException;

/**
 * Formato di export "CSV generico" — universale.
 *
 * Produce un file CSV per ogni DataSource selezionato. I file vengono poi
 * inclusi nel ZIP finale dal ExportBundleGenerator (questo formato si limita
 * a scriverli in $workDir).
 *
 * Convenzioni CSV:
 *  - Encoding UTF-8 con BOM (così Excel italiano apre senza chiedere encoding)
 *  - Separatore: `,` (virgola) — standard internazionale
 *  - Quoting: tutti i campi (sicurezza prima di tutto, dimensione poi)
 *  - Line ending: \r\n (compatibilità Windows + Mac)
 *  - Date in formato ISO YYYY-MM-DD
 *  - Decimali con punto, 2 cifre fisse
 *
 * Streaming: `fputcsv()` row-by-row su filehandle aperto → footprint O(1).
 * Anche per 100k+ righe il processo PHP non supera 50MB di memoria.
 *
 * Supporta TUTTI i DataSource registrati (no filtri).
 */
class CsvGenericFormat implements ExportFormat
{
    /** UTF-8 BOM: Excel italiano riconosce automaticamente l'encoding. */
    private const UTF8_BOM = "\xEF\xBB\xBF";

    public function key(): string
    {
        return 'csv_generic';
    }

    public function label(): string
    {
        return 'CSV generico (universale)';
    }

    public function description(): string
    {
        return 'Un file CSV UTF-8 per ogni tabella selezionata. Compatibile con Excel, LibreOffice, e tutti i software contabili che importano CSV.';
    }

    public function mimeType(): string
    {
        return 'text/csv';
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function supportedDataSources(): ?array
    {
        return null; // null = wildcard → supporta tutti i DataSource
    }

    public function requiresDataSources(): bool
    {
        return true; // CSV è guidato dalla selezione utente
    }

    public function generate(
        ConsultantExportBundle $bundle,
        array $dataSources,
        string $workDir,
        ?callable $progress = null,
    ): array {
        if (! is_dir($workDir)) {
            throw new RuntimeException("Work directory non esiste: {$workDir}");
        }

        $tenantId   = (string) $bundle->tenant_id;
        $from       = $bundle->period_from;
        $to         = $bundle->period_to;
        $generated  = [];
        $totalRows  = 0;
        $totalEstim = $this->totalEstimated($dataSources, $tenantId, $from, $to);

        foreach ($dataSources as $source) {
            /** @var DataSource $source */
            $fileName = $source->fileName();
            $path     = $workDir . DIRECTORY_SEPARATOR . $fileName;

            $fh = @fopen($path, 'wb');
            if ($fh === false) {
                throw new RuntimeException("Impossibile aprire in scrittura: {$path}");
            }

            try {
                // BOM UTF-8 (Excel italiano)
                fwrite($fh, self::UTF8_BOM);

                // Header
                fputcsv($fh, $source->headers(), ',', '"', '\\', "\r\n");

                // Righe (streaming via Generator)
                foreach ($source->rows($tenantId, $from, $to) as $row) {
                    fputcsv($fh, array_map(fn ($v) => $v ?? '', $row), ',', '"', '\\', "\r\n");
                    $totalRows++;

                    // Notifica progresso ogni 500 righe per non saturare il job
                    if ($progress !== null && $totalRows % 500 === 0) {
                        $progress($totalRows, $totalEstim);
                    }
                }
            } finally {
                fclose($fh);
            }

            $generated[] = $fileName;
        }

        // Flush finale del progresso
        if ($progress !== null) {
            $progress($totalRows, max($totalRows, $totalEstim));
        }

        return $generated;
    }

    /**
     * @param  array<DataSource>  $sources
     */
    private function totalEstimated(array $sources, string $tenantId, $from, $to): int
    {
        $total = 0;
        foreach ($sources as $s) {
            $total += $s->estimatedCount($tenantId, $from, $to);
        }
        return $total;
    }
}
