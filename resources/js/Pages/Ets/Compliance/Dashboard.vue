<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    XCircleIcon,
    MinusCircleIcon,
    ArrowPathIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    ultimoCheck: Object,
    storicoChecks: Array,
    tenant: Object,
    canRun: Boolean,
});

const runCheck = () => {
    if (!confirm('Eseguire il controllo di compliance?')) return;
    router.post(route('ets.compliance.run'));
};

const formatDate = (d) => d ? new Date(d).toLocaleString('it-IT') : '—';
const formatDateShort = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoColor = (stato) => stato === 'con_errori'
    ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
    : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';

const punteggioColor = computed(() => {
    const p = props.ultimoCheck?.punteggio_percentuale ?? 0;
    if (p >= 80) return 'text-green-600';
    if (p >= 50) return 'text-yellow-600';
    return 'text-red-600';
});
</script>

<template>
    <AppLayout title="Compliance ETS">
        <Head title="Compliance ETS" />
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Compliance ETS</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Statuto · Atto Costitutivo · RUNTS · Verifica normativa</p>
                </div>
                <PrimaryButton v-if="canRun" @click="runCheck">
                    <ArrowPathIcon class="size-4 me-2" />Esegui controllo
                </PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Navigazione moduli -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Link :href="route('ets.compliance.dashboard')" class="block bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-lg p-4 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition">
                        <div class="font-semibold text-indigo-700 dark:text-indigo-300">Controllo compliance</div>
                        <div class="text-sm text-indigo-600 dark:text-indigo-400 mt-1">Verifica automatica delle regole</div>
                    </Link>
                    <Link :href="route('ets.statuto.index')" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <div class="font-semibold text-gray-700 dark:text-gray-300">Statuto</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Versioni, clausole e allegati</div>
                    </Link>
                    <Link :href="route('ets.atto-costitutivo.index')" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <div class="font-semibold text-gray-700 dark:text-gray-300">Atto Costitutivo</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Atto notarile e registrazione</div>
                    </Link>
                </div>

                <!-- Ultimo check -->
                <div v-if="ultimoCheck" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">Ultimo controllo</h3>
                        <span :class="statoColor(ultimoCheck.stato)" class="text-xs font-medium px-2 py-1 rounded-full">{{ ultimoCheck.stato_label }}</span>
                    </div>
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-4">
                            <div class="text-center">
                                <div :class="punteggioColor" class="text-3xl font-bold">{{ ultimoCheck.punteggio_percentuale }}%</div>
                                <div class="text-xs text-gray-500 mt-1">Punteggio</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">{{ ultimoCheck.n_ok }}</div>
                                <div class="text-xs text-gray-500 mt-1">Conformi</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-yellow-600">{{ ultimoCheck.n_avviso }}</div>
                                <div class="text-xs text-gray-500 mt-1">Avvisi</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-red-600">{{ ultimoCheck.n_errore }}</div>
                                <div class="text-xs text-gray-500 mt-1">Non conformi</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-400">{{ ultimoCheck.n_na }}</div>
                                <div class="text-xs text-gray-500 mt-1">N/A</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                            <span class="flex items-center gap-1"><ClockIcon class="size-4" />{{ formatDate(ultimoCheck.run_at) }}</span>
                            <Link :href="route('ets.compliance.show', ultimoCheck.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                Dettaglio →
                            </Link>
                        </div>
                    </div>
                    <!-- Errori in evidenza -->
                    <div v-if="ultimoCheck.n_errore > 0" class="px-6 py-3 bg-red-50 dark:bg-red-900/20 border-t border-red-100 dark:border-red-800">
                        <div class="text-sm font-medium text-red-700 dark:text-red-300 mb-2">Non conformità da correggere:</div>
                        <ul class="space-y-1">
                            <li
                                v-for="r in ultimoCheck.results.filter(x => x.esito === 'errore')"
                                :key="r.id"
                                class="text-sm text-red-600 dark:text-red-400 flex items-start gap-2"
                            >
                                <XCircleIcon class="size-4 mt-0.5 shrink-0" />
                                <span><strong>{{ r.rule?.titolo }}</strong>: {{ r.messaggio }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Nessun check ancora -->
                <div v-else class="bg-white dark:bg-gray-800 shadow rounded-lg px-6 py-12 text-center">
                    <ArrowPathIcon class="size-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-gray-500 dark:text-gray-400 mb-4">Nessun controllo eseguito. Avvia il primo controllo per verificare la conformità normativa.</p>
                    <PrimaryButton v-if="canRun" @click="runCheck">Esegui il primo controllo</PrimaryButton>
                </div>

                <!-- Storico -->
                <div v-if="storicoChecks.length > 1" class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">Storico controlli</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">OK</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Avvisi</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Errori</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Punteggio</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="c in storicoChecks" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatDateShort(c.run_at) }}</td>
                                <td class="px-6 py-3 text-center text-sm font-medium text-green-600">{{ c.n_ok }}</td>
                                <td class="px-6 py-3 text-center text-sm font-medium text-yellow-600">{{ c.n_avviso }}</td>
                                <td class="px-6 py-3 text-center text-sm font-medium text-red-600">{{ c.n_errore }}</td>
                                <td class="px-6 py-3 text-center text-sm font-medium">{{ c.punteggio_percentuale }}%</td>
                                <td class="px-6 py-3 text-right">
                                    <Link :href="route('ets.compliance.show', c.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm">Dettaglio</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Disclaimer -->
                <p class="text-xs text-gray-400 dark:text-gray-500 italic text-center">
                    ⚠ I controlli automatici sono indicativi e non sostituiscono il parere di un notaio, commercialista o consulente legale specializzato in diritto del Terzo Settore.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
