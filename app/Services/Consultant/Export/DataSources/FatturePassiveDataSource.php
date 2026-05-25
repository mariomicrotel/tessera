<?php

namespace App\Services\Consultant\Export\DataSources;

use App\Services\Consultant\Export\Contracts\DataSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sorgente dati: Fatture passive (di acquisto) ricevute nel periodo.
 *
 * Join con `suppliers` per arricchire la riga con denominazione, P.IVA, CF
 * del fornitore (utile al commercialista per CU/770/registro IVA acquisti).
 */
class FatturePassiveDataSource implements DataSource
{
    public function key(): string         { return 'fatture_passive'; }
    public function label(): string       { return 'Fatture passive'; }
    public function description(): string { return 'Fatture di acquisto ricevute nel periodo, con dati anagrafici dei fornitori.'; }
    public function fileName(): string    { return 'fatture_passive.csv'; }

    public function headers(): array
    {
        return [
            'numero_fattura',
            'data_fattura',
            'data_ricezione',
            'data_registrazione',
            'data_scadenza',
            'supplier_id',
            'fornitore_ragione_sociale',
            'fornitore_partita_iva',
            'fornitore_codice_fiscale',
            'fornitore_codice_sdi',
            'tipo_documento',
            'esigibilita',
            'imponibile_totale',
            'iva_totale',
            'totale_documento',
            'stato_pagamento',
            'note',
        ];
    }

    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator
    {
        $cursor = DB::table('fatture_passive as fp')
            ->leftJoin('suppliers as s', 's.id', '=', 'fp.supplier_id')
            ->where('fp.tenant_id', $tenantId)
            ->whereBetween('fp.data_fattura', [$from->toDateString(), $to->toDateString()])
            ->whereNull('fp.deleted_at')
            ->orderBy('fp.data_fattura')
            ->orderBy('fp.numero_fattura')
            ->select([
                'fp.numero_fattura',
                'fp.data_fattura',
                'fp.data_ricezione',
                'fp.data_registrazione',
                'fp.data_scadenza',
                'fp.supplier_id',
                's.ragione_sociale as fornitore_ragione_sociale',
                's.partita_iva as fornitore_partita_iva',
                's.codice_fiscale as fornitore_codice_fiscale',
                's.codice_sdi as fornitore_codice_sdi',
                'fp.tipo_documento',
                'fp.esigibilita',
                'fp.imponibile_totale',
                'fp.iva_totale',
                'fp.totale_documento',
                'fp.stato_pagamento',
                'fp.note',
            ])
            ->cursor();

        foreach ($cursor as $r) {
            yield [
                $r->numero_fattura,
                $r->data_fattura,
                $r->data_ricezione,
                $r->data_registrazione,
                $r->data_scadenza,
                $r->supplier_id,
                $r->fornitore_ragione_sociale,
                $r->fornitore_partita_iva,
                $r->fornitore_codice_fiscale,
                $r->fornitore_codice_sdi,
                $r->tipo_documento,
                $r->esigibilita,
                $this->money($r->imponibile_totale),
                $this->money($r->iva_totale),
                $this->money($r->totale_documento),
                $r->stato_pagamento,
                $this->cleanText($r->note),
            ];
        }
    }

    public function estimatedCount(string $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('fatture_passive')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_fattura', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->count();
    }

    private function money(mixed $v): string { return number_format((float) ($v ?? 0), 2, '.', ''); }
    private function cleanText(?string $v): string { return $v === null ? '' : trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''); }
}
