<?php

namespace App\Services\Consultant\Export;

use App\Models\ConsultantExportBundle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Orchestratore della generazione di un bundle di export.
 *
 * Flusso:
 *  1. Marca il bundle come `processing`
 *  2. Crea una working directory temporanea (sys_get_temp_dir())
 *  3. Per ogni formato selezionato:
 *      - Risolve i DataSource supportati
 *      - Esegue ExportFormat::generate() → produce N file in workDir
 *  4. Zippa tutti i file prodotti in un singolo .zip (streaming-friendly)
 *  5. Sposta lo ZIP nel disk consultant_exports al path bundle->generateFilePath()
 *  6. Aggiorna bundle con file_path, file_size_bytes, status=ready
 *  7. Pulisce la working directory
 *
 * Su errore: marca bundle come `failed` con error_message, pulisce tmp.
 *
 * Su cancel cooperativo: controlla bundle->refresh()->status a ogni step
 *   maggiore; se è `cancelled`, interrompe e pulisce.
 */
class ExportBundleGenerator
{
    public function __construct(
        private readonly ExportFormatRegistry $registry,
    ) {}

    /**
     * Genera il bundle. È idempotente solo se ri-chiamato dopo una failure
     * (lo stato torna a `processing`). Non riprende da metà: rigenera tutto.
     *
     * @throws Throwable  Rilancia per far retry-are il job
     */
    public function generate(ConsultantExportBundle $bundle): void
    {
        $workDir = $this->createWorkDir($bundle);

        try {
            $this->markProcessing($bundle);

            $producedFiles = $this->runFormats($bundle, $workDir);

            if ($this->isCancelled($bundle)) {
                Log::info("Bundle {$bundle->id} cancelled during generation");
                return;
            }

            if (empty($producedFiles)) {
                throw new RuntimeException('Nessun file prodotto dagli export. Verifica selezione formati/dati.');
            }

            $zipPath = $this->createZip($bundle, $workDir, $producedFiles);

            $this->storeFinal($bundle, $zipPath);

            $this->markReady($bundle);

        } catch (Throwable $e) {
            $this->markFailed($bundle, $e);
            throw $e; // rilancia per il retry del job
        } finally {
            $this->cleanupWorkDir($workDir);
        }
    }

    /* ── Step privati ─────────────────────────────────────────────────────── */

    private function markProcessing(ConsultantExportBundle $bundle): void
    {
        $bundle->status     = ConsultantExportBundle::STATUS_PROCESSING;
        $bundle->started_at = now();
        $bundle->save();
    }

    /**
     * Esegue tutti i formati selezionati, ritorna array di file relativi a $workDir.
     *
     * @return array<string>
     */
    private function runFormats(ConsultantExportBundle $bundle, string $workDir): array
    {
        $allProduced = [];

        foreach ((array) $bundle->formats as $formatKey) {
            if ($this->isCancelled($bundle)) {
                return $allProduced;
            }

            $format     = $this->registry->format($formatKey);
            $sources    = $this->registry->resolveDataSourcesFor($format, (array) $bundle->data_types);

            // I formati self-contained (es. XML AdE) NON richiedono DataSource:
            // generano i propri file attingendo da modelli/servizi specifici.
            // Per quelli, è normale che $sources sia [] — non skippare!
            if (empty($sources) && $format->requiresDataSources()) {
                Log::warning("Format {$formatKey} ha 0 DataSource supportati per bundle {$bundle->id}");
                continue;
            }

            $produced = $format->generate(
                $bundle,
                $sources,
                $workDir,
                fn (int $rows, int $total) => Log::debug("Bundle {$bundle->id} [{$formatKey}]: {$rows}/{$total} righe"),
            );

            $allProduced = array_merge($allProduced, $produced);
        }

        return $allProduced;
    }

    /**
     * Costruisce il file ZIP finale dentro $workDir (path assoluto).
     */
    private function createZip(ConsultantExportBundle $bundle, string $workDir, array $files): string
    {
        $zipPath = $workDir . DIRECTORY_SEPARATOR . 'bundle.zip';
        $zip     = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossibile creare ZIP: {$zipPath}");
        }

        // README con metadati
        $readme = $this->buildReadme($bundle, $files);
        $zip->addFromString('README.txt', $readme);

        foreach ($files as $relative) {
            $absolute = $workDir . DIRECTORY_SEPARATOR . $relative;
            if (! is_file($absolute)) {
                Log::warning("File atteso non trovato: {$absolute}");
                continue;
            }
            $zip->addFile($absolute, $relative);
        }

        if (! $zip->close()) {
            throw new RuntimeException('Errore in fase di chiusura ZIP');
        }

        return $zipPath;
    }

    /**
     * Sposta lo ZIP nel disk persistente e aggiorna il bundle.
     */
    private function storeFinal(ConsultantExportBundle $bundle, string $zipLocalPath): void
    {
        $relativePath = $bundle->generateFilePath();
        $disk         = Storage::disk(ConsultantExportBundle::storageDisk());

        $disk->put($relativePath, fopen($zipLocalPath, 'rb'));

        $bundle->file_path       = $relativePath;
        $bundle->file_size_bytes = $disk->size($relativePath);
        $bundle->save();
    }

    private function markReady(ConsultantExportBundle $bundle): void
    {
        $bundle->status       = ConsultantExportBundle::STATUS_READY;
        $bundle->completed_at = now();
        $bundle->save();
    }

    private function markFailed(ConsultantExportBundle $bundle, Throwable $e): void
    {
        $bundle->status        = ConsultantExportBundle::STATUS_FAILED;
        $bundle->error_message = sprintf('%s: %s', get_class($e), $e->getMessage());
        $bundle->completed_at  = now();
        $bundle->save();
    }

    /**
     * Controlla se il bundle è stato cancellato dall'utente (refresh dal DB).
     * Permette cancel cooperativo: il job si arresta gracefully tra uno step e l'altro.
     */
    private function isCancelled(ConsultantExportBundle $bundle): bool
    {
        return $bundle->fresh()?->status === ConsultantExportBundle::STATUS_CANCELLED;
    }

    private function createWorkDir(ConsultantExportBundle $bundle): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'export-' . $bundle->id . '-' . Str::random(8);
        if (! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException("Impossibile creare working directory: {$dir}");
        }
        return $dir;
    }

    private function cleanupWorkDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . '*') ?: [];
        foreach ($files as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    private function buildReadme(ConsultantExportBundle $bundle, array $files): string
    {
        $lines = [
            "Bundle di export — Tessera/ETS-OK",
            "==================================",
            "",
            "Bundle ID:     {$bundle->id}",
            "Tenant ID:     {$bundle->tenant_id}",
            "Periodo:       {$bundle->periodLabel()}",
            "Generato:      " . now()->format('Y-m-d H:i:s'),
            "Scade:         " . $bundle->expires_at->format('Y-m-d H:i:s'),
            "",
            "Formati:       " . implode(', ', (array) $bundle->formats),
            "Tabelle:       " . implode(', ', (array) $bundle->data_types),
            "",
            "File inclusi:",
        ];
        foreach ($files as $f) {
            $lines[] = "  - {$f}";
        }
        $lines[] = "";
        $lines[] = "Generato da Tessera/ETS-OK. Per supporto: contatta l'amministratore del tenant.";
        return implode("\r\n", $lines);
    }
}
