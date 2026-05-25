<?php

namespace App\Services\Consultant\Export\DataSources;

use App\Services\Consultant\Export\Contracts\DataSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sorgente dati: Incassi (quote associative, donazioni, altri).
 *
 * Per ogni incasso esponiamo anche l'eventuale member_id (socio) o donor_name
 * (donatore non socio). Per le cooperative sono inclusi anche capitale e
 * prestito sociale come righe con `type` specifico.
 */
class IncassiDataSource implements DataSource
{
    public function key(): string         { return 'incassi'; }
    public function label(): string       { return 'Incassi'; }
    public function description(): string { return 'Quote associative, donazioni, versamenti capitale sociale, depositi prestito sociale ricevuti nel periodo.'; }
    public function fileName(): string    { return 'incassi.csv'; }

    public function headers(): array
    {
        return [
            'data_incasso',
            'tipo',                  // quota, donazione, altro, capitale, prestito_sociale
            'amount',
            'member_id',             // null se donor esterno
            'donor_name',            // null se socio
            'conto_id',
            'description',
            'receipt_issued_at',
            'genera_prima_nota',
        ];
    }

    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator
    {
        $cursor = DB::table('incassi')
            ->where('tenant_id', $tenantId)
            ->whereBetween('paid_at', [$from->toDateString() . ' 00:00:00', $to->toDateString() . ' 23:59:59'])
            ->orderBy('paid_at')
            ->select([
                'paid_at',
                'type',
                'amount',
                'member_id',
                'donor_name',
                'conto_id',
                'description',
                'receipt_issued_at',
                'genera_prima_nota',
            ])
            ->cursor();

        foreach ($cursor as $r) {
            yield [
                $r->paid_at,
                $r->type,
                $this->money($r->amount),
                $r->member_id,
                $r->donor_name,
                $r->conto_id,
                $this->cleanText($r->description),
                $r->receipt_issued_at,
                $r->genera_prima_nota ? '1' : '0',
            ];
        }
    }

    public function estimatedCount(string $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('incassi')
            ->where('tenant_id', $tenantId)
            ->whereBetween('paid_at', [$from->toDateString() . ' 00:00:00', $to->toDateString() . ' 23:59:59'])
            ->count();
    }

    private function money(mixed $v): string { return number_format((float) ($v ?? 0), 2, '.', ''); }
    private function cleanText(?string $v): string { return $v === null ? '' : trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''); }
}
