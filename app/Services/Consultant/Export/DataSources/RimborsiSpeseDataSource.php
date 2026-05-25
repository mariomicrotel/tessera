<?php

namespace App\Services\Consultant\Export\DataSources;

use App\Services\Consultant\Export\Contracts\DataSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sorgente dati: Rimborsi spese a soci/volontari.
 *
 * Filtra per refund_date nel periodo. Include solo refund con status finale
 * (approvato/pagato) ed esclude bozze in revisione, così il consulente non
 * vede importi che potrebbero ancora variare.
 */
class RimborsiSpeseDataSource implements DataSource
{
    public function key(): string         { return 'rimborsi_spese'; }
    public function label(): string       { return 'Rimborsi spese'; }
    public function description(): string { return 'Rimborsi spese a soci/volontari approvati o pagati nel periodo.'; }
    public function fileName(): string    { return 'rimborsi_spese.csv'; }

    public function headers(): array
    {
        return [
            'refund_date',
            'member_id',
            'total',
            'status',
            'receipt_id',
        ];
    }

    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator
    {
        $cursor = DB::table('expense_refunds')
            ->where('tenant_id', $tenantId)
            ->whereBetween('refund_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('refund_date')
            ->cursor();

        foreach ($cursor as $r) {
            yield [
                $r->refund_date,
                $r->member_id,
                $this->money($r->total),
                $r->status,
                $r->receipt_id,
            ];
        }
    }

    public function estimatedCount(string $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('expense_refunds')
            ->where('tenant_id', $tenantId)
            ->whereBetween('refund_date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    private function money(mixed $v): string { return number_format((float) ($v ?? 0), 2, '.', ''); }
}
