<?php

namespace App\Services\Consultant\Export\DataSources;

use App\Services\Consultant\Export\Contracts\DataSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SpeseDataSource implements DataSource
{
    public function key(): string         { return 'spese'; }
    public function label(): string       { return 'Spese'; }
    public function description(): string { return 'Uscite di cassa registrate nel periodo, con voce di rendiconto e gestione.'; }
    public function fileName(): string    { return 'spese.csv'; }

    public function headers(): array
    {
        return [
            'data',
            'amount',
            'description',
            'conto_id',
            'rendiconto_code',
            'gestione',          // istituzionale, commerciale, ecc.
            'competenza_cassa',
            'genera_prima_nota',
        ];
    }

    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator
    {
        $cursor = DB::table('spese')
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->select([
                'date', 'amount', 'description', 'conto_id',
                'rendiconto_code', 'gestione', 'competenza_cassa', 'genera_prima_nota',
            ])
            ->cursor();

        foreach ($cursor as $r) {
            yield [
                $r->date,
                $this->money($r->amount),
                $this->cleanText($r->description),
                $r->conto_id,
                $r->rendiconto_code,
                $r->gestione,
                $r->competenza_cassa ? '1' : '0',
                $r->genera_prima_nota ? '1' : '0',
            ];
        }
    }

    public function estimatedCount(string $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('spese')
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    private function money(mixed $v): string { return number_format((float) ($v ?? 0), 2, '.', ''); }
    private function cleanText(?string $v): string { return $v === null ? '' : trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''); }
}
