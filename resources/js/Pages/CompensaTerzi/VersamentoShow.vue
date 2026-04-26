<script setup>
import {
    BanknotesIcon,
    ArrowLeftIcon,
    CheckCircleIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    versamento: Object,
});

const fmt     = (n) => Number(n ?? 0).toFixed(2);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const tipiLabel = { occasionale: 'Occasionale', professionale: 'Professionale', provvigioni: 'Provvigioni' };

const nomeMese = (mese, anno) => {
    try {
        return new Date(anno, mese - 1, 1).toLocaleDateString('it-IT', { month: 'long', year: 'numeric' });
    } catch { return `${mese}/${anno}`; }
};

const scadenzaLegale = (mese, anno) => {
    const m = mese === 12 ? 1 : mese + 1;
    const a = mese === 12 ? anno + 1 : anno;
    return `16/${String(m).padStart(2,'0')}/${a}`;
};
</script>

<template>
    <AppLayout :title="`Versamento F24 — ${versamento.mese_riferimento}/${versamento.anno_riferimento}`">
        <Head :title="`Versamento F24 ${versamento.mese_riferimento}/${versamento.anno_riferimento}`" />
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <BanknotesIcon class="size-5 text-gray-500" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight capitalize">
                        Versamento F24 — {{ nomeMese(versamento.mese_riferimento, versamento.anno_riferimento) }}
                    </h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        <CheckCircleIcon class="size-3" />Versato
                    </span>
                </div>
                <Link :href="route('compensi-terzi.versamenti')"
                    class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                    <ArrowLeftIcon class="size-4" />Versamenti
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6 space-y-6">

            <!-- Riepilogo versamento -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Mese di riferimento</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100 capitalize">
                        {{ nomeMese(versamento.mese_riferimento, versamento.anno_riferimento) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Data versamento</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ fmtDate(versamento.data_versamento) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Scadenza legale</p>
                    <p class="font-mono text-gray-700 dark:text-gray-300">
                        {{ scadenzaLegale(versamento.mese_riferimento, versamento.anno_riferimento) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Codice tributo</p>
                    <p class="font-mono text-gray-900 dark:text-gray-100">{{ versamento.codice_tributo }}</p>
                </div>
                <div v-if="versamento.codice_ufficio">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Codice ufficio</p>
                    <p class="font-mono text-gray-700 dark:text-gray-300">{{ versamento.codice_ufficio }}</p>
                </div>
                <div v-if="versamento.codice_atto">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Codice atto</p>
                    <p class="font-mono text-gray-700 dark:text-gray-300">{{ versamento.codice_atto }}</p>
                </div>
                <div class="sm:col-span-2 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-3 text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Importo totale versato</p>
                    <p class="text-2xl font-bold font-mono text-indigo-700 dark:text-indigo-300">€ {{ fmt(versamento.importo_totale) }}</p>
                </div>
            </div>

            <!-- Note -->
            <div v-if="versamento.note" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-sm">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Note</p>
                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ versamento.note }}</p>
            </div>

            <!-- Compensi collegati -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center gap-2">
                    <DocumentTextIcon class="size-4 text-gray-500" />
                    <h3 class="font-semibold text-sm text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Compensi inclusi nel versamento ({{ versamento.compensi?.length ?? 0 }})
                    </h3>
                </div>
                <table v-if="versamento.compensi?.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Percipiente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">C.F.</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Lordo</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Ritenuta</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="c in versamento.compensi" :key="c.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ c.nome_percipiente }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ c.codice_fiscale }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ tipiLabel[c.tipo_rapporto] ?? c.tipo_rapporto }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ fmtDate(c.data_pagamento) }}</td>
                            <td class="px-4 py-3 text-right font-mono">€ {{ fmt(c.compenso_lordo) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-red-600 dark:text-red-400">€ {{ fmt(c.ritenuta) }}</td>
                            <td class="px-4 py-3">
                                <Link :href="route('compensi-terzi.show', c.id)"
                                      class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Dettaglio
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-700 border-t dark:border-gray-600">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Totale</td>
                            <td class="px-4 py-3 text-right font-bold font-mono text-gray-900 dark:text-gray-100">
                                € {{ fmt(versamento.compensi.reduce((s, c) => s + parseFloat(c.compenso_lordo ?? 0), 0)) }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold font-mono text-red-600 dark:text-red-400">
                                € {{ fmt(versamento.importo_totale) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <p v-else class="py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                    Nessun compenso collegato.
                </p>
            </div>

        </div>
    </AppLayout>
</template>
