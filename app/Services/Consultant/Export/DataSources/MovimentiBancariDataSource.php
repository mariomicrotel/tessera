<?php

namespace App\Services\Consultant\Export\DataSources;

use App\Services\Consultant\Export\Contracts\DataSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sorgente dati: Movimenti bancari da estratti conto importati.
 *
 * Filtra per data_valuta (data effettiva del movimento, non data registrazione).
 * Include flag riconciliato per dare al commercialista visibilità su cosa
 * è già stato matchato con fatture/incassi/spese e cosa è ancora sospeso.
 */
class MovimentiBancariDataSource implements DataSource
{
    public function key(): string         { return 'movimenti_bancari'; }
    public function label(): string       { return 'Movimenti bancari'; }
    public function description(): string { return 'Movimenti bancari estratti dai conti, con flag di riconciliazione.'; }
    public function fileName(): string    { return 'movimenti_bancari.csv'; }

    public function headers(): array
    {
        return [
            'data_valuta',
            'data_contabile',
            'tipo',            // dare, avere
            'importo',
            'descrizione',
            'riferimento',
            'riconciliato',    // 0/1
            'estratto_conto_id',
        ];
    }

    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator
    {
        $cursor = DB::table('movimenti_bancari')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_valuta', [$from->toDateString(), $to->toDateString()])
            ->orderBy('data_valuta')
            ->select([
                'data_valuta', 'data_contabile', 'tipo', 'importo',
                'descrizione', 'riferimento', 'riconciliato', 'estratto_conto_id',
            ])
            ->cursor();

        foreach ($cursor as $r) {
            yield [
                $r->data_valuta,
                $r->data_contabile,
                $r->tipo,
                $this->money($r->importo),
                $this->cleanText($r->descrizione),
                $r->riferimento,
                $r->riconciliato ? '1' : '0',
                $r->estratto_conto_id,
            ];
        }
    }

    public function estimatedCount(string $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('movimenti_bancari')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_valuta', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    private function money(mixed $v): string { return number_format((float) ($v ?? 0), 2, '.', ''); }
    private function cleanText(?string $v): string { return $v === null ? '' : trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''); }
}
