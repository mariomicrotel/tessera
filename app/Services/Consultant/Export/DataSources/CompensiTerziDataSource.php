<?php

namespace App\Services\Consultant\Export\DataSources;

use App\Services\Consultant\Export\Contracts\DataSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sorgente dati: Compensi a terzi (collaboratori, prestatori occasionali, ecc.).
 *
 * Dati essenziali per la Certificazione Unica e il modello 770:
 * percipiente, CF, P.IVA, causale, importo lordo, ritenute, INPS,
 * compenso netto, stato ritenuta.
 */
class CompensiTerziDataSource implements DataSource
{
    public function key(): string         { return 'compensi_terzi'; }
    public function label(): string       { return 'Compensi a terzi'; }
    public function description(): string { return 'Compensi a collaboratori e prestatori occasionali (per CU/770).'; }
    public function fileName(): string    { return 'compensi_terzi.csv'; }

    public function headers(): array
    {
        return [
            'data_pagamento',
            'anno_competenza',
            'nome_percipiente',
            'codice_fiscale',
            'partita_iva',
            'indirizzo',
            'tipo_rapporto',
            'codice_causale',          // A, B, ... (categorie CU)
            'causale_prestazione',
            'compenso_lordo',
            'base_imponibile_ritenuta',
            'aliquota_ritenuta',
            'ritenuta',
            'compenso_netto',
            'contributo_inps_beneficiario',
            'contributo_inps_committente',
            'rimborsi_spese',
            'stato_ritenuta',           // versata, da_versare, ...
            'versamento_ritenuta_id',
            'member_id',
            'note',
        ];
    }

    public function rows(string $tenantId, CarbonInterface $from, CarbonInterface $to): \Generator
    {
        $cursor = DB::table('compensi_terzi')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_pagamento', [$from->toDateString(), $to->toDateString()])
            ->orderBy('data_pagamento')
            ->cursor();

        foreach ($cursor as $r) {
            yield [
                $r->data_pagamento,
                $r->anno_competenza,
                $r->nome_percipiente,
                $r->codice_fiscale,
                $r->partita_iva,
                $this->cleanText($r->indirizzo),
                $r->tipo_rapporto,
                $r->codice_causale,
                $this->cleanText($r->causale_prestazione),
                $this->money($r->compenso_lordo),
                $this->money($r->base_imponibile_ritenuta),
                $r->aliquota_ritenuta,
                $this->money($r->ritenuta),
                $this->money($r->compenso_netto),
                $this->money($r->contributo_inps_beneficiario),
                $this->money($r->contributo_inps_committente),
                $this->money($r->rimborsi_spese),
                $r->stato_ritenuta,
                $r->versamento_ritenuta_id,
                $r->member_id,
                $this->cleanText($r->note),
            ];
        }
    }

    public function estimatedCount(string $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) DB::table('compensi_terzi')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_pagamento', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    private function money(mixed $v): string { return number_format((float) ($v ?? 0), 2, '.', ''); }
    private function cleanText(?string $v): string { return $v === null ? '' : trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''); }
}
