<script setup>
import {
    PlusIcon,
    MagnifyingGlassIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    UserGroupIcon,
    BanknotesIcon,
    ExclamationCircleIcon,
    DocumentTextIcon,
    ClipboardDocumentListIcon,
    ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    compensi:   Object,
    kpi:        Object,
    filters:    Object,
    anni:       Array,
    statiLabel: Object,
});

/* ── Filtri ─────────────────────────────────────────────────────────────── */
const annoFiltro  = ref(props.filters?.anno   ?? new Date().getFullYear());
const statoFiltro = ref(props.filters?.stato  ?? '');
const search      = ref(props.filters?.search ?? '');

let debounce = null;
watch([annoFiltro, statoFiltro, search], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('compensi-terzi.index'),
            {
                anno:   annoFiltro.value  || undefined,
                stato:  statoFiltro.value || undefined,
                search: search.value      || undefined,
            },
            { preserveState: true, replace: true }
        );
    }, 400);
});

/* ── Helpers ────────────────────────────────────────────────────────────── */
const fmt     = (n) => Number(n ?? 0).toFixed(2);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoBadgeClass = (stato) => stato === 'da_versare'
    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';

const tipiLabel = {
    occasionale:   'Occasionale',
    professionale: 'Professionale',
    provvigioni:   'Provvigioni',
};
</script>

<template>
    <AppLayout title="Compensi a terzi">
        <Head title="Compensi a terzi — Ritenute d'acconto" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <UserGroupIcon class="size-5 text-gray-500" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Compensi a terzi
                    </h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">({{ compensi.total }} totali)</span>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('compensi-terzi.riepilogo')"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ClipboardDocumentListIcon class="size-4" />CU Riepilogo
                    </Link>
                    <a :href="route('compensi-terzi.genera-cu') + '?anno=' + annoFiltro"
                        target="_blank"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-indigo-300 dark:border-indigo-600 rounded-md font-medium text-xs text-indigo-700 dark:text-indigo-300 uppercase tracking-widest hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                        <ArrowDownTrayIcon class="size-4" />Genera CU {{ annoFiltro }}
                    </a>
                    <Link :href="route('compensi-terzi.versamenti')"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <BanknotesIcon class="size-4" />Versamenti F24
                    </Link>
                    <Link :href="route('compensi-terzi.create')">
                        <PrimaryButton>
                            <PlusIcon class="size-4 me-2" />Nuovo compenso
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto sm:px-6 space-y-4">

            <!-- KPI ─────────────────────────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 flex items-center gap-4">
                    <div class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                        <BanknotesIcon class="size-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Compensi lordi {{ filters.anno }}</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(kpi.totale_compensi) }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 flex items-center gap-4">
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900/30">
                        <DocumentTextIcon class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ritenute totali</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(kpi.totale_ritenute) }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 flex items-center gap-4">
                    <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900/30">
                        <ExclamationCircleIcon class="size-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ritenute da versare</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(kpi.ritenute_da_versare) }}</p>
                    </div>
                </div>
            </div>

            <!-- Filtri ──────────────────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="relative">
                        <MagnifyingGlassIcon class="absolute left-3 top-2.5 size-4 text-gray-400" />
                        <input v-model="search" type="text" placeholder="Nome o CF percipiente..."
                            class="pl-9 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>
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

            <!-- Tabella ─────────────────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table v-if="compensi.data?.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Percipiente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">C.F.</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Lordo</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Ritenuta</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Netto</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="c in compensi.data" :key="c.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ fmtDate(c.data_pagamento) }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ c.nome_percipiente }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ c.codice_fiscale }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ tipiLabel[c.tipo_rapporto] ?? c.tipo_rapporto }}</td>
                            <td class="px-4 py-3 text-right font-mono">€ {{ fmt(c.compenso_lordo) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-red-600 dark:text-red-400">€ {{ fmt(c.ritenuta) }}</td>
                            <td class="px-4 py-3 text-right font-medium font-mono">€ {{ fmt(c.compenso_netto) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                      :class="statoBadgeClass(c.stato_ritenuta)">
                                    {{ statiLabel[c.stato_ritenuta] ?? c.stato_ritenuta }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="route('compensi-terzi.show', c.id)"
                                      class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Dettaglio
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="py-12 text-center text-gray-500 dark:text-gray-400">
                    Nessun compenso trovato per l'anno {{ filters.anno }}.
                </p>

                <!-- Paginazione -->
                <div v-if="compensi.prev_page_url || compensi.next_page_url"
                     class="px-4 py-3 border-t dark:border-gray-700 flex justify-between items-center text-sm">
                    <Link v-if="compensi.prev_page_url" :href="compensi.prev_page_url"
                          class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        <ArrowLeftIcon class="size-4" />Indietro
                    </Link>
                    <span v-else />
                    <span class="text-gray-500 dark:text-gray-400 text-xs">
                        Pagina {{ compensi.current_page }} di {{ compensi.last_page }}
                    </span>
                    <Link v-if="compensi.next_page_url" :href="compensi.next_page_url"
                          class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        Avanti<ArrowRightIcon class="size-4" />
                    </Link>
                    <span v-else />
                </div>
            </div>

        </div>
    </AppLayout>
</template>
