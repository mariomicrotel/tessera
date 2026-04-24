<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ExclamationTriangleIcon, ClockIcon, BuildingStorefrontIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    inScadenza:          Array,
    scadute:             Array,
    fornitoriInScadenza: Array,
    fornitoriScadute:    Array,
    totaleScaduto:       Number,
});

const formatDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const formatEur  = (n) => Number(n ?? 0).toLocaleString('it-IT', { style: 'currency', currency: 'EUR' });
</script>

<template>
    <AppLayout title="Dashboard Scadenzario">
        <Head title="Dashboard Scadenzario" />
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Dashboard Scadenzario</h2>
                <div class="flex gap-2">
                    <Link :href="route('scadenze.index')">
                        <SecondaryButton>Tutte le scadenze</SecondaryButton>
                    </Link>
                    <Link :href="route('scadenze.create')">
                        <PrimaryButton>+ Nuova scadenza</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- KPI cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-700">
                        <p class="text-sm text-red-600 dark:text-red-400 font-medium">Totale scaduto</p>
                        <p class="text-2xl font-bold text-red-800 dark:text-red-200 mt-1">{{ formatEur(totaleScaduto) }}</p>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 border border-yellow-200 dark:border-yellow-700">
                        <p class="text-sm text-yellow-700 dark:text-yellow-400 font-medium">Scadenze generiche scadute</p>
                        <p class="text-2xl font-bold text-yellow-800 dark:text-yellow-200 mt-1">{{ scadute.length }}</p>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-700">
                        <p class="text-sm text-blue-700 dark:text-blue-400 font-medium">In scadenza (30 gg)</p>
                        <p class="text-2xl font-bold text-blue-800 dark:text-blue-200 mt-1">{{ inScadenza.length + fornitoriInScadenza.length }}</p>
                    </div>
                </div>

                <!-- Scadenze generiche scadute -->
                <div v-if="scadute.length > 0" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-700 flex items-center gap-2">
                        <ExclamationTriangleIcon class="size-5 text-red-600 dark:text-red-400" />
                        <h3 class="font-semibold text-red-800 dark:text-red-200">Scadenze generiche scadute</h3>
                    </div>
                    <table class="min-w-full text-sm divide-y divide-gray-100 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Scadenza</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Descrizione</th>
                                <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-300">Importo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="s in scadute" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-4 py-2 text-red-700 dark:text-red-400 font-medium">{{ formatDate(s.data_scadenza) }}</td>
                                <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ s.descrizione }}</td>
                                <td class="px-4 py-2 text-right text-gray-800 dark:text-gray-200">{{ formatEur(s.importo) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Fatture fornitori scadute -->
                <div v-if="fornitoriScadute.length > 0" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-700 flex items-center gap-2">
                        <BuildingStorefrontIcon class="size-5 text-red-600 dark:text-red-400" />
                        <h3 class="font-semibold text-red-800 dark:text-red-200">Fatture fornitori scadute</h3>
                    </div>
                    <table class="min-w-full text-sm divide-y divide-gray-100 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Scadenza</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Fornitore</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">N° Fattura</th>
                                <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-300">Totale</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="f in fornitoriScadute" :key="f.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-4 py-2 text-red-700 dark:text-red-400 font-medium">{{ formatDate(f.data_scadenza) }}</td>
                                <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ f.supplier?.name ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ f.numero_fattura }}</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-800 dark:text-gray-200">{{ formatEur(f.totale_documento) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- In scadenza entro 30 gg -->
                <div v-if="inScadenza.length > 0 || fornitoriInScadenza.length > 0"
                     class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-200 dark:border-yellow-700 flex items-center gap-2">
                        <ClockIcon class="size-5 text-yellow-600 dark:text-yellow-400" />
                        <h3 class="font-semibold text-yellow-800 dark:text-yellow-200">In scadenza nei prossimi 30 giorni</h3>
                    </div>
                    <table class="min-w-full text-sm divide-y divide-gray-100 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Scadenza</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Tipo</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Descrizione</th>
                                <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-300">Importo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="s in inScadenza" :key="'g-'+s.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ formatDate(s.data_scadenza) }}</td>
                                <td class="px-4 py-2 text-gray-500 dark:text-gray-400 capitalize">{{ s.tipo }}</td>
                                <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ s.descrizione }}</td>
                                <td class="px-4 py-2 text-right text-gray-800 dark:text-gray-200">{{ formatEur(s.importo) }}</td>
                            </tr>
                            <tr v-for="f in fornitoriInScadenza" :key="'f-'+f.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ formatDate(f.data_scadenza) }}</td>
                                <td class="px-4 py-2 text-gray-500 dark:text-gray-400">Fornitore</td>
                                <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ f.supplier?.name ?? '—' }} — {{ f.numero_fattura }}</td>
                                <td class="px-4 py-2 text-right text-gray-800 dark:text-gray-200">{{ formatEur(f.totale_documento) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!scadute.length && !fornitoriScadute.length && !inScadenza.length && !fornitoriInScadenza.length"
                     class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <DocumentTextIcon class="size-12 mx-auto mb-3 opacity-40" />
                    <p>Nessuna scadenza urgente. Ottimo lavoro!</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
