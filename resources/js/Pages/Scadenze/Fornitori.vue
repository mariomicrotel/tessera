<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { FunnelIcon, ChevronLeftIcon, ChevronRightIcon, ExclamationTriangleIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    fatture:       Object,
    totaleScaduto: Number,
    stati:         Object,
    filters:       Object,
});

const form = reactive({
    stato:  props.filters?.stato  ?? '',
    dal:    props.filters?.dal    ?? '',
    al:     props.filters?.al     ?? '',
    search: props.filters?.search ?? '',
});

const filter = () => router.get(route('scadenze.fornitori'), form);

const formatDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const formatEur  = (n) => Number(n ?? 0).toLocaleString('it-IT', { style: 'currency', currency: 'EUR' });

const isScaduta = (f) => {
    if (['pagata', 'annullata'].includes(f.stato_pagamento)) return false;
    return f.data_scadenza && new Date(f.data_scadenza) < new Date();
};

const giorniAllaScadenza = (f) => {
    if (!f.data_scadenza) return null;
    const diff = Math.ceil((new Date(f.data_scadenza) - new Date()) / 86400000);
    return diff;
};

const statoPagBadge = (stato) => {
    if (stato === 'pagata') return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    if (stato === 'parzialmente_pagata') return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
    if (stato === 'annullata') return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
    return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
};
</script>

<template>
    <AppLayout title="Scadenzario Fornitori">
        <Head title="Scadenzario Fornitori" />
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Scadenzario Fornitori</h2>
                <div class="flex gap-2">
                    <Link :href="route('scadenze.dashboard')">
                        <SecondaryButton>Dashboard</SecondaryButton>
                    </Link>
                    <Link :href="route('scadenze.index')">
                        <SecondaryButton>Generiche</SecondaryButton>
                    </Link>
                    <Link :href="route('scadenze.clienti')">
                        <SecondaryButton>Clienti</SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Riepilogo scaduto -->
                <div v-if="totaleScaduto > 0" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-4 flex items-center gap-3">
                    <ExclamationTriangleIcon class="size-6 text-red-600 dark:text-red-400 flex-shrink-0" />
                    <div>
                        <p class="font-semibold text-red-800 dark:text-red-200">Fatture scadute non pagate</p>
                        <p class="text-sm text-red-700 dark:text-red-300">Totale scaduto: <strong>{{ formatEur(totaleScaduto) }}</strong></p>
                    </div>
                </div>

                <!-- Filtri -->
                <form @submit.prevent="filter" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Stato pagamento</label>
                        <select v-model="form.stato" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                            <option value="">Da pagare (default)</option>
                            <option v-for="(label, key) in stati" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Scadenza dal</label>
                        <input v-model="form.dal" type="date" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Al</label>
                        <input v-model="form.al" type="date" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>
                    <div class="flex-1 min-w-[160px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Cerca fornitore / n° fattura</label>
                        <input v-model="form.search" type="text" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-1" />Filtra</PrimaryButton>
                </form>

                <!-- Tabella -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Scadenza</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Fornitore</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">N° Fattura</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Data fattura</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Totale</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Stato</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">Apri</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="!fatture.data.length">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    Nessuna fattura trovata.
                                </td>
                            </tr>
                            <tr v-for="f in fatture.data" :key="f.id"
                                :class="['hover:bg-gray-50 dark:hover:bg-gray-750',
                                         isScaduta(f) ? 'bg-red-50 dark:bg-red-900/10' :
                                         (giorniAllaScadenza(f) !== null && giorniAllaScadenza(f) <= 7 && !['pagata','annullata'].includes(f.stato_pagamento)) ? 'bg-orange-50 dark:bg-orange-900/10' : '']">
                                <td class="px-4 py-3 whitespace-nowrap font-medium"
                                    :class="isScaduta(f) ? 'text-red-700 dark:text-red-400' :
                                            (giorniAllaScadenza(f) !== null && giorniAllaScadenza(f) <= 7 && !['pagata','annullata'].includes(f.stato_pagamento)) ? 'text-orange-700 dark:text-orange-400' :
                                            'text-gray-900 dark:text-gray-100'">
                                    {{ formatDate(f.data_scadenza) }}
                                    <span v-if="isScaduta(f)" class="ml-1 text-xs text-red-500">(scaduta)</span>
                                    <span v-else-if="giorniAllaScadenza(f) !== null && giorniAllaScadenza(f) <= 7 && !['pagata','annullata'].includes(f.stato_pagamento)"
                                          class="ml-1 text-xs text-orange-500">({{ giorniAllaScadenza(f) }}gg)</span>
                                </td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200 font-medium">{{ f.supplier?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ f.numero_fattura }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formatDate(f.data_fattura) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-200">{{ formatEur(f.totale_documento) }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statoPagBadge(f.stato_pagamento)]">
                                        {{ stati[f.stato_pagamento] ?? f.stato_pagamento?.replace(/_/g, ' ') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Link :href="route('iva.fatture-passive.show', f)"
                                          title="Apri fattura"
                                          class="inline-flex items-center text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                        <ArrowTopRightOnSquareIcon class="size-4" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Paginazione -->
                    <div v-if="fatture.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Pagina {{ fatture.current_page }} di {{ fatture.last_page }}
                        </span>
                        <div class="flex gap-1">
                            <Link v-if="fatture.prev_page_url" :href="fatture.prev_page_url">
                                <SecondaryButton class="py-1"><ChevronLeftIcon class="size-4" /></SecondaryButton>
                            </Link>
                            <Link v-if="fatture.next_page_url" :href="fatture.next_page_url">
                                <SecondaryButton class="py-1"><ChevronRightIcon class="size-4" /></SecondaryButton>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
