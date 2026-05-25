<?php

namespace App\Jobs;

use App\Models\ConsultantExportBundle;
use App\Notifications\ExportBundleFailedNotification;
use App\Notifications\ExportBundleReadyNotification;
use App\Services\Consultant\Export\ExportBundleGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job che esegue la generazione di un ConsultantExportBundle in coda.
 *
 * - Long-running: timeout 600s (10 min) per bundle grandi
 * - Memory: 256MB (lo streaming dovrebbe stare ben sotto, ma diamo margine)
 * - Retry: 1 solo tentativo automatico (export consumano risorse, meglio failure
 *   visibile che retry silenziosi che mascherano bug)
 */
class GenerateExportBundleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tentativi totali (incluso il primo). */
    public int $tries = 2;

    /** Timeout in secondi. Bundle molto grossi possono richiederne di più. */
    public int $timeout = 600;

    /** Backoff tra tentativi (secondi). */
    public int $backoff = 30;

    public function __construct(
        public string $bundleId,
    ) {}

    public function handle(ExportBundleGenerator $generator): void
    {
        $bundle = ConsultantExportBundle::find($this->bundleId);

        if (! $bundle) {
            Log::warning("Bundle {$this->bundleId} non trovato, abort");
            return;
        }

        // Se già stato cancellato dall'utente mentre era in coda, esci
        if ($bundle->isCancelled()) {
            Log::info("Bundle {$this->bundleId} già cancellato, abort");
            return;
        }

        // Idempotenza: se è già ready/failed, non rifare
        if ($bundle->isReady()) {
            Log::info("Bundle {$this->bundleId} già ready, skip");
            return;
        }

        $generator->generate($bundle);

        // Notifica il consulente (mail + in-app database)
        $bundle->refresh();
        if ($bundle->isReady() && $bundle->consultant) {
            $bundle->consultant->notify(new ExportBundleReadyNotification($bundle));
        }
    }

    /**
     * Chiamato quando tutti i tentativi falliscono.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Bundle {$this->bundleId} fallito definitivamente: {$exception->getMessage()}");

        $bundle = ConsultantExportBundle::find($this->bundleId);
        if (! $bundle) {
            return;
        }

        // Se per qualche ragione lo stato non era già stato impostato a failed dal generator
        if (! $bundle->isTerminal()) {
            $bundle->status        = ConsultantExportBundle::STATUS_FAILED;
            $bundle->error_message = sprintf('%s: %s', get_class($exception), $exception->getMessage());
            $bundle->completed_at  = now();
            $bundle->save();
        }

        if ($bundle->consultant) {
            $bundle->consultant->notify(new ExportBundleFailedNotification($bundle));
        }
    }
}
