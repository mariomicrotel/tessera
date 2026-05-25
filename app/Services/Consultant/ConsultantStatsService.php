<?php

namespace App\Services\Consultant;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregazione dati operativi di un tenant per il cruscotto del consulente.
 *
 * Design decisions (Opus F2):
 *  - Stateless singleton: nessuna dipendenza dal middleware ResolveTenant
 *  - Tutte le query usano DB::table() con where tenant_id esplicito
 *    per bypassare BelongsToTenant global scope e overhead ORM
 *  - Cache TTL: 15 min se il periodo include today, 24h se storico chiuso
 *  - Chiave: consultant_stats:{tenant_id}:{from}:{to}:v1
 *  - Trend mensile calcolato lato SQL (GROUP BY DATE_FORMAT) — mai in PHP
 */
class ConsultantStatsService
{
    private const CACHE_VERSION = 'v1';
    private const TTL_LIVE      = 900;    // 15 min — periodo include oggi
    private const TTL_HISTORICAL = 86400; // 24 h  — periodo storico chiuso

    /* ── Public API ──────────────────────────────────────────────────────── */

    /**
     * Aggregato completo per il cruscotto.
     * È il metodo che il controller chiama.
     */
    public function aggregateForPeriod(
        string         $tenantId,
        CarbonInterface $from,
        CarbonInterface $to,
        bool           $forceRefresh = false,
    ): array {
        $key = $this->cacheKey($tenantId, $from, $to);
        $ttl = $to->isFuture() || $to->isToday() ? self::TTL_LIVE : self::TTL_HISTORICAL;

        if ($forceRefresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, $ttl, function () use ($tenantId, $from, $to) {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first(['organization_type', 'cooperative_type']);
            $isCooperativa = ($tenant?->organization_type === 'cooperative');

            $data = [
                'meta'    => $this->buildMeta($tenantId, $from, $to, false),
                'common'  => $this->aggregateCommon($tenantId, $from, $to),
                'ets'     => $isCooperativa ? null : $this->aggregateEts($tenantId, $from, $to),
                'cooperativa' => $isCooperativa ? $this->aggregateCooperativa($tenantId, $from, $to) : null,
            ];

            $data['meta']['cached'] = false;
            return $data;
        });
    }

