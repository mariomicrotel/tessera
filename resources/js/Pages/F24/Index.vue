<script setup>
import {
    DocumentTextIcon,
    PlusIcon,
    MagnifyingGlassIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    BanknotesIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    modelli:    Object,
    kpi:        Object,
    filters:    Object,
    anni:       Array,
    statiLabel: Object,
});

const annoFiltro  = ref(props.filters?.anno  ?? new Date().getFullYear());
const statoFiltro = ref(props.filters?.stato ?? '');

let debounce = null;
watch([annoFiltro, statoFiltro], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('f24.index'),
            { anno: annoFiltro.value || undefined, stato: statoFiltro.value || undefined },
            { preserveState: true, replace: true }
        );
    }, 300);
});

const fmt     = (n) => Number(n ?? 0).toFixed(2);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoBadgeClass = (stato) => ({
    bozza:     'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    compilato: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    versato:   'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
}[stato] ?? '');

const mesiLabel = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                   'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

const periodoLabel = (m) => m.mese ? `${mesiLabel[m.mese]} ${m.anno}` : `Anno ${m.anno}`;
</script>

<template>
    <AppLayout title="Modelli F24">
        <Head title="Modelli F24" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <DocumentTextIcon class="size-5 text-gray-500" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Modelli F24
                    </h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">({{ modelli.total }} totali)</span>
                </div>
                <Link :href="route('f24.create')">
                    <PrimaryButton>
                        <PlusIcon class="size-4 me-2" />Nuovo F24
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto sm:px-6 space-y-4">

            <!-- KPI -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 flex items-center gap-4">
                    <div class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                        <BanknotesIcon class="size-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Totale debiti {{ filters.anno }}</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(kpi.totale_debiti) }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 flex items-center gap-4">
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900/30">
                        <CheckCircleIcon class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Totale crediti</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(kpi.totale_crediti) }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 flex items-center gap-4">
                    <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900/30">
                        <ExclamationCircleIcon class="size-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Saldo da versare</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(kpi.saldo_da_versare) }}</p>
                    </div>
                </div>
            </div>

            <!-- Filtri -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <select v-model="statoFiltro"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                        <option value="">Tutti gli stati</option>
                        <option v-for="(label, key) in statiLabel" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <select v-model="annoFiltro"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                        <option v-for="a in anni" :key="a" :value="a">{{ a }}</option>
                    </select>
                </div>
            </div>

            <!-- Tabella -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table v-if="modelli.data?.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Periodo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Compilato il</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Versato il</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Debiti</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Crediti</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Saldo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="m in modelli.data" :key="m.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                {{ periodoLabel(m) }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ fmtDate(m.data_compilazione) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ fmtDate(m.data_versamento) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(m.totale_debiti) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-green-700 dark:text-green-400">€ {{ fmt(m.totale_crediti) }}</td>
                            <td class="px-4 py-3 text-right font-bold font-mono"
                                :class="m.saldo > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400'">
                                € {{ fmt(m.saldo) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                    :class="statoBadgeClass(m.stato)">
                                    {{ statiLabel[m.stato] ?? m.stato }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="route('f24.show', m.id)"
                                      class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Dettaglio
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="py-12 text-center text-gray-500 dark:text-gray-400">
                    Nessun F24 per l'anno {{ filters.anno }}.
                </p>

                <!-- Paginazione -->
                <div v-if="modelli.prev_page_url || modelli.next_page_url"
                     class="px-4 py-3 border-t dark:border-gray-700 flex justify-between items-center text-sm">
                    <Link v-if="modelli.prev_page_url" :href="modelli.prev_page_url"
                          class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        <ArrowLeftIcon class="size-4" />Indietro
                    </Link>
                    <span v-else />
                    <span class="text-gray-500 dark:text-gray-400 text-xs">
                        Pagina {{ modelli.current_page }} di {{ modelli.last_page }}
                    </span>
                    <Link v-if="modelli.next_page_url" :href="modelli.next_page_url"
                          class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        Avanti<ArrowRightIcon class="size-4" />
                    </Link>
                    <span v-else />
                </div>
            </div>

        </div>
    </AppLayout>
</template>
