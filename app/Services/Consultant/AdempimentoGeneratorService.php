<?php

namespace App\Services\Consultant;

use App\Models\AdempimentoItem;
use App\Models\AdempimentoTemplate;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Genera le istanze di AdempimentoItem per (tenant, anno).
 *
 * Strategy:
 *  - Recupera tutti i template attivi applicabili al tipo di organizzazione del tenant
 *  - Per ogni template, itera i "periodi nell'anno" (mensili: 01..12, trimestrali: T1..T4)
 *  - Per ogni (template, anno, periodo), crea l'item solo se non esiste (idempotente)
 *
 * Idempotenza garantita dall'unique key (tenant_id, template_id, anno, periodo).
 */
class AdempimentoGeneratorService
{
    /**
     * Genera tutti gli adempimenti per il tenant nell'anno indicato.
     *
     * @return array{created:int, skipped:int, errors:array<string>}
     */
    public function generaPerAnno(Tenant $tenant, int $anno): array
    {
        $stats = ['created' => 0, 'skipped' => 0, 'errors' => []];

        $templates = AdempimentoTemplate::query()
            ->attivi()
            ->applicabiliA($tenant->organization_type ?? 'ets')
            ->get();

        DB::transaction(function () use ($tenant, $anno, $templates, &$stats) {
            foreach ($templates as $template) {
                $periodi = $template->periodiNellAnno();

                // Per templates una_tantum/biennali non genera nulla automaticamente:
                // saranno aggiunti manualmente quando rilevanti per il tenant.
                if (empty($periodi)) {
                    continue;
                }

                // Biennale: skippa anni dispari (convenzione: si parte dagli anni pari)
                if ($template->periodicita === AdempimentoTemplate::PERIODICITA_BIENNALE
                    && $anno % 2 !== 0) {
                    continue;
                }

                foreach ($periodi as $periodo) {
                    try {
                        $scadenza = $template->calcolaScadenza($anno, $periodo);
                    } catch (\Throwable $e) {
                        $stats['errors'][] = "Template {$template->codice} periodo {$periodo}: {$e->getMessage()}";
                        continue;
                    }

                    $exists = AdempimentoItem::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('template_id', $template->id)
                        ->where('anno', $anno)
                        ->where(function ($q) use ($periodo) {
                            if ($periodo === null) {
                                $q->whereNull('periodo');
                            } else {
                                $q->where('periodo', $periodo);
                            }
                        })
                        ->exists();

                    if ($exists) {
                        $stats['skipped']++;
                        continue;
                    }

                    AdempimentoItem::create([
                        'tenant_id'      => $tenant->id,
                        'template_id'    => $template->id,
                        'anno'           => $anno,
                        'periodo'        => $periodo,
                        'data_scadenza'  => $scadenza->toDateString(),
                        'stato'          => AdempimentoItem::STATO_DA_FARE,
                    ]);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    /**
     * Genera una singola istanza una_tantum per un template (es. EAS, 5x1000).
     */
    public function generaUnaTantum(Tenant $tenant, AdempimentoTemplate $template, int $anno): AdempimentoItem
    {
        $scadenza = $template->calcolaScadenza($anno, null);

        return AdempimentoItem::firstOrCreate(
            [
                'tenant_id'   => $tenant->id,
                'template_id' => $template->id,
                'anno'        => $anno,
                'periodo'     => null,
            ],
            [
                'data_scadenza' => $scadenza->toDateString(),
                'stato'         => AdempimentoItem::STATO_DA_FARE,
            ],
        );
    }
}
