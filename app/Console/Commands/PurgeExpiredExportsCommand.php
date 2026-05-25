<?php

namespace App\Console\Commands;

use App\Models\ConsultantExportBundle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pulisce i bundle di export scaduti.
 *
 * - Trova bundle con expires_at < now()
 * - Cancella il file ZIP dal disk
 * - Marca lo stato come `expired` (mantiene il record per audit/storico)
 *
 * Schedulato giornalmente nello scheduler.
 */
class PurgeExpiredExportsCommand extends Command
{
    protected $signature = 'consultant:purge-expired-exports {--dry-run : Solo log, no operazioni}';

    protected $description = 'Cancella i file dei bundle export scaduti e li marca come expired.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $bundles = ConsultantExportBundle::query()
            ->where('expires_at', '<', now())
            ->whereIn('status', [
                ConsultantExportBundle::STATUS_READY,
                ConsultantExportBundle::STATUS_FAILED,
            ])
            ->get();

        if ($bundles->isEmpty()) {
            $this->info('Nessun bundle scaduto da rimuovere.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s%d bundle scaduti trovati.',
            $dryRun ? '[DRY-RUN] ' : '',
            $bundles->count(),
        ));

        $disk           = Storage::disk(ConsultantExportBundle::storageDisk());
        $deletedFiles   = 0;
        $totalBytes     = 0;

        foreach ($bundles as $bundle) {
            if ($bundle->file_path && $disk->exists($bundle->file_path)) {
                $size = $disk->size($bundle->file_path);
                $totalBytes += $size;

                if (! $dryRun) {
                    $disk->delete($bundle->file_path);
                }
                $deletedFiles++;
                $this->line(sprintf('  - %s (%d bytes)', $bundle->id, $size));
            }

            if (! $dryRun) {
                $bundle->status    = ConsultantExportBundle::STATUS_EXPIRED;
                $bundle->file_path = null; // non più valido
                $bundle->save();
            }
        }

        $this->info(sprintf(
            '%sLiberati %s da %d file.',
            $dryRun ? '[DRY-RUN] ' : '',
            $this->formatBytes($totalBytes),
            $deletedFiles,
        ));

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return "{$bytes} B";
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $val = $bytes;
        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }
        return sprintf('%.1f %s', $val, $units[$i]);
    }
}
