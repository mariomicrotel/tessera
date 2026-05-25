<?php

namespace App\Services\Consultant\Export\DataSources;

use App\Services\Consultant\Export\Contracts\DataSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sorgente dati: Modelli F24 (versamenti tributari).
 *
 * Filtra per data_versamento. Inclusi solo gli F24 con stato finale
 * (versato / non versato) — gli F24 in bozza vanno comunque inclusi
 * per dare al consulente visibilità sulle preparazioni in corso.
 *
 * Per il dettaglio righe (codici tributo, importi) il commercialista
 * può richiedere il PDF del singolo F24; qui esponiamo solo il riepilogo.
 */
class ModelliF24DataSource implements DataSource
{
    public function key(): string         { return 'modelli_f24'; }
    public function label(): string       { return 'Modelli F24'; }
    public function description(): string { return 'Riepilogo F24 con totali debiti, crediti, saldo per anno/mese.'; }
    public function fileName(): string    { return 'modelli_f24.csv'; }

    public function headers(): array
    {
        return [
            'anno',
            'mese',
            'data_compilazione',
            'data_versamento',
            'stato',                  // bozza, compilato, versato
            'totale_debiti',
            'totale_crediti',
            'saldo',
            'liquidazione_iva_id',    // se è F24 da liquidazione IVA
            'versamento_ritenuta_id', // se è F24 per ritenuta
            'note',
        ];
    }

    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator
    {
        $cursor = DB::table('modelli_f24')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($from, $to) {
                // Includi sia per data_versamento sia per data_compilazione,
                // così il consulente vede anche F24 preparati ma non ancora versati nel periodo.
                $q->whereBetween('data_versamento', [$from->toDateString(), $to->toDateString()])
                  ->orWhereBetween('data_compilazione', [$from->toDateString(), $to->toDateString()]);
            })
            ->orderBy('anno')
            ->orderBy('mese')
            ->cursor();

        foreach ($cursor as $r) {
            yield [
                $r->anno,
                $r->mese,
                $r->data_compilazione,
                $r->data_versamento,
                $r->stato,
                $this->money($r->totale_debiti),
                $this->money($r->totale_crediti),
                $this->money($r->saldo),
                $r->liquidazione_iva_id,
                $r->versamento_ritenuta_id,
                $this->cleanText($r->note),
            ];
        }
    }

    public function estimatedCount(string $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('modelli_f24')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('data_versamento', [$from->toDateString(), $to->toDateString()])
                  ->orWhereBetween('data_compilazione', [$from->toDateString(), $to->toDateString()]);
            })
            ->count();
    }

    private function money(mixed $v): string { return number_format((float) ($v ?? 0), 2, '.', ''); }
    private function cleanText(?string $v): string { return $v === null ? '' : trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''); }
}