    /**
     * Range di date disponibili per il tenant (per hint UI del selettore periodo).
     */
    public function availablePeriods(string $tenantId): array
    {
        $key = "consultant_stats_periods:{$tenantId}";
        return Cache::remember($key, 3600, function () use ($tenantId) {
            $minDates = [];

            foreach (['fatture_attive' => 'data_fattura', 'fatture_passive' => 'data_fattura', 'incassi' => 'paid_at', 'spese' => 'date'] as $table => $col) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $min = DB::table($table)->where('tenant_id', $tenantId)->min($col);
                    if ($min) {
                        $minDates[] = $min;
                    }
                }
            }

            return [
                'min_date' => $minDates ? min($minDates) : now()->startOfYear()->toDateString(),
                'max_date' => now()->addDay()->toDateString(),
            ];
        });
    }

    /**
     * Invalida la cache per un tenant (tutti i periodi o uno specifico).
     */
    public function invalidate(string $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): void
    {
        if ($from && $to) {
            Cache::forget($this->cacheKey($tenantId, $from, $to));
        } else {
            // Invalida chiave periods e le chiavi live più comuni
            Cache::forget("consultant_stats_periods:{$tenantId}");

            // Invalida anno corrente e anno precedente (i più usati)
            $year = now()->year;
            foreach ([$year, $year - 1] as $y) {
                $f = Carbon::create($y, 1, 1);
                $t = Carbon::create($y, 12, 31);
                Cache::forget($this->cacheKey($tenantId, $f, $t));
            }
            // Invalida "ultimo anno mobile" e "mese corrente"
            Cache::forget($this->cacheKey($tenantId, now()->subYear(), now()));
            Cache::forget($this->cacheKey($tenantId, now()->startOfMonth(), now()->endOfMonth()));
        }
    }

    /* ── Aggregatori comuni (ETS + cooperative) ──────────────────────────── */

    public function aggregateCommon(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            'fatturazione_attiva'  => $this->fatturazioneAttiva($tenantId, $from, $to),
            'fatturazione_passiva' => $this->fatturazionePassiva($tenantId, $from, $to),
            'cashflow'             => $this->cashflow($tenantId, $from, $to),
            'banca'                => $this->banca($tenantId, $from, $to),
            'trend_mensile'        => $this->trendMensile($tenantId, $from, $to),
        ];
    }

    public function fatturazioneAttiva(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        if (! DB::getSchemaBuilder()->hasTable('fatture_attive')) {
            return $this->emptyFatturazioneAttiva();
        }

        $base = DB::table('fatture_attive')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_fattura', [$from->toDateString(), $to->toDateString()])
            ->where('stato', '!=', 'annullata');

        $totals = (clone $base)->selectRaw('
            COUNT(*) as count_totale,
            COALESCE(SUM(imponibile_totale), 0) as imponibile_totale,
            COALESCE(SUM(iva_totale), 0) as iva_totale,
            COALESCE(SUM(totale_documento), 0) as totale_documento
        ')->first();

        $perStato = (clone $base)->selectRaw('stato, COUNT(*) as cnt')
            ->groupBy('stato')
            ->pluck('cnt', 'stato')
            ->toArray();

        $daIncassare = (clone $base)
            ->where('stato_pagamento', 'da_incassare')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(totale_documento), 0) as valore')
            ->first();

        $incassato = (clone $base)
            ->where('stato_pagamento', 'incassata')
            ->selectRaw('COALESCE(SUM(totale_documento), 0) as valore')
            ->value('valore') ?? 0;

        return [
            'count_totale'      => (int) ($totals->count_totale ?? 0),
            'imponibile_totale' => (float) ($totals->imponibile_totale ?? 0),
            'iva_totale'        => (float) ($totals->iva_totale ?? 0),
            'totale_documento'  => (float) ($totals->totale_documento ?? 0),
            'per_stato'         => [
                'bozza'        => (int) ($perStato['bozza'] ?? 0),
                'emessa'       => (int) ($perStato['emessa'] ?? 0),
                'inviata_sdi'  => (int) ($perStato['inviata_sdi'] ?? 0),
                'accettata'    => (int) ($perStato['accettata'] ?? 0),
                'scartata'     => (int) ($perStato['scartata'] ?? 0),
            ],
            'count_da_incassare' => (int) ($daIncassare->cnt ?? 0),
            'valore_da_incassare' => (float) ($daIncassare->valore ?? 0),
            'valore_incassato'   => (float) $incassato,
        ];
    }

    public function fatturazionePassiva(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        if (! DB::getSchemaBuilder()->hasTable('fatture_passive')) {
            return $this->emptyFatturazionePassiva();
        }

        $base = DB::table('fatture_passive')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_fattura', [$from->toDateString(), $to->toDateString()]);

        $totals = (clone $base)->selectRaw('
            COUNT(*) as count_totale,
            COALESCE(SUM(imponibile_totale), 0) as imponibile_totale,
            COALESCE(SUM(iva_totale), 0) as iva_totale,
            COALESCE(SUM(totale_documento), 0) as totale_documento
        ')->first();

        $daPagare = (clone $base)
            ->where('stato_pagamento', 'da_pagare')
            ->selectRaw('COALESCE(SUM(totale_documento), 0) as valore')
            ->value('valore') ?? 0;

        $pagato = (clone $base)
            ->where('stato_pagamento', 'pagata')
            ->selectRaw('COALESCE(SUM(totale_documento), 0) as valore')
            ->value('valore') ?? 0;

        $inScadenza30 = DB::getSchemaBuilder()->hasColumn('fatture_passive', 'data_scadenza')
            ? DB::table('fatture_passive')
                ->where('tenant_id', $tenantId)
                ->where('stato_pagamento', 'da_pagare')
                ->whereBetween('data_scadenza', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->count()
            : 0;

        return [
            'count_totale'       => (int) ($totals->count_totale ?? 0),
            'imponibile_totale'  => (float) ($totals->imponibile_totale ?? 0),
            'iva_totale'         => (float) ($totals->iva_totale ?? 0),
            'totale_documento'   => (float) ($totals->totale_documento ?? 0),
            'valore_da_pagare'   => (float) $daPagare,
            'valore_pagato'      => (float) $pagato,
            'count_in_scadenza_30gg' => (int) $inScadenza30,
        ];
    }

    public function cashflow(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        // Entrate: incassi
        $entratePer = [];
        $entrateTotal = 0.0;

        if (DB::getSchemaBuilder()->hasTable('incassi')) {
            $rows = DB::table('incassi')
                ->where('tenant_id', $tenantId)
                ->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("type, COALESCE(SUM(amount), 0) as totale")
                ->groupBy('type')
                ->get();

            foreach ($rows as $r) {
                $entratePer[$r->type] = (float) $r->totale;
                $entrateTotal += (float) $r->totale;
            }
        }

        // Uscite: spese
        $uscitatotale = 0.0;
        if (DB::getSchemaBuilder()->hasTable('spese')) {
            $uscitatotale = (float) DB::table('spese')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->sum('amount');
        }

        // Rimborsi contabilizzati
        $rimborsiTotale = 0.0;
        if (DB::getSchemaBuilder()->hasTable('expense_refunds')) {
            $rimborsiTotale = (float) DB::table('expense_refunds')
                ->where('tenant_id', $tenantId)
                ->where('status', 'contabilizzata')
                ->whereBetween('refund_date', [$from->toDateString(), $to->toDateString()])
                ->sum('total');
        }

        return [
            'entrate_totali'   => $entrateTotal,
            'entrate_per_tipo' => [
                'quota'            => (float) ($entratePer['quota'] ?? 0),
                'donazione'        => (float) ($entratePer['donazione'] ?? 0),
                'incasso_generico' => (float) ($entratePer['incasso_generico'] ?? 0),
            ],
            'uscite_totali'         => $uscitatotale,
            'rimborsi_spese_totali' => $rimborsiTotale,
            'saldo_periodo'         => $entrateTotal - $uscitatotale - $rimborsiTotale,
        ];
    }

    public function banca(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        if (! DB::getSchemaBuilder()->hasTable('movimenti_bancari')) {
            return ['totale_dare' => 0, 'totale_avere' => 0, 'count_movimenti' => 0, 'count_non_riconciliati' => 0, 'percentuale_riconciliazione' => 0.0];
        }

        $base = DB::table('movimenti_bancari')
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_valuta', [$from->toDateString(), $to->toDateString()]);

        $totals = (clone $base)->selectRaw('
            COUNT(*) as cnt,
            COALESCE(SUM(CASE WHEN tipo = "dare" THEN importo ELSE 0 END), 0) as totale_dare,
            COALESCE(SUM(CASE WHEN tipo = "avere" THEN importo ELSE 0 END), 0) as totale_avere,
            COALESCE(SUM(CASE WHEN riconciliato = 0 THEN 1 ELSE 0 END), 0) as non_riconciliati
        ')->first();

        $cnt = (int) ($totals->cnt ?? 0);
        $nonRic = (int) ($totals->non_riconciliati ?? 0);
        $perc = $cnt > 0 ? round((($cnt - $nonRic) / $cnt) * 100, 1) : 0.0;

        return [
            'totale_dare'               => (float) ($totals->totale_dare ?? 0),
            'totale_avere'              => (float) ($totals->totale_avere ?? 0),
            'count_movimenti'           => $cnt,
            'count_non_riconciliati'    => $nonRic,
            'percentuale_riconciliazione' => $perc,
        ];
    }

    public function trendMensile(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        // Genera tutti i bucket mesi nel range
        $buckets = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $buckets[$cursor->format('Y-m')] = [
                'mese'             => $cursor->format('Y-m'),
                'ricavi'           => 0.0,
                'costi'            => 0.0,
                'incassi'          => 0.0,
                'spese'            => 0.0,
                'fatture_emesse'   => 0,
                'fatture_ricevute' => 0,
            ];
            $cursor->addMonth();
        }

        // Fatture attive (ricavi + count)
        if (DB::getSchemaBuilder()->hasTable('fatture_attive')) {
            $bucket = $this->dateBucket('data_fattura');
            $rows = DB::table('fatture_attive')
                ->where('tenant_id', $tenantId)
                ->where('stato', '!=', 'annullata')
                ->whereBetween('data_fattura', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("{$bucket} as bucket, COALESCE(SUM(imponibile_totale),0) as ricavi, COUNT(*) as cnt")
                ->groupBy('bucket')
                ->get();
            foreach ($rows as $r) {
                if (isset($buckets[$r->bucket])) {
                    $buckets[$r->bucket]['ricavi']         = (float) $r->ricavi;
                    $buckets[$r->bucket]['fatture_emesse'] = (int) $r->cnt;
                }
            }
        }

        // Fatture passive (costi + count)
        if (DB::getSchemaBuilder()->hasTable('fatture_passive')) {
            $bucket = $this->dateBucket('data_fattura');
            $rows = DB::table('fatture_passive')
                ->where('tenant_id', $tenantId)
                ->whereBetween('data_fattura', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("{$bucket} as bucket, COALESCE(SUM(imponibile_totale),0) as costi, COUNT(*) as cnt")
                ->groupBy('bucket')
                ->get();
            foreach ($rows as $r) {
                if (isset($buckets[$r->bucket])) {
                    $buckets[$r->bucket]['costi']            = (float) $r->costi;
                    $buckets[$r->bucket]['fatture_ricevute'] = (int) $r->cnt;
                }
            }
        }

        // Incassi
        if (DB::getSchemaBuilder()->hasTable('incassi')) {
            $bucket = $this->dateBucket('paid_at');
            $rows = DB::table('incassi')
                ->where('tenant_id', $tenantId)
                ->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("{$bucket} as bucket, COALESCE(SUM(amount),0) as incassi")
                ->groupBy('bucket')
                ->get();
            foreach ($rows as $r) {
                if (isset($buckets[$r->bucket])) {
                    $buckets[$r->bucket]['incassi'] = (float) $r->incassi;
                }
            }
        }

        // Spese
        if (DB::getSchemaBuilder()->hasTable('spese')) {
            $bucket = $this->dateBucket('date');
            $rows = DB::table('spese')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("{$bucket} as bucket, COALESCE(SUM(amount),0) as spese")
                ->groupBy('bucket')
                ->get();
            foreach ($rows as $r) {
                if (isset($buckets[$r->bucket])) {
                    $buckets[$r->bucket]['spese'] = (float) $r->spese;
                }
            }
        }

        return array_values($buckets);
    }

    /* ── Aggregatori ETS-only ────────────────────────────────────────────── */

    public function aggregateEts(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        $membriAttivi = DB::getSchemaBuilder()->hasTable('members')
            ? DB::table('members')->where('tenant_id', $tenantId)->where('stato', 'attivo')->count()
            : 0;

        $nuoviMembri = DB::getSchemaBuilder()->hasTable('members')
            ? DB::table('members')
                ->where('tenant_id', $tenantId)
                ->whereBetween('admission_date', [$from->toDateString(), $to->toDateString()])
                ->count()
            : 0;

        $donazioni = 0.0;
        $countDonazioni = 0;
        if (DB::getSchemaBuilder()->hasTable('incassi')) {
            $row = DB::table('incassi')
                ->where('tenant_id', $tenantId)
                ->where('type', 'donazione')
                ->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount), 0) as totale')
                ->first();
            $donazioni     = (float) ($row->totale ?? 0);
            $countDonazioni = (int) ($row->cnt ?? 0);
        }

        // Spese da rendicontare (senza codice rendiconto CEE)
        $speseNonRendicontate = 0;
        $breakdownRendiconto  = [];
        if (DB::getSchemaBuilder()->hasTable('spese')) {
            $speseNonRendicontate = DB::table('spese')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->whereNull('rendiconto_code')
                ->count();

            $breakdownRendiconto = DB::table('spese')
                ->where('tenant_id', $tenantId)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->whereNotNull('rendiconto_code')
                ->selectRaw('rendiconto_code as codice, COUNT(*) as cnt, COALESCE(SUM(amount),0) as totale')
                ->groupBy('rendiconto_code')
                ->get()
                ->map(fn ($r) => [
                    'codice' => $r->codice,
                    'totale' => (float) $r->totale,
                    'count'  => (int) $r->cnt,
                ])
                ->values()
                ->toArray();
        }

        return [
            'membri_attivi'      => (int) $membriAttivi,
            'nuovi_membri_periodo' => (int) $nuoviMembri,
            'donazioni_periodo'  => $donazioni,
            'count_donazioni'    => $countDonazioni,
            'rendiconto'         => [
                'spese_non_rendicontate_count' => $speseNonRendicontate,
                'breakdown_per_codice'         => $breakdownRendiconto,
            ],
        ];
    }

    /* ── Aggregatori cooperative-only ───────────────────────────────────── */

    public function aggregateCooperativa(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        $capitaleSociale = ['sottoscritto_totale' => 0.0, 'versato_totale' => 0.0, 'da_versare' => 0.0, 'count_soci_morosi' => 0, 'nuove_sottoscrizioni_periodo' => 0];

        if (DB::getSchemaBuilder()->hasTable('cooperative_shares')) {
            $totals = DB::table('cooperative_shares')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['sottoscritta', 'parzialmente_versata', 'versata'])
                ->selectRaw('COALESCE(SUM(totale_sottoscritto),0) as sottoscritto, COALESCE(SUM(totale_versato),0) as versato')
                ->first();

            $sott = (float) ($totals->sottoscritto ?? 0);
            $vers = (float) ($totals->versato ?? 0);

            $morosi = DB::table('cooperative_shares')
                ->where('tenant_id', $tenantId)
                ->where('status', 'parzialmente_versata')
                ->distinct('member_id')
                ->count('member_id');

            $nuove = DB::table('cooperative_shares')
                ->where('tenant_id', $tenantId)
                ->whereBetween('data_sottoscrizione', [$from->toDateString(), $to->toDateString()])
                ->count();

            $capitaleSociale = [
                'sottoscritto_totale'         => $sott,
                'versato_totale'              => $vers,
                'da_versare'                  => max(0.0, $sott - $vers),
                'count_soci_morosi'           => (int) $morosi,
                'nuove_sottoscrizioni_periodo' => (int) $nuove,
            ];
        }

        $prestitoSociale = ['libretti_attivi' => 0, 'saldo_totale' => 0.0, 'depositi_periodo' => 0.0, 'prelievi_periodo' => 0.0, 'interessi_periodo' => 0.0, 'flusso_netto_periodo' => 0.0];

        if (DB::getSchemaBuilder()->hasTable('prestito_sociale_libretti')) {
            $libretti = DB::table('prestito_sociale_libretti')
                ->where('tenant_id', $tenantId)
                ->where('status', 'attivo')
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(saldo_attuale),0) as saldo')
                ->first();

            $prestitoSociale['libretti_attivi'] = (int) ($libretti->cnt ?? 0);
            $prestitoSociale['saldo_totale']    = (float) ($libretti->saldo ?? 0);
        }

        if (DB::getSchemaBuilder()->hasTable('prestito_sociale_movimenti')) {
            $movimenti = DB::table('prestito_sociale_movimenti')
                ->where('tenant_id', $tenantId)
                ->whereBetween('data_valuta', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN tipo='deposito' THEN importo ELSE 0 END),0) as depositi,
                    COALESCE(SUM(CASE WHEN tipo='prelievo' THEN importo ELSE 0 END),0) as prelievi,
                    COALESCE(SUM(CASE WHEN tipo='interessi' THEN importo ELSE 0 END),0) as interessi
                ")
                ->first();

            $dep = (float) ($movimenti->depositi ?? 0);
            $pre = (float) ($movimenti->prelievi ?? 0);
            $int = (float) ($movimenti->interessi ?? 0);

            $prestitoSociale['depositi_periodo']  = $dep;
            $prestitoSociale['prelievi_periodo']  = $pre;
            $prestitoSociale['interessi_periodo'] = $int;
            $prestitoSociale['flusso_netto_periodo'] = $dep - $pre;
        }

        return [
            'capitale_sociale' => $capitaleSociale,
            'prestito_sociale' => $prestitoSociale,
        ];
    }

    /* ── Helpers privati ─────────────────────────────────────────────────── */

    private function cacheKey(string $tenantId, CarbonInterface $from, CarbonInterface $to): string
    {
        return sprintf(
            'consultant_stats:%s:%s:%s:%s',
            $tenantId,
            $from->toDateString(),
            $to->toDateString(),
            self::CACHE_VERSION,
        );
    }

    private function buildMeta(string $tenantId, CarbonInterface $from, CarbonInterface $to, bool $cached): array
    {
        return [
            'tenant_id'    => $tenantId,
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'period_from'  => $from->toDateString(),
            'period_to'    => $to->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'cached'       => $cached,
        ];
    }

    /**
     * Restituisce l'espressione SQL per estrarre il bucket YYYY-MM da una colonna data.
     * MySQL/MariaDB usano DATE_FORMAT, SQLite usa strftime.
     */
    private function dateBucket(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            default  => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private function emptyFatturazioneAttiva(): array
    {
        return [
            'count_totale' => 0, 'imponibile_totale' => 0.0, 'iva_totale' => 0.0, 'totale_documento' => 0.0,
            'per_stato' => ['bozza' => 0, 'emessa' => 0, 'inviata_sdi' => 0, 'accettata' => 0, 'scartata' => 0],
            'count_da_incassare' => 0, 'valore_da_incassare' => 0.0, 'valore_incassato' => 0.0,
        ];
    }

    private function emptyFatturazionePassiva(): array
    {
        return [
            'count_totale' => 0, 'imponibile_totale' => 0.0, 'iva_totale' => 0.0, 'totale_documento' => 0.0,
            'valore_da_pagare' => 0.0, 'valore_pagato' => 0.0, 'count_in_scadenza_30gg' => 0,
        ];
    }
}
