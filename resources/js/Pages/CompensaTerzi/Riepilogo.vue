<script setup>
import {
    ClipboardDocumentListIcon,
    ArrowLeftIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    riepilogo: Array,
    anno:      Number,
    anni:      Array,
});

const annoFiltro = ref(props.anno);

watch(annoFiltro, (a) => {
    router.get(route('compensi-terzi.riepilogo'), { anno: a }, { preserveState: true, replace: true });
});

const fmt = (n) => Number(n ?? 0).toFixed(2);

const causaliLabel = { A: 'Autonomo/Occasionale', Q: 'Provvigioni', R: 'Provv. plurimand.', V: 'Provv. agente' };
</script>

<template>
    <AppLayout title="Riepilogo CU — Compensi a terzi">
        <Head title="Riepilogo CU compensi a terzi" />
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <ClipboardDocumentListIcon class="size-5 text-gray-500" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Riepilogo CU — Anno {{ anno }}
                    </h2>
                </div>
                <div class="flex gap-2 items-center">
                    <select v-model="annoFiltro"
                        class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                        <option v-for="a in anni" :key="a" :value="a">{{ a }}</option>
                    </select>
                    <Link :href="route('compensi-terzi.index')"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ArrowLeftIcon class="size-4" />Elenco
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto sm:px-6 space-y-6">

            <p v-if="!riepilogo?.length" class="py-12 text-center text-gray-500 dark:text-gray-400">
                Nessun compenso per l'anno {{ anno }}.
            </p>

            <!-- Una card per percipiente -->
            <div v-for="p in riepilogo" :key="p.codice_fiscale"
                 class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">

                <!-- Header percipiente -->
                <div class="px-6 py-4 border-b dark:border-gray-700 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <UserGroupIcon class="size-4 text-gray-500" />
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ p.nome_percipiente }}</h3>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            CF: <span class="font-mono">{{ p.codice_fiscale }}</span>
                            <span v-if="p.partita_iva"> · P.IVA: <span class="font-mono">{{ p.partita_iva }}</span></span>
                            <span v-if="p.indirizzo"> · {{ p.indirizzo }}</span>
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <span v-for="causale in p.causali" :key="causale"
                                  class="inline-flex px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs font-mono text-gray-700 dark:text-gray-300">
                                {{ causale }} — {{ causaliLabel[causale] ?? causale }}
                            </span>
                        </div>
                    </div>
                    <div class="text-right text-sm">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ritenute totali</p>
                        <p class="text-xl font-bold font-mono text-red-600 dark:text-red-400">€ {{ fmt(p.totale_ritenuta) }}</p>
                    </div>
                </div>

                <!-- Totali CU -->
                <div class="px-6 py-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm border-b dark:border-gray-700">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Totale lordo</p>
                        <p class="font-mono font-semibold text-gray-900 dark:text-gray-100">€ {{ fmt(p.totale_compenso_lordo) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Base imponibile</p>
                        <p class="font-mono font-semibold text-gray-900 dark:text-gray-100">€ {{ fmt(p.totale_base_imponibile) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Totale netto</p>
                        <p class="font-mono font-semibold text-green-700 dark:text-green-400">€ {{ fmt(p.totale_netto) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Rimborsi spese</p>
                        <p class="font-mono text-gray-700 dark:text-gray-300">€ {{ fmt(p.totale_rimborsi) }}</p>
                    </div>
                    <div v-if="p.totale_inps_beneficiario > 0">
                        <p class="text-xs text-gray-500 dark:text-gray-400">INPS beneficiario</p>
                        <p class="font-mono text-gray-700 dark:text-gray-300">€ {{ fmt(p.totale_inps_beneficiario) }}</p>
                    </div>
                    <div v-if="p.totale_inps_committente > 0">
                        <p class="text-xs text-gray-500 dark:text-gray-400">INPS committente</p>
                        <p class="font-mono text-gray-700 dark:text-gray-300">€ {{ fmt(p.totale_inps_committente) }}</p>
                    </div>
                </div>

                <!-- Dettaglio compensi -->
                <table class="min-w-full text-xs divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase">Causale prestazione</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-300 uppercase">Lordo</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-300 uppercase">Ritenuta</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500 dark:text-gray-300 uppercase">Netto</th>
                            <th class="px-4 py-2 text-center font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="c in p.compensi" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ c.data_pagamento ? new Date(c.data_pagamento).toLocaleDateString('it-IT') : '—' }}
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300 max-w-xs truncate">{{ c.causale_prestazione }}</td>
                            <td class="px-4 py-2 text-right font-mono">€ {{ fmt(c.compenso_lordo) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-red-600 dark:text-red-400">€ {{ fmt(c.ritenuta) }}</td>
                            <td class="px-4 py-2 text-right font-mono">€ {{ fmt(c.compenso_netto) }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium"
                                    :class="c.stato_ritenuta === 'da_versare'
                                        ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'">
                                    {{ c.stato_ritenuta === 'da_versare' ? 'Da versare' : 'Versata' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>
