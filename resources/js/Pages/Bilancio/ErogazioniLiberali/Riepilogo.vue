<script setup>
import {
    HeartIcon,
    ArrowDownTrayIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    riepilogo:   Object,
    anno:        Number,
    anniRange:   Array,
    modalita:    Object,
    tipiDonante: Object,
});

const page   = usePage();
const tenant = page.props.tenant;

const annoScelto = ref(props.anno);
watch(annoScelto, (val) => {
    router.get(route('erogazioni-liberali.riepilogo', tenant), { anno: val }, { preserveState: false });
});

const fmt  = (n) => Number(n ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtN = (n) => Number(n ?? 0).toLocaleString('it-IT');

// Map per_modalita keys to labels
const modalitaLabel = (key) => props.modalita[key] ?? key;
const tipoLabel     = (key) => props.tipiDonante[key] ?? key;

// Percentuale detraibili
const pctDetraibili = computed(() => {
    if (!props.riepilogo.totale_importo) return 0;
    return ((props.riepilogo.totale_detraibili / props.riepilogo.totale_importo) * 100).toFixed(1);
});

// Totale detrazioni spettanti ai donanti
const totaleDetrazioni = computed(() => {
    return props.riepilogo.per_donante?.reduce((sum, d) => {
        const a = d.donante_tipo === 'persona_fisica' ? 0.26 : 0.30;
        return sum + (parseFloat(d.totale_detraibile) || 0) * a;
    }, 0) ?? 0;
});
</script>

<template>
    <AppLayout title="Riepilogo Erogazioni Liberali">
        <Head title="Riepilogo Erogazioni Liberali" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <HeartIcon class="w-6 h-6 text-rose-500" />
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                        Riepilogo Erogazioni Liberali — {{ annoScelto }}
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="route('erogazioni-liberali.csv', [tenant, { anno: annoScelto }])"
                       class="inline-flex items-center gap-1.5 text-sm bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg px-3 py-1.5">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        CSV AdE
                    </a>
                    <a :href="route('erogazioni-liberali.xml', [tenant, { anno: annoScelto }])"
                       class="inline-flex items-center gap-1.5 text-sm bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg px-3 py-1.5">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        XML AdE
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Anno selector -->
            <div class="flex flex-wrap gap-2">
                <button v-for="a in anniRange" :key="a"
                        @click="annoScelto = a"
                        :class="['px-4 py-1.5 rounded-lg border text-sm font-semibold transition',
                                 annoScelto === a
                                   ? 'bg-rose-600 border-rose-600 text-white'
                                   : 'bg-white border-gray-300 text-gray-600 hover:border-rose-300 dark:bg-gray-800 dark:border-gray-600']">
                    {{ a }}
                </button>
            </div>

            <!-- KPI summary -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <div class="text-xs text-gray-400 uppercase">Totale raccolto</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">€ {{ fmt(riepilogo.totale_importo) }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ fmtN(riepilogo.count) }} donazioni</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <div class="text-xs text-gray-400 uppercase">Detraibili</div>
                    <div class="text-2xl font-bold text-green-700 mt-1">€ {{ fmt(riepilogo.totale_detraibili) }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ pctDetraibili }}% del totale</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <div class="text-xs text-gray-400 uppercase">Non detraibili</div>
                    <div class="text-2xl font-bold text-red-500 mt-1">€ {{ fmt(riepilogo.totale_non_detraibili) }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">contanti / non tracciabili</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <div class="text-xs text-gray-400 uppercase">Tot. detrazioni donanti</div>
                    <div class="text-2xl font-bold text-purple-700 mt-1">€ {{ fmt(totaleDetrazioni) }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">stimato (26%/30%)</div>
                </div>
            </div>

            <!-- Breakdown per tipo donante -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-3">Per tipo donante</h3>
                    <div v-if="!Object.keys(riepilogo.per_tipo ?? {}).length" class="text-sm text-gray-400 italic">
                        Nessun dato
                    </div>
                    <div v-for="(stats, tipo) in riepilogo.per_tipo" :key="tipo"
                         class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0 text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ tipoLabel(tipo) }}</span>
                        <span class="text-right">
                            <span class="font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(stats.tot) }}</span>
                            <span class="text-gray-400 ml-1">({{ fmtN(stats.n) }})</span>
                        </span>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-3">Per modalità pagamento</h3>
                    <div v-if="!Object.keys(riepilogo.per_modalita ?? {}).length" class="text-sm text-gray-400 italic">
                        Nessun dato
                    </div>
                    <div v-for="(stats, mod) in riepilogo.per_modalita" :key="mod"
                         class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0 text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ modalitaLabel(mod) }}</span>
                        <span class="text-right">
                            <span class="font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(stats.tot) }}</span>
                            <span class="text-gray-400 ml-1">({{ fmtN(stats.n) }})</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Per donante (CU-style) -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                    <DocumentTextIcon class="w-5 h-5 text-gray-400" />
                    <h3 class="font-semibold text-gray-700 dark:text-gray-200">Dettaglio per donante</h3>
                    <span class="text-xs text-gray-400 ml-1">(riepilogo ai fini della comunicazione AdE)</span>
                </div>
                <div v-if="!riepilogo.per_donante?.length"
                     class="p-8 text-center text-gray-400 text-sm">
                    Nessuna erogazione per l'anno {{ annoScelto }}.
                </div>
                <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Codice fiscale</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Donante</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">N.</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Totale versato</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Di cui detraibile</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Detrazione est.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="d in riepilogo.per_donante" :key="d.donante_cf"
                            class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-4 py-3 text-sm font-mono text-gray-600 dark:text-gray-300">
                                {{ d.donante_cf || '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ d.donante_ragione_sociale ?? [d.donante_cognome, d.donante_nome].filter(Boolean).join(' ') || '—' }}
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-500">
                                {{ d.donante_tipo === 'persona_fisica' ? 'PF' : 'PG' }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-600">{{ d.n_versamenti }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">
                                € {{ fmt(d.totale_versato) }}
                            </td>
                            <td class="px-4 py-3 text-right text-green-700 font-semibold">
                                € {{ fmt(d.totale_detraibile) }}
                            </td>
                            <td class="px-4 py-3 text-right text-purple-700 font-semibold text-sm">
                                € {{ fmt((parseFloat(d.totale_detraibile) || 0) * (d.donante_tipo === 'persona_fisica' ? 0.26 : 0.30)) }}
                                <span class="text-xs text-gray-400 ml-0.5">
                                    ({{ d.donante_tipo === 'persona_fisica' ? '26' : '30' }}%)
                                </span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-700 font-bold">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">TOTALE</td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">€ {{ fmt(riepilogo.totale_importo) }}</td>
                            <td class="px-4 py-3 text-right text-green-700">€ {{ fmt(riepilogo.totale_detraibili) }}</td>
                            <td class="px-4 py-3 text-right text-purple-700">€ {{ fmt(totaleDetrazioni) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Back link -->
            <div>
                <Link :href="route('erogazioni-liberali.index', [tenant, { anno: annoScelto }])"
                      class="text-sm text-gray-500 hover:text-gray-700">← Elenco erogazioni</Link>
            </div>

        </div>
    </AppLayout>
</template>
