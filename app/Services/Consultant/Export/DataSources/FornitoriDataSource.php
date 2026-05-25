<?php

namespace App\Services\Consultant\Export\DataSources;

use App\Services\Consultant\Export\Contracts\DataSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sorgente dati: Anagrafica fornitori.
 *
 * Esporta solo i fornitori che hanno emesso almeno una fattura nel periodo
 * (filtro implicito via INNER JOIN con fatture_passive). Così evitiamo di
 * scaricare anagrafiche storiche non pertinenti al periodo richiesto.
 *
 * Se serve l'anagrafica completa (per migrazioni o backup), il consulente
 * può richiederla esplicitamente con `?all=1` (TODO Day 4 nella UI).
 */
class FornitoriDataSource implements DataSource
{
    public function key(): string         { return 'fornitori'; }
    public function label(): string       { return 'Anagrafica fornitori'; }
    public function description(): string { return 'Fornitori che hanno emesso almeno una fattura nel periodo selezionato.'; }
    public function fileName(): string    { return 'fornitori.csv'; }

    public function headers(): array
    {
        return [
            'id',
            'name',
            'ragione_sociale',
            'partita_iva',
            'codice_fiscale',
            'codice_sdi',
            'pec',
            'email',
            'phone',
            'indirizzo',
            'cap',
            'citta',
            'provincia',
            'nazione',
            'iban',
            'condizioni_pagamento',
            'categoria',
            'attivo',
            'note',
        ];
    }

    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator
    {
        // DISTINCT supplier_id usati nel periodo, poi pesco le anagrafiche
        $supplierIdsUsed = DB::table('fatture_passive')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_fattura', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->whereNotNull('supplier_id')
            ->distinct()
            ->pluck('supplier_id')
            ->all();

        if (empty($supplierIdsUsed)) {
            return;
        }

        $cursor = DB::table('suppliers')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $supplierIdsUsed)
            ->whereNull('deleted_at')
            ->orderBy('ragione_sociale')
            ->orderBy('name')
            ->cursor();

        foreach ($cursor as $r) {
            yield [
                $r->id,
                $r->name,
                $r->ragione_sociale,
                $r->partita_iva,
                $r->codice_fiscale,
                $r->codice_sdi,
                $r->pec,
                $r->email,
                $r->phone,
                $this->cleanText($r->indirizzo),
                $r->cap,
                $r->citta,
                $r->provincia,
                $r->nazione,
                $r->iban,
                $r->condizioni_pagamento,
                $r->categoria,
                $r->attivo ? '1' : '0',
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
            ->whereNotNull('supplier_id')
            ->distinct('supplier_id')
            ->count('supplier_id');
    }

    private function cleanText(?string $v): string { return $v === null ? '' : trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''); }
}
