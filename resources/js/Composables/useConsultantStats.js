import { computed } from 'vue';

/**
 * Composable per accedere ai dati aggregati del cruscotto consulente.
 *
 * @param {import('vue').Ref|Object} stats  — prop `stats` dalla pagina Show
 * @returns helpers tipizzati con fallback sicuri
 */
export function useConsultantStats(stats) {
    const s = computed(() => stats.value ?? stats ?? {});

    // ── Meta ────────────────────────────────────────────────────────────────
    const meta    = computed(() => s.value.meta    ?? {});
    const common  = computed(() => s.value.common  ?? {});
    const ets     = computed(() => s.value.ets     ?? null);
    const coop    = computed(() => s.value.cooperativa ?? null);

    // ── Fatturazione attiva ─────────────────────────────────────────────────
    const fattAttiva = computed(() => common.value.fatturazione_attiva ?? {});

    const totaleEmesso      = computed(() => Number(fattAttiva.value.totale_documento ?? 0));
    const countFatture      = computed(() => Number(fattAttiva.value.count_totale ?? 0));
    const daIncassare       = computed(() => Number(fattAttiva.value.da_incassare ?? 0));
    const incassato         = computed(() => Number(fattAttiva.value.incassato ?? 0));
    const perStato          = computed(() => fattAttiva.value.per_stato ?? []);

    // ── Fatturazione passiva ────────────────────────────────────────────────
    const fattPassiva       = computed(() => common.value.fatturazione_passiva ?? {});
    const totaleCosti       = computed(() => Number(fattPassiva.value.totale_documento ?? 0));
    const inScadenza30gg    = computed(() => Number(fattPassiva.value.count_in_scadenza_30gg ?? 0));

    // ── Cash flow ───────────────────────────────────────────────────────────
    const cashflow          = computed(() => common.value.cashflow ?? {});
    const totaleIncassi     = computed(() => Number(cashflow.value.totale_incassi ?? 0));
    const totaleSpese       = computed(() => Number(cashflow.value.totale_spese ?? 0));
    const saldoCashflow     = computed(() => totaleIncassi.value - totaleSpese.value);

    // ── Banca ───────────────────────────────────────────────────────────────
    const banca             = computed(() => common.value.banca ?? {});
    const percRiconciliato  = computed(() => Number(banca.value.perc_riconciliato ?? 0));

    // ── Trend mensile ───────────────────────────────────────────────────────
    const trendMensile      = computed(() => common.value.trend_mensile ?? []);

    // Dati per Chart.js (line chart: entrate vs uscite)
    const trendLabels       = computed(() => trendMensile.value.map(t => t.mese));
    const trendEntrate      = computed(() => trendMensile.value.map(t => Number(t.entrate ?? 0)));
    const trendUscite       = computed(() => trendMensile.value.map(t => Number(t.uscite ?? 0)));

    // ── ETS ────────────────────────────────────────────────────────────────
    const membriAttivi      = computed(() => Number(ets.value?.membri?.attivi ?? 0));
    const nuoviMembri       = computed(() => Number(ets.value?.membri?.nuovi_nel_periodo ?? 0));
    const totaleDonazioni   = computed(() => Number(ets.value?.donazioni?.totale ?? 0));

    // ── Cooperativa ────────────────────────────────────────────────────────
    const capitaleSociale   = computed(() => Number(coop.value?.capitale_sociale?.versato ?? 0));
    const prestitoSociale   = computed(() => Number(coop.value?.prestito_sociale?.saldo_attuale ?? 0));

    // ── Utilità formattazione ───────────────────────────────────────────────
    const fmt = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 0 });
    const formatCurrency = (v) => fmt.format(Number(v) || 0);
    const formatPercent  = (v) => `${Number(v || 0).toFixed(1)} %`;

    return {
        // meta
        meta,
        // sections
        fattAttiva, fattPassiva, cashflow, banca, trendMensile, ets, coop,
        // KPI calcolati
        totaleEmesso, countFatture, daIncassare, incassato, perStato,
        totaleCosti, inScadenza30gg,
        totaleIncassi, totaleSpese, saldoCashflow,
        percRiconciliato,
        // trend chart
        trendLabels, trendEntrate, trendUscite,
        // ets
        membriAttivi, nuoviMembri, totaleDonazioni,
        // coop
        capitaleSociale, prestitoSociale,
        // helpers
        formatCurrency, formatPercent,
    };
}
