<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowDownTrayIcon, BanknotesIcon, UserGroupIcon, ScaleIcon, ChartBarIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    anno:                          { type: Number, required: true },
    anniDisponibili:               { type: Array,  default: () => [] },
    capitale_sottoscritto_totale:  { type: Number, default: 0 },
    capitale_versato_totale:       { type: Number, default: 0 },
    capitale_da_versare:           { type: Number, default: 0 },
    soci_per_status:               { type: Object, default: () => ({}) },
    totale_soci:                   { type: Number, default: 0 },
    valore_medio_per_socio:        { type: Number, default: 0 },
    storico_capitale:              { type: Array,  default: () => [] },
    totale_prestito_sociale:       { type: Number, default: 0 },
    num_libretti_attivi:           { type: Number, default: 0 },
    interessi_anno:                { type: Number, default: 0 },
    riserve_accantonate:           { type: Object, default: () => ({}) },
    ristorni_anno:                 { type: Array,  default: () => [] },
    filters:                       { type: Object, default: () => ({}) },
});

const form = reactive({ anno: String(props.anno) });

const apply = () =>
    router.get(route('reports.situazione-capitale'), { anno: form.anno }, { preserveState: false, replace: true });

const fmt = (v) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v) || 0);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const pct = (part, tot) => tot > 0 ? Math.round((part / tot) * 100) : 0;

const percVersato = computed(() => pct(props.capitale_versato_totale, props.capitale_sottoscritto_totale));

const statusLabel = {
    sottoscritta:         'Sottoscritta',
    parzialmente_versata: 'Parz. versata',
    versata:              'Versata',
    riscattata:           'Riscattata',
};
const statusColor = {
    sottoscritta:         'bg-indigo-400',
    parzialmente_versata: 'bg-amber-400',
    versata:              'bg-green-500',
    riscattata:           'bg-gray-400',
};

const storicoMax = computed(() =>
    Math.max(1, ...props.storico_capitale.map(r => r.versato))
);

const exportUrl = computed(() =>
    route('reports.situazione-capitale.export') + '?anno=' + form.anno
);

