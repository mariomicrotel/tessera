<script setup>
import {
    ChartBarIcon,
    ArrowDownTrayIcon,
    TableCellsIcon,
    ScaleIcon,
    CurrencyEuroIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    sp:         Object,
    ce:         Object,
    rendiconto: Object,
    anno:       Number,
    annoPrec:   Number,
    tab:        String,
    anniRange:  Array,
});

const page   = usePage();
const tenant = page.props.tenant;

const activeTab = ref(props.tab ?? 'ce');
const annoScelto = ref(props.anno);

watch([annoScelto, activeTab], ([a, t]) => {
    router.get(
        route('bilancio.cee.index', tenant),
        { anno: a, tab: t },
        { preserveState: false }
    );
});

const fmt = (n) => {
    const v = parseFloat(n ?? 0);
    return v.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const varPct = (curr, prev) => {
    if (!prev || prev == 0) return null;
    return ((curr - prev) / Math.abs(prev) * 100).toFixed(1);
};

const varClass = (n) => n > 0 ? 'text-green-600' : n < 0 ? 'text-red-600' : 'text-gray-400';

const risClass = (n) => parseFloat(n) >= 0
    ? 'bg-green-50 border border-green-200 text-green-800'
    : 'bg-red-50 border border-red-200 text-red-700';

// CE sezioni labels short
const sezLabel = { A: 'Valore prod.', B: 'Costi prod.', C: 'Finanziari', D: 'Rettifiche', E: 'Imposte' };
</script>

<template>
    <AppLayout title="Bilancio CEE">
        <Head title="Bilancio CEE" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <ChartBarIcon class="w-6 h-6 text-blue-600" />
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Bilancio CEE</h2>
                    <span class="text-xs text-gray-400">IV Direttiva — Esercizio {{ annoScelto }}</span>
                </div>
                <!-- Export buttons -->
                <div class="flex items-center gap-2">
                    <a :href="route('bilancio.cee.csv', [tenant, { anno: annoScelto }])"
                       class="inline-flex items-center gap-1 text-sm bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg px-3 py-1.5">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        CSV completo
                    </a>
                    <template v-if="activeTab === 'sp'">
                        <a :href="route('bilancio.cee.pdf-sp', [tenant, { anno: annoScelto }])"
                           class="inline-flex items-center gap-1 text-sm bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg px-3 py-1.5">
                            <ArrowDownTrayIcon class="w-4 h-4" />
                            PDF — SP
                        </a>
                    </template>
                    <template v-else-if="activeTab === 'ce'">
                        <a :href="route('bilancio.cee.pdf-ce', [tenant, { anno: annoScelto }])"
                           class="inline-flex items-center gap-1 text-sm bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg px-3 py-1.5">
                            <ArrowDownTrayIcon class="w-4 h-4" />
                            PDF — CE
                        </a>
                    </template>
                    <template v-else-if="activeTab === 'rendiconto'">
                        <a :href="route('bilancio.cee.pdf-rendiconto', [tenant, { anno: annoScelto }])"
                           class="inline-flex items-center gap-1 text-sm bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg px-3 py-1.5">
                            <ArrowDownTrayIcon class="w-4 h-4" />
                            PDF — Rendiconto
                        </a>
                    </template>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <!-- Anno selector + KPI bar -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex gap-2">
                    <button v-for="a in anniRange" :key="a"
                            @click="annoScelto = a"
                            :class="['px-4 py-1.5 rounded-lg border text-sm font-semibold transition',
                                     annoScelto === a
                                       ? 'bg-blue-600 border-blue-600 text-white'
                                       : 'bg-white border-gray-300 text-gray-600 hover:border-blue-300 dark:bg-gray-800 dark:border-gray-600']">
                        {{ a }}
                    </button>
                </div>
                <!-- Quick KPIs -->
                <div class="flex gap-4 text-sm">
                    <div class="text-center">
                        <div class="text-xs text-gray-400">Attivo totale</div>
                        <div class="font-bold text-gray-800 dark:text-gray-100">€ {{ fmt(sp.totale_attivo) }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-gray-400">Passivo + PN</div>
                        <div class="font-bold text-gray-800 dark:text-gray-100">€ {{ fmt(sp.totale_passivo) }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-gray-400">Risultato esercizio</div>
                        <div :class="['font-bold', parseFloat(ce.risultato_esercizio) >= 0 ? 'text-green-700' : 'text-red-600']">
                            € {{ fmt(ce.risultato_esercizio) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex gap-0">
                    <button v-for="(lbl, key) in { sp: 'Stato Patrimoniale', ce: 'Conto Economico', rendiconto: 'Rendiconto Gestionale' }"
                            :key="key"
                            @click="activeTab = key"
                            :class="['px-5 py-3 text-sm font-semibold border-b-2 transition',
                                     activeTab === key
                                       ? 'border-blue-600 text-blue-700 dark:text-blue-400'
                                       : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400']">
                        {{ lbl }}
                    </button>
                </nav>
            </div>

            <!-- ─── TAB: Stato Patrimoniale ─────────────────────────────── -->
            <div v-if="activeTab === 'sp'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- ATTIVO -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                    <div class="bg-blue-900 text-white px-5 py-3 font-bold text-sm flex justify-between">
                        <span>ATTIVO</span>
                        <span>€ {{ fmt(sp.totale_attivo) }}</span>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 uppercase">
                                <th class="px-4 py-2 text-left">Voce</th>
                                <th class="px-4 py-2 text-right">{{ anno }}</th>
                                <th class="px-4 py-2 text-right">{{ annoPrec }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="gruppo in sp.attivo" :key="gruppo.mastro">
                                <tr class="bg-blue-50 dark:bg-blue-900/20">
                                    <td class="px-4 py-2 font-semibold text-blue-800 dark:text-blue-300">
                                        {{ gruppo.mastro }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-bold">€ {{ fmt(gruppo.saldo) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-400">{{ fmt(gruppo.saldo_prec) }}</td>
                                </tr>
                                <tr v-for="v in gruppo.voci" :key="v.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                    <td class="px-4 py-1.5 pl-8 text-gray-600 dark:text-gray-300 text-xs">
                                        {{ v.codice }} — {{ v.descrizione }}
                                    </td>
                                    <td class="px-4 py-1.5 text-right font-mono text-xs">{{ fmt(v.saldo) }}</td>
                                    <td class="px-4 py-1.5 text-right font-mono text-xs text-gray-400">{{ fmt(v.saldo_prec) }}</td>
                                </tr>
                            </template>
                            <tr v-if="sp.attivo.length === 0">
                                <td colspan="3" class="px-4 py-6 text-center text-gray-400 italic text-xs">
                                    Nessun conto attivo movimentato nell'anno.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-blue-50 dark:bg-blue-900/30 font-bold border-t-2 border-blue-600">
                                <td class="px-4 py-2 text-blue-800 dark:text-blue-200">TOTALE ATTIVO</td>
                                <td class="px-4 py-2 text-right text-blue-800 dark:text-blue-200">€ {{ fmt(sp.totale_attivo) }}</td>
                                <td class="px-4 py-2 text-right text-gray-400">—</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- PASSIVO + PN -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                    <div class="bg-blue-900 text-white px-5 py-3 font-bold text-sm flex justify-between">
                        <span>PASSIVO + PATRIMONIO NETTO</span>
                        <span>€ {{ fmt(sp.totale_passivo) }}</span>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 uppercase">
                                <th class="px-4 py-2 text-left">Voce</th>
                                <th class="px-4 py-2 text-right">{{ anno }}</th>
                                <th class="px-4 py-2 text-right">{{ annoPrec }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="gruppo in sp.passivo" :key="gruppo.mastro">
                                <tr class="bg-blue-50 dark:bg-blue-900/20">
                                    <td class="px-4 py-2 font-semibold text-blue-800 dark:text-blue-300">
                                        {{ gruppo.mastro }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-bold">€ {{ fmt(gruppo.saldo) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-400">{{ fmt(gruppo.saldo_prec) }}</td>
                                </tr>
                                <tr v-for="v in gruppo.voci" :key="v.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                    <td class="px-4 py-1.5 pl-8 text-gray-600 dark:text-gray-300 text-xs">
                                        {{ v.codice }} — {{ v.descrizione }}
                                    </td>
                                    <td class="px-4 py-1.5 text-right font-mono text-xs">{{ fmt(v.saldo) }}</td>
                                    <td class="px-4 py-1.5 text-right font-mono text-xs text-gray-400">{{ fmt(v.saldo_prec) }}</td>
                                </tr>
                            </template>
                            <tr v-if="sp.passivo.length === 0">
                                <td colspan="3" class="px-4 py-6 text-center text-gray-400 italic text-xs">
                                    Nessun conto passivo/PN movimentato nell'anno.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-blue-50 dark:bg-blue-900/30 font-bold border-t-2 border-blue-600">
                                <td class="px-4 py-2 text-blue-800 dark:text-blue-200">TOTALE PASSIVO + PN</td>
                                <td class="px-4 py-2 text-right text-blue-800 dark:text-blue-200">€ {{ fmt(sp.totale_passivo) }}</td>
                                <td class="px-4 py-2 text-right text-gray-400">—</td>
                            </tr>
                            <tr v-if="sp.differenza !== 0"
                                class="bg-red-50 text-red-700 font-semibold text-xs">
                                <td class="px-4 py-2">⚠ Differenza (controllare partita doppia)</td>
                                <td class="px-4 py-2 text-right">€ {{ fmt(sp.differenza) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- ─── TAB: Conto Economico ───────────────────────────────── -->
            <div v-else-if="activeTab === 'ce'" class="space-y-4">

                <!-- Sezioni A–E -->
                <div v-for="(sez, key) in ce.sezioni" :key="key"
                     class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                    <div class="bg-blue-900 text-white px-5 py-3 font-bold text-sm flex justify-between">
                        <span>{{ sez.label }}</span>
                        <span>€ {{ fmt(sez.totale) }}</span>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-500 uppercase">
                                <th class="px-4 py-2 text-left">Voce</th>
                                <th class="px-4 py-2 text-right">{{ anno }}</th>
                                <th class="px-4 py-2 text-right">{{ annoPrec }}</th>
                                <th class="px-4 py-2 text-right">Var. %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template v-for="gruppo in sez.voci" :key="gruppo.mastro">
                                <tr class="bg-blue-50 dark:bg-blue-900/20">
                                    <td class="px-4 py-2 font-semibold text-blue-800 dark:text-blue-300">{{ gruppo.mastro }}</td>
                                    <td class="px-4 py-2 text-right font-bold">€ {{ fmt(gruppo.saldo) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-400">{{ fmt(gruppo.saldo_prec) }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <span v-if="varPct(gruppo.saldo, gruppo.saldo_prec)"
                                              :class="varClass(varPct(gruppo.saldo, gruppo.saldo_prec))">
                                            {{ varPct(gruppo.saldo, gruppo.saldo_prec) }}%
                                        </span>
                                        <span v-else class="text-gray-300">—</span>
                                    </td>
                                </tr>
                                <tr v-for="v in gruppo.voci" :key="v.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                    <td class="px-4 py-1.5 pl-8 text-gray-600 dark:text-gray-300 text-xs">
                                        {{ v.codice }} — {{ v.descrizione }}
                                    </td>
                                    <td class="px-4 py-1.5 text-right font-mono text-xs">{{ fmt(v.saldo) }}</td>
                                    <td class="px-4 py-1.5 text-right font-mono text-xs text-gray-400">{{ fmt(v.saldo_prec) }}</td>
                                    <td class="px-4 py-1.5 text-right text-xs">
                                        <span v-if="varPct(v.saldo, v.saldo_prec)"
                                              :class="varClass(varPct(v.saldo, v.saldo_prec))">
                                            {{ varPct(v.saldo, v.saldo_prec) }}%
                                        </span>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!sez.voci || sez.voci.length === 0">
                                <td colspan="4" class="px-4 py-4 text-center text-gray-400 italic text-xs">
                                    Nessun conto movimentato per questa sezione.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-blue-50 font-bold border-t-2 border-blue-200">
                                <td class="px-4 py-2 text-blue-800">Totale {{ sez.label }}</td>
                                <td class="px-4 py-2 text-right text-blue-800">€ {{ fmt(sez.totale) }}</td>
                                <td class="px-4 py-2 text-right text-gray-400">{{ fmt(sez.totale_prec) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                    <!-- Risultato operativo dopo B) -->
                    <div v-if="key === 'B'"
                         class="px-5 py-2 bg-indigo-50 border-t border-indigo-200 text-sm font-semibold text-indigo-800 flex justify-between">
                        <span>Risultato operativo (A − B)</span>
                        <span :class="ce.risultato_operativo >= 0 ? 'text-green-700' : 'text-red-600'">
                            € {{ fmt(ce.risultato_operativo) }}
                        </span>
                    </div>
                </div>

                <!-- Risultato finale -->
                <div :class="['rounded-xl p-5 flex flex-wrap justify-between items-center gap-4', risClass(ce.risultato_esercizio)]">
                    <div>
                        <div class="text-lg font-bold">
                            {{ parseFloat(ce.risultato_esercizio) >= 0 ? 'AVANZO DI GESTIONE' : 'DISAVANZO DI GESTIONE' }}
                        </div>
                        <div class="text-sm opacity-75">Anno {{ anno }} (ante imposte: € {{ fmt(ce.risultato_ante_imposte) }})</div>
                    </div>
                    <div class="text-3xl font-bold">€ {{ fmt(ce.risultato_esercizio) }}</div>
                </div>
            </div>

            <!-- ─── TAB: Rendiconto Gestionale ────────────────────────── -->
            <div v-else-if="activeTab === 'rendiconto'" class="space-y-6">

                <div v-for="(area, areaKey) in rendiconto.aree" :key="areaKey"
                     v-if="area.tot_entrate > 0 || area.tot_uscite > 0"
                     class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">

                    <div class="bg-blue-900 text-white px-5 py-3 font-bold text-sm flex justify-between items-center">
                        <span>{{ area.label }}</span>
                        <span :class="area.risultato >= 0 ? 'text-green-300' : 'text-red-300'">
                            {{ area.risultato >= 0 ? '+' : '' }}€ {{ fmt(area.risultato) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700">
                        <!-- Entrate -->
                        <div>
                            <div class="px-4 py-2 bg-green-50 dark:bg-green-900/20 text-xs font-bold text-green-700 uppercase">
                                Entrate — € {{ fmt(area.tot_entrate) }}
                            </div>
                            <table class="w-full text-xs">
                                <tbody>
                                    <tr v-for="v in area.entrate" :key="v.codice"
                                        class="border-b border-gray-50 dark:border-gray-700 hover:bg-gray-50">
                                        <td class="px-4 py-1.5 text-gray-600 dark:text-gray-300">{{ v.codice }} {{ v.descrizione }}</td>
                                        <td class="px-4 py-1.5 text-right font-mono text-green-700">{{ fmt(v.saldo) }}</td>
                                    </tr>
                                    <tr v-if="!area.entrate || area.entrate.length === 0">
                                        <td colspan="2" class="px-4 py-3 text-center text-gray-400 italic">Nessuna entrata</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Uscite -->
                        <div>
                            <div class="px-4 py-2 bg-red-50 dark:bg-red-900/20 text-xs font-bold text-red-700 uppercase">
                                Uscite — € {{ fmt(area.tot_uscite) }}
                            </div>
                            <table class="w-full text-xs">
                                <tbody>
                                    <tr v-for="v in area.uscite" :key="v.codice"
                                        class="border-b border-gray-50 dark:border-gray-700 hover:bg-gray-50">
                                        <td class="px-4 py-1.5 text-gray-600 dark:text-gray-300">{{ v.codice }} {{ v.descrizione }}</td>
                                        <td class="px-4 py-1.5 text-right font-mono text-red-600">{{ fmt(v.saldo) }}</td>
                                    </tr>
                                    <tr v-if="!area.uscite || area.uscite.length === 0">
                                        <td colspan="2" class="px-4 py-3 text-center text-gray-400 italic">Nessuna uscita</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Risultato area -->
                    <div :class="['px-5 py-2.5 text-sm font-semibold flex justify-between border-t',
                                  area.risultato >= 0 ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-700 border-red-200']">
                        <span>Risultato area</span>
                        <span>{{ area.risultato >= 0 ? '+' : '' }}€ {{ fmt(area.risultato) }}
                            <span class="text-xs font-normal ml-1">(anno prec.: {{ fmt(area.risultato_prec) }})</span>
                        </span>
                    </div>
                </div>

                <!-- Box "nessun dato" -->
                <div v-if="Object.values(rendiconto.aree).every(a => a.tot_entrate === 0 && a.tot_uscite === 0)"
                     class="bg-white dark:bg-gray-800 rounded-xl shadow p-10 text-center text-gray-400">
                    Nessun movimento contabile confermato per l'anno {{ anno }}.
                </div>

                <!-- Riepilogo generale -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5">
                    <h3 class="font-bold text-gray-700 dark:text-gray-200 mb-4">Riepilogo generale</h3>
                    <div class="flex flex-wrap gap-6">
                        <div>
                            <div class="text-xs text-gray-400 uppercase">Totale entrate</div>
                            <div class="text-2xl font-bold text-green-700">€ {{ fmt(rendiconto.tot_entrate) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400 uppercase">Totale uscite</div>
                            <div class="text-2xl font-bold text-red-600">€ {{ fmt(rendiconto.tot_uscite) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400 uppercase">Risultato netto</div>
                            <div :class="['text-2xl font-bold', rendiconto.risultato_netto >= 0 ? 'text-green-700' : 'text-red-600']">
                                {{ rendiconto.risultato_netto >= 0 ? '+' : '' }}€ {{ fmt(rendiconto.risultato_netto) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
