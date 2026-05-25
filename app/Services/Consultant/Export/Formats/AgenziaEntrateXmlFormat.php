<?php

namespace App\Services\Consultant\Export\Formats;

use App\Models\ConsultantExportBundle;
use App\Models\Tenant;
use App\Services\Consultant\Export\Contracts\ExportFormat;
use App\Services\LipeXmlService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Formato Agenzia Entrate XML — LIPE (Liquidazione Periodica IVA).
 *
 * **Self-contained**: attinge i dati direttamente dal modello LiquidazioneIva
 * tramite LipeXmlService::genera(), ignorando i DataSource selezionati
 * dall'utente. La selezione "tabelle" non si applica a questo formato.
 *
 * **Periodo → trimestri**: il bundle ha un range [from, to] generico;
 * questo formato lo trasforma nei trimestri AdE compresi nel range e produce
 * un XML separato per ogni trimestre. Esempio:
 *  - bundle 2026-01-01 → 2026-12-31  → 4 file (T1, T2, T3, T4 2026)
 *  - bundle 2025-06-01 → 2026-03-31  → 4 file (T2/T3/T4 2025 + T1 2026)
 *  - bundle 2026-01-01 → 2026-01-31  → 1 file (T1 2026 con dati di gennaio)
 *
 * **Validazione XSD**: Il LipeXmlService genera XML conforme allo schema
 * "ComunicazioneLiquidazioni_v1.1.xsd" dell'Agenzia Entrate. La validazione
 * XSD richiede il file ufficiale (non incluso nel repo per evitare
 * incertezze di copyright/versioning). Il consulente può validare l'XML
 * con il software di controllo gratuito dell'AdE prima dell'invio.
 *
 * **Per aggiungere altri formati AdE** (es. esterometro, dichiarazione IVA
 * annuale) si possono aggiungere metodi `generateEsterometro($bundle, $year)`
 * sul service e instradare nello switch del generate(). Per ora solo LIPE.
 */
class AgenziaEntrateXmlFormat implements ExportFormat
{
    public function __construct(
        private readonly LipeXmlService $lipeService,
    ) {}

    public function key(): string
    {
        return 'agenzia_entrate_xml';
    }

    public function label(): string
    {
        return 'XML Agenzia Entrate (LIPE)';
    }

    public function description(): string
    {
        return 'Liquidazione Periodica IVA in XML conforme allo schema ComunicazioneLiquidazioni v1.1. Un file per ogni trimestre compreso nel periodo selezionato. Da inviare tramite Entratel o validare con il software AdE.';
    }

    public function mimeType(): string
    {
        return 'application/xml';
    }

    public function extension(): string
    {
        return 'xml';
    }

    public function supportedDataSources(): ?array
    {
        return []; // [] = nessun DataSource (formato self-contained)
    }

    public function requiresDataSources(): bool
    {
        return false; // il formato attinge da LiquidazioneIva, non dalle DataSource
    }

    public function generate(
        ConsultantExportBundle $bundle,
        array $dataSources,    // ignorato per questo formato
        string $workDir,
        ?callable $progress = null,
    ): array {
        $tenant = Tenant::find($bundle->tenant_id);
        if (! $tenant) {
            throw new RuntimeException("Tenant non trovato per bundle {$bundle->id}");
        }

        $trimesters = $this->trimestersInRange(
            Carbon::parse($bundle->period_from),
            Carbon::parse($bundle->period_to),
        );

        if (empty($trimesters)) {
            throw new RuntimeException('Nessun trimestre identificabile nel periodo richiesto');
        }

        $generated = [];
        $totalQuarters = count($trimesters);
        $i = 0;

        foreach ($trimesters as [$anno, $trimestre]) {
            $i++;

            try {
                $xml = $this->lipeService->genera($tenant, $anno, $trimestre);
            } catch (\Throwable $e) {
                // Se un trimestre non ha dati o fallisce, log e continua
                // (non bloccare l'intero bundle per un solo trimestre vuoto).
                Log::warning(
                    "LIPE T{$trimestre}/{$anno} per bundle {$bundle->id} fallito: {$e->getMessage()}"
                );
                continue;
            }

            // Sanity check minimal: deve contenere l'elemento root atteso
            if (! str_contains($xml, 'ComunicazioneLiquidazioniPeriodiche')) {
                Log::warning("LIPE T{$trimestre}/{$anno}: XML malformato, skip");
                continue;
            }

            $fileName = sprintf('LIPE_%d_T%d.xml', $anno, $trimestre);
            $path     = $workDir . DIRECTORY_SEPARATOR . $fileName;

            if (file_put_contents($path, $xml) === false) {
                throw new RuntimeException("Impossibile scrivere {$path}");
            }

            $generated[] = $fileName;

            if ($progress !== null) {
                $progress($i, $totalQuarters);
            }
        }

        if (empty($generated)) {
            throw new RuntimeException(
                'Nessun trimestre LIPE generato nel periodo. Verifica di avere ' .
                'liquidazioni IVA definitive o fatture nel periodo.'
            );
        }

        return $generated;
    }

    /**
     * Restituisce tutte le coppie [anno, trimestre] che intersecano [from, to].
     *
     * @return array<int, array{0:int, 1:int}>
     */
    private function trimestersInRange(Carbon $from, Carbon $to): array
    {
        $cursor = $from->copy()->startOfQuarter();
        $end    = $to->copy()->endOfQuarter();
        $result = [];

        // Safety: max 40 trimestri (10 anni) per evitare loop su date errate
        $safety = 40;

        while ($cursor->lessThanOrEqualTo($end) && $safety-- > 0) {
            $result[] = [(int) $cursor->year, (int) $cursor->quarter];
            $cursor->addQuarter();
        }

        return $result;
    }
}