const badgeFor = (s) => {
    const cfg = {
        deliberato:   { label: 'Deliberato',   cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
        pagato:       { label: 'Pagato',       cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
        in_pagamento: { label: 'In pagamento', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    };
    return cfg[s] ?? { label: s, cls: 'bg-gray-100 text-gray-600' };
};
</script>

<template>
    <Head :title="`Situazione Capitale ${anno}`" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Situazione Patrimoniale Cooperativa
                </h2>
                <div class="flex items-center gap-3">
                    <select v-model="form.anno" @change="apply"
                        class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                        <option v-for="a in anniDisponibili" :key="a" :value="String(a)">{{ a }}</option>
                    </select>
                    <a :href="exportUrl">
                        <PrimaryButton type="button">
                            <ArrowDownTrayIcon class="size-4 me-2" /> Export CSV
                        </PrimaryButton>
                    </a>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- ── CAPITALE SOCIALE ──────────────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-4 border-b dark:border-gray-700 bg-purple-50 dark:bg-purple-900/20">
                        <BanknotesIcon class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                        <h3 class="font-semibold text-purple-800 dark:text-purple-200">Capitale Sociale</h3>
                    </div>
                    <div class="p-5 space-y-5">

                        <!-- Progress bar versato/sottoscritto -->
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 dark:text-gray-400">Versato: <strong>{{ fmt(capitale_versato_totale) }}</strong></span>
                                <span class="text-gray-500 dark:text-gray-400">Sottoscritto: {{ fmt(capitale_sottoscritto_totale) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                                <div class="bg-purple-500 h-4 rounded-full transition-all duration-500"
                                     :style="{ width: percVersato + '%' }"></div>
                            </div>
                            <div class="flex justify-between text-xs mt-1">
                                <span class="text-purple-600 dark:text-purple-400 font-medium">{{ percVersato }}% versato</span>
                                <span v-if="capitale_da_versare > 0" class="text-amber-600 dark:text-amber-400">
                                    Da versare: {{ fmt(capitale_da_versare) }}
                                </span>
                                <span v-else class="text-green-600 dark:text-green-400">Completamente versato ✓</span>
                            </div>
                        </div>

                        <!-- KPI -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                                <div class="text-xs text-gray-500 mb-1">Soci totali</div>
                                <div class="text-2xl font-bold text-purple-800 dark:text-purple-200">{{ totale_soci }}</div>
                            </div>
                            <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                                <div class="text-xs text-gray-500 mb-1">Valore medio / socio</div>
                                <div class="text-lg font-bold text-purple-800 dark:text-purple-200">{{ fmt(valore_medio_per_socio) }}</div>
                            </div>
                            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <div class="text-xs text-gray-500 mb-1">Versato</div>
                                <div class="text-lg font-bold text-green-700 dark:text-green-300">{{ fmt(capitale_versato_totale) }}</div>
                            </div>
                            <div class="text-center p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                                <div class="text-xs text-gray-500 mb-1">Da versare</div>
                                <div class="text-lg font-bold"
                                     :class="capitale_da_versare > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-400'">
                                    {{ fmt(capitale_da_versare) }}
                                </div>
                            </div>
                        </div>

                        <!-- Quote per status -->
                        <div v-if="Object.keys(soci_per_status).length > 0">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Distribuzione quote per stato</div>
                            <div class="flex flex-wrap gap-3">
                                <div v-for="(count, status) in soci_per_status" :key="status"
                                     class="flex items-center gap-2 text-sm">
                                    <span class="inline-block w-3 h-3 rounded-full" :class="statusColor[status] || 'bg-gray-400'"></span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ statusLabel[status] || status }}:</span>
                                    <strong class="text-gray-900 dark:text-gray-100">{{ count }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Storico per anno -->
                        <div v-if="storico_capitale.length > 0">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-3">Storico sottoscrizioni per anno</div>
                            <div class="space-y-2">
                                <div v-for="r in storico_capitale" :key="r.anno" class="flex items-center gap-3 text-sm">
                                    <span class="w-12 text-gray-600 dark:text-gray-400 font-mono">{{ r.anno }}</span>
                                    <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded h-5 overflow-hidden">
                                        <div class="bg-purple-400 dark:bg-purple-600 h-5 rounded transition-all"
                                             :style="{ width: Math.round((r.versato / storicoMax) * 100) + '%' }"></div>
                                    </div>
                                    <span class="w-28 text-right text-gray-700 dark:text-gray-300">{{ fmt(r.versato) }}</span>
                                    <span class="w-10 text-right text-xs text-gray-500">{{ r.numero_quote }} soci</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ── PRESTITO SOCIALE ───────────────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-4 border-b dark:border-gray-700 bg-teal-50 dark:bg-teal-900/20">
                        <BanknotesIcon class="w-5 h-5 text-teal-600 dark:text-teal-400" />
                        <h3 class="font-semibold text-teal-800 dark:text-teal-200">Prestito Sociale</h3>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="text-center p-3 bg-teal-50 dark:bg-teal-900/20 rounded-lg">
                            <div class="text-xs text-gray-500 mb-1">Saldo totale libretti attivi</div>
                            <div class="text-2xl font-bold text-teal-800 dark:text-teal-200">{{ fmt(totale_prestito_sociale) }}</div>
                        </div>
                        <div class="text-center p-3 bg-teal-50 dark:bg-teal-900/20 rounded-lg">
                            <div class="text-xs text-gray-500 mb-1">Libretti attivi</div>
                            <div class="text-2xl font-bold text-teal-800 dark:text-teal-200">{{ num_libretti_attivi }}</div>
                        </div>
                        <div class="text-center p-3 bg-teal-50 dark:bg-teal-900/20 rounded-lg">
                            <div class="text-xs text-gray-500 mb-1">Interessi accreditati {{ anno }}</div>
                            <div class="text-2xl font-bold text-teal-800 dark:text-teal-200">{{ fmt(interessi_anno) }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">soggetti a ritenuta 26%</div>
                        </div>
                    </div>
                </section>

                <!-- ── RISERVE ────────────────────────────────────────────────── -->
                <section class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-4 border-b dark:border-gray-700 bg-indigo-50 dark:bg-indigo-900/20">
                        <ScaleIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        <h3 class="font-semibold text-indigo-800 dark:text-indigo-200">Riserve</h3>
                        <span class="text-xs text-indigo-500 dark:text-indigo-400">(accantonamenti cumulativi da prima nota)</span>
                    </div>
                    <div class="p-5">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
                            <div class="p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                                <div class="text-xs text-gray-500 mb-1">Riserva Legale (B08) — totale</div>
                                <div class="text-lg font-bold text-indigo-800 dark:text-indigo-200">{{ fmt(riserve_accantonate.legale) }}</div>
                                <div class="text-xs text-gray-400 mt-0.5">min 30% utili (L.59/1992)</div>
                            </div>
                            <div class="p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                                <div class="text-xs text-gray-500 mb-1">Riserva Indivisibile (B09) — totale</div>
                                <div class="text-lg font-bold text-indigo-800 dark:text-indigo-200">{{ fmt(riserve_accantonate.indivisibile) }}</div>
                                <div class="text-xs text-gray-400 mt-0.5">min 3% utili (art. 2545-ter c.c.)</div>
                            </div>
                            <div class="p-3 bg-indigo-100 dark:bg-indigo-900/40 rounded-lg">
                                <div class="text-xs text-gray-500 mb-1">Totale riserve</div>
                                <div class="text-xl font-bold text-indigo-900 dark:text-indigo-100">{{ fmt(riserve_accantonate.totale) }}</div>
                            </div>
                        </div>

                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Accantonamenti {{ anno }}</div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="p-3 border rounded-lg dark:border-gray-700 text-center">
                                <div class="text-xs text-gray-500 mb-1">Riserva Legale</div>
                                <div class="font-bold">{{ fmt(riserve_accantonate.legale_anno) }}</div>
                            </div>
                            <div class="p-3 border rounded-lg dark:border-gray-700 text-center">
                                <div class="text-xs text-gray-500 mb-1">Riserva Indivisibile</div>
                                <div class="font-bold">{{ fmt(riserve_accantonate.indivisibile_anno) }}</div>
                            </div>
                            <div class="p-3 border rounded-lg dark:border-gray-700 text-center bg-gray-50 dark:bg-gray-700/50">
                                <div class="text-xs text-gray-500 mb-1">Totale {{ anno }}</div>
                                <div class="font-bold">{{ fmt(riserve_accantonate.totale_anno) }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ── RISTORNI ────────────────────────────────────────────────── -->
                <section v-if="ristorni_anno.length > 0" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-4 border-b dark:border-gray-700 bg-blue-50 dark:bg-blue-900/20">
                        <ChartBarIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        <h3 class="font-semibold text-blue-800 dark:text-blue-200">Ristorni — Anno {{ anno }}</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Delibera</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Importo deliberato</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Aliquota rit.</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Pagamento</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="r in ristorni_anno" :key="r.id">
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ fmtDate(r.data_delibera_assemblea) }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ fmt(r.importo_totale_deliberato) }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ (r.aliquota_ritenuta * 100).toFixed(0) }}%</td>
                                <td class="px-4 py-2 text-gray-500">{{ fmtDate(r.data_pagamento) }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full" :class="badgeFor(r.status).cls">
                                        {{ badgeFor(r.status).label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <div v-else class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
                    Nessun ristorno deliberato per l'anno {{ anno }}.
                </div>

            </div>
        </div>
    </AppLayout>
</template>
