<script setup>
import {
    PlusIcon,
    MagnifyingGlassIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    DocumentTextIcon,
    BanknotesIcon,
    ClockIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    fatture:    Object,
    kpi:        Object,
    filters:    Object,
    statiLabel: Object,
});

/* ── Filtri ─────────────────────────────────────────────────────────────── */
const search          = ref(props.filters?.numero          ?? '');
const statoFiltro     = ref(props.filters?.stato           ?? '');
const statoPagFiltro  = ref(props.filters?.stato_pagamento ?? '');
const tipoDocFiltro   = ref(props.filters?.tipo_documento  ?? '');
const annoFiltro      = ref(props.filters?.anno            ? Number(props.filters.anno) : '');

let debounce = null;
watch([search, statoFiltro, statoPagFiltro, tipoDocFiltro, annoFiltro], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => applyFilters(), 400);
});

function applyFilters() {
    router.get(
        route('iva.fatture-attive.index'),
        {
            numero:           search.value         || undefined,
            stato:            statoFiltro.value     || undefined,
            stato_pagamento:  statoPagFiltro.value  || undefined,
            tipo_documento:   tipoDocFiltro.value   || undefined,
            anno:             annoFiltro.value       || undefined,
        },
        { preserveState: true, replace: true }
    );
}

/* ── Helpers ────────────────────────────────────────────────────────────── */
const fmt     = (n) => Number(n ?? 0).toFixed(2);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoBadgeClass = (stato) => ({
    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300':         stato === 'bozza',
    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200':         stato === 'emessa',
    'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200': stato === 'inviata_sdi',
    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':     stato === 'accettata',
    'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300':             stato === 'scartata',
    'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500':         stato === 'annullata',
});

const pagBadgeClass = (stato) => ({
    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': stato === 'da_incassare',
    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':     stato === 'incassata',
    'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': stato === 'parzialmente_incassata',
});

const pagLabels = {
    da_incassare:           'Da incassare',
    incassata:              'Incassata',
    parzialmente_incassata: 'Parz. incassata',
};

const currentYear = new Date().getFullYear();
const anni = Array.from({ length: 5 }, (_, i) => currentYear - i);
</script>

<template>
    <AppLayout title="Fatture attive">
        <Head title="Fatture attive" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <DocumentTextIcon class="size-5 text-gray-500" aria-hidden="true" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Fatture attive
                    </h2>
                    <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">({{ fatture.total }} totali)</span>
                </div>
                <Link :href="route('iva.fatture-attive.create')">
                    <PrimaryButton>
                        <PlusIcon class="size-4 me-2" aria-hidden="true" />Nuova fattura
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto sm:px-6 space-y-4">

            <!-- KPI ─────────────────────────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Totale emesse -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex items-center gap-4">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                        <DocumentTextIcon class="size-6 text-blue-600 dark:text-blue-300" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Totale emesse</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(kpi.totale_emesse) }}</p>
                    </div>
                </div>
                <!-- Incassate -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex items-center gap-4">
                    <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                        <CheckCircleIcon class="size-6 text-green-600 dark:text-green-300" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Incassate</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(kpi.totale_incassate) }}</p>
                    </div>
                </div>
                <!-- Da incassare -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex items-center gap-4">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                        <ClockIcon class="size-6 text-yellow-600 dark:text-yellow-300" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Da incassare</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(kpi.totale_da_incassare) }}</p>
                    </div>
                </div>
            </div>

            <!-- Filtri ──────────────────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="relative">
                        <MagnifyingGlassIcon class="absolute left-3 top-2.5 size-4 text-gray-400" aria-hidden="true" />
                        <input v-model="search" type="text" placeholder="Numero fattura…"
                            class="pl-9 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>
                    <select v-model="statoFiltro"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                        <option value="">Tutti gli stati documento</option>
                        <option v-for="(label, key) in statiLabel" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <select v-model="statoPagFiltro"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                        <option value="">Tutti gli stati pagamento</option>
                        <option value="da_incassare">Da incassare</option>
                        <option value="incassata">Incassata</option>
                        <option value="parzialmente_incassata">Parz. incassata</option>
                    </select>
                    <select v-model="tipoDocFiltro"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                        <option value="">Tutti i tipi</option>
                        <option value="TD01">Fatture</option>
                        <option value="TD04">Note di Credito</option>
                    </select>
                    <select v-model="annoFiltro"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                        <option value="">Tutti gli anni</option>
                        <option v-for="a in anni" :key="a" :value="a">{{ a }}</option>
                    </select>
                </div>
            </div>

            <!-- Tabella ─────────────────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table v-if="fatture.data?.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Numero</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cliente</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Imponibile</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IVA</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Totale</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stato doc.</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pagamento</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="fa in fatture.data" :key="fa.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ fmtDate(fa.data_fattura) }}</td>
                            <td class="px-4 py-3 font-medium whitespace-nowrap">
                                <Link :href="route('iva.fatture-attive.show', fa.id)"
                                      class="text-indigo-600 dark:text-indigo-400 hover:underline font-mono">
                                    {{ fa.numero_fattura }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="fa.tipo_documento === 'TD04'"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200"
                                    title="Nota di Credito">
                                    NC
                                </span>
                                <span v-else class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ fa.tipo_documento }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                <span v-if="fa.cliente_id" class="text-xs text-gray-500 dark:text-gray-400">
                                    Cliente #{{ fa.cliente_id }}
                                </span>
                                <span v-else class="text-gray-400 italic text-xs">—</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">€ {{ fmt(fa.imponibile_totale) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-600 dark:text-gray-400">€ {{ fmt(fa.iva_totale) }}</td>
                            <td class="px-4 py-3 text-right font-medium font-mono">€ {{ fmt(fa.totale_documento) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                      :class="statoBadgeClass(fa.stato)">
                                    {{ statiLabel[fa.stato] ?? fa.stato }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                      :class="pagBadgeClass(fa.stato_pagamento)">
                                    {{ pagLabels[fa.stato_pagamento] ?? fa.stato_pagamento }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="route('iva.fatture-attive.show', fa.id)"
                                      class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Dettaglio
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-else class="py-12 text-center text-gray-500 dark:text-gray-400">
                    Nessuna fattura attiva trovata.
                </p>

                <!-- Paginazione -->
                <div v-if="fatture.prev_page_url || fatture.next_page_url"
                     class="px-4 py-3 border-t dark:border-gray-700 flex justify-between items-center text-sm">
                    <Link v-if="fatture.prev_page_url" :href="fatture.prev_page_url"
                          class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        <ArrowLeftIcon class="size-4" />Indietro
                    </Link>
                    <span v-else />
                    <span class="text-gray-500 dark:text-gray-400 text-xs">
                        Pagina {{ fatture.current_page }} di {{ fatture.last_page }}
                    </span>
                    <Link v-if="fatture.next_page_url" :href="fatture.next_page_url"
                          class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        Avanti<ArrowRightIcon class="size-4" />
                    </Link>
                    <span v-else />
                </div>
            </div>

        </div>
    </AppLayout>
</template>
