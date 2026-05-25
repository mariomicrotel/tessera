<?php

namespace App\Services\Consultant\Export\DataSources;

use App\Services\Consultant\Export\Contracts\DataSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sorgente dati: Cespiti (assets) — registro beni ammortizzabili.
 *
 * Filtra per data_inizio_ammortamento dentro il periodo (cespiti acquistati
 * o entrati in ammortamento nel periodo). Esclude cespiti dismessi.
 *
 * Per il commercialista è essenziale per la quadratura ammortamenti
 * e per IMU/TASI sui beni immobili.
 */
class CespitiDataSource implements DataSource
{
    public function key(): string         { return 'cespiti'; }
    public function label(): string       { return 'Cespiti'; }
    public function description(): string { return 'Beni ammortizzabili acquistati o entrati in ammortamento nel periodo.'; }
    public function fileName(): string    { return 'cespiti.csv'; }

    public function headers(): array
    {
        return [
            'code',
            'name',
            'matricola',
            'asset_category_id',
            'supplier_id',
            'fattura_passiva_id',
            'purchase_date',
            'data_inizio_ammortamento',
            'value',
            'costo_storico',
            'aliquota_custom',
            'metodo_ammortamento',
            'primo_anno_ridotto',
            'percentuale_deducibilita',
            'stato',
            'property_id',
            'note_fiscali',
        ];
    }

    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator
    {
        $cursor = DB::table('assets')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
                  ->orWhereBetween('data_inizio_ammortamento', [$from->toDateString(), $to->toDateString()]);
            })
            ->whereNull('deleted_at')
            ->orderBy('purchase_date')
            ->cursor();

        foreach ($cursor as $r) {
            yield [
                $r->code,
                $r->name,
                $r->matricola,
                $r->asset_category_id,
                $r->supplier_id,
                $r->fattura_passiva_id,
                $r->purchase_date,
                $r->data_inizio_ammortamento,
                $this->money($r->value),
                $this->money($r->costo_storico),
                $r->aliquota_custom,
                $r->metodo_ammortamento,
                $r->primo_anno_ridotto ? '1' : '0',
                $r->percentuale_deducibilita,
                $r->stato,
                $r->property_id,
                $this->cleanText($r->note_fiscali),
            ];
        }
    }

    public function estimatedCount(string $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('assets')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
                  ->orWhereBetween('data_inizio_ammortamento', [$from->toDateString(), $to->toDateString()]);
            })
            ->whereNull('deleted_at')
            ->count();
    }

    private function money(mixed $v): string { return number_format((float) ($v ?? 0), 2, '.', ''); }
    private function cleanText(?string $v): string { return $v === null ? '' : trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''); }
}
