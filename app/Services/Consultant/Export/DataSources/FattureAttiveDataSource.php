<?php

namespace App\Services\Consultant\Export\DataSources;

use App\Services\Consultant\Export\Contracts\DataSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sorgente dati: Fatture attive (di vendita) emesse nel periodo.
 *
 * Reference implementation di DataSource: tutti gli altri DataSource
 * seguono questo pattern (stream cursor, headers stabili, mapping minimale).
 *
 * Note di design:
 *  - Usa DB::table()->cursor() (non Eloquent::lazy()) per evitare hydration
 *    e bypass BelongsToTenant. Footprint memoria costante ~10MB.
 *  - Headers stabili: cambiare l'ordine BREAKS i CSV già scaricati dai clienti.
 *  - Date sempre in ISO YYYY-MM-DD (Excel/Calc le riconoscono).
 *  - Importi sempre con punto decimale, 2 cifre, no separatore migliaia.
 *  - cliente_id esportato come riferimento opaco: il nostro schema non ha
 *    ancora una tabella clienti normalizzata (vedi commento nella migration
 *    2026_04_22_100004_create_fatture_attive_table). Il consulente risolve
 *    il cliente sul proprio gestionale.
 */
class FattureAttiveDataSource implements DataSource
{
    public function key(): string         { return 'fatture_attive'; }
    public function label(): string       { return 'Fatture attive'; }
    public function description(): string { return 'Fatture di vendita emesse nel periodo, incluse note di credito e annullate.'; }
    public function fileName(): string    { return 'fatture_attive.csv'; }

    public function headers(): array
    {
        return [
            'numero_fattura',
            'sezionale',
            'anno',
            'progressivo',
            'data_fattura',
            'data_scadenza',
            'cliente_id',             // riferimento opaco (no join: tabella clienti non esiste)
            'tipo_documento',         // TD01, TD04, ...
            'esigibilita',            // immediata, differita, split_payment
            'imponibile_totale',
            'iva_totale',
            'totale_documento',
            'stato',                  // bozza, emessa, accettata, ...
            'stato_pagamento',        // da_incassare, incassata, ...
            'sdi_identificativo',
            'fattura_collegata_id',   // per note credito → fattura origine
            'note',
        ];
    }

    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator
    {
        $cursor = DB::table('fatture_attive')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_fattura', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->orderBy('data_fattura')
            ->orderBy('progressivo')
            ->select([
                'numero_fattura',
                'sezionale',
                'anno',
                'progressivo',
                'data_fattura',
                'data_scadenza',
                'cliente_id',
                'tipo_documento',
                'esigibilita',
                'imponibile_totale',
                'iva_totale',
                'totale_documento',
                'stato',
                'stato_pagamento',
                'sdi_identificativo',
                'fattura_collegata_id',
                'note',
            ])
            ->cursor(); // stream → niente OOM su 10k+ righe

        foreach ($cursor as $row) {
            yield [
                $row->numero_fattura,
                $row->sezionale,
                $row->anno,
                $row->progressivo,
                $row->data_fattura,
                $row->data_scadenza,
                $row->cliente_id,
                $row->tipo_documento,
                $row->esigibilita,
                $this->money($row->imponibile_totale),
                $this->money($row->iva_totale),
                $this->money($row->totale_documento),
                $row->stato,
                $row->stato_pagamento,
                $row->sdi_identificativo,
                $row->fattura_collegata_id,
                $this->cleanText($row->note),
            ];
        }
    }

    public function estimatedCount(string $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('fatture_attive')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_fattura', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Normalizza importi: punto decimale, 2 cifre, no separatore migliaia.
     * Esempio: 1234.5 → "1234.50"
     */
    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Rimuove caratteri problematici da campi testo per CSV multi-linea.
     * Mantiene l'utf-8, sostituisce CR/LF con spazio singolo.
     */
    private function cleanText(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
    }
}
