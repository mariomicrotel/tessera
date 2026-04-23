<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChartBarIcon, TableCellsIcon, CheckCircleIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    esercizio:  Number,
    riepilogo:  Object,
    bozze:      Object,
    definitive: Array,
    anni:       Array,
});

const esiEselezionato = ref(props.esercizio);

function cambiaEsercizio() {
    router.get(route('cespiti.ammortamento.index'), { esercizio: esiEselezionato.value }, { preserveState: false });
}

// Form genera bozze
const generaForm = useForm({ esercizio: props.esercizio });
function genera() {
    if (!confirm(`Generare le bozze di ammortamento per l'esercizio ${props.esercizio}?`)) return;
    generaForm.post(route('cespiti.ammortamento.genera'));
}

// Form conferma esercizio
const confermaForm = useForm({
    esercizio:          props.esercizio,
    data_registrazione: new Date().toISOString().substring(0, 10),
});
const showConfermaModal = ref(false);
function confermaEsercizio() {
    confermaForm.post(route('cespiti.ammortamento.conferma-esercizio'), {
        onSuccess: () => { showConfermaModal.value = false; },
    });
}

// Form registra singola
function registraSingola(scheduleId) {
    router.post(route('cespiti.ammortamento.registra', scheduleId), {}, {
        preserveScroll: true,
    });
}

function fmt(n) {
    return Number(n ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <AppLayout title="Ammortamenti">
        <Head title="Ammortamenti" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <ChartBarIcon class="size-6 text-gray-500" />
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Ammortamenti</h2>
                </div>
                <div class="flex items-center gap-3">
                    <select v-model="esiEselezionato" @change="cambiaEsercizio"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-3 py-1.5 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option v-for="anno in anni" :key="anno" :value="anno">Esercizio {{ anno }}</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- KPI riepilogo -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">In bozza</p>
                        <p class="text-2xl font-bold text-yellow-500">{{ riepilogo.bozze }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Definitive</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ riepilogo.definitive }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Totale quote {{ esercizio }}</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">€ {{ fmt(riepilogo.totale_quota) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Cespiti ammortizzati</p>
                        <p class="text-2xl font-bold text-gray-500">{{ riepilogo.cespiti_ammortizzati }}</p>
                    </div>
                </div>

                <!-- Azioni principali -->
                <div class="flex flex-wrap gap-3 items-center">
                    <a :href="route('cespiti.registro-pdf') + '?esercizio=' + esercizio" target="_blank"
                       class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <DocumentArrowDownIcon class="size-4" />
                        PDF Registro {{ esercizio }}
                    </a>
                    <button @click="genera" :disabled="generaForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition">
                        <TableCellsIcon class="size-4" />
                        {{ generaForm.processing ? 'Generazione…' : `Genera bozze ${esercizio}` }}
                    </button>
                    <button v-if="riepilogo.bozze > 0" @click="showConfermaModal = true"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                        <CheckCircleIcon class="size-4" />
                        Conferma esercizio {{ esercizio }} ({{ riepilogo.bozze }} bozze)
                    </button>
                </div>

                <!-- Bozze -->
                <div v-if="bozze.data.length > 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Bozze in attesa di registrazione</h3>
                        <span class="text-xs text-yellow-600 bg-yellow-50 dark:bg-yellow-900/20 dark:text-yellow-400 px-2 py-0.5 rounded-full">
                            {{ bozze.total }} bozze
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Cespite</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Categoria</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Aliquota</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quota calcolata</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">VNC fine anno</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="s in bozze.data" :key="s.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-2.5">
                                        <Link :href="route('cespiti.show', s.asset_id)" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ s.asset?.name ?? `#${s.asset_id}` }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">
                                        {{ s.asset?.category?.codice ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-400">{{ s.aliquota_applicata }}%</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(s.quota_calcolata) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-700 dark:text-gray-300">€ {{ fmt(s.valore_residuo_fine_anno) }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <button @click="registraSingola(s.id)"
                                                class="text-xs px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition">
                                            Registra
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Paginazione bozze -->
                    <div v-if="bozze.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex gap-1 justify-end">
                        <Link v-for="link in bozze.links" :key="link.label"
                              :href="link.url ?? '#'"
                              v-html="link.label"
                              class="px-3 py-1 rounded border text-xs transition"
                              :class="link.active ? 'bg-blue-600 text-white border-blue-600' : link.url ? 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' : 'border-gray-200 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-default'" />
                    </div>
                </div>

                <!-- Definitive -->
                <div v-if="definitive.length > 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Quote registrate in prima nota</h3>
                        <span class="text-xs text-green-600 bg-green-50 dark:bg-green-900/20 dark:text-green-400 px-2 py-0.5 rounded-full">
                            {{ definitive.length }} definitive
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Cespite</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quota registrata</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">VNC fine anno</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Data registrazione</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="s in definitive" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-2.5">
                                        <Link :href="route('cespiti.show', s.asset_id)" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ s.asset?.name ?? `#${s.asset_id}` }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(s.quota_registrata) }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-700 dark:text-gray-300">€ {{ fmt(s.valore_residuo_fine_anno) }}</td>
                                    <td class="px-4 py-2.5 text-center text-gray-500 dark:text-gray-400">{{ s.data_registrazione ?? '—' }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700">
                                <tr>
                                    <td class="px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300">Totale</td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold text-gray-900 dark:text-gray-100">
                                        € {{ fmt(definitive.reduce((s, r) => s + Number(r.quota_registrata), 0)) }}
                                    </td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Stato vuoto -->
                <div v-if="bozze.data.length === 0 && definitive.length === 0"
                     class="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-12 text-center">
                    <ChartBarIcon class="size-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                    <p class="text-gray-500 dark:text-gray-400 mb-4">Nessuna quota per l'esercizio {{ esercizio }}.</p>
                    <button @click="genera" :disabled="generaForm.processing"
                            class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50 transition">
                        {{ generaForm.processing ? 'Generazione…' : `Genera bozze ${esercizio}` }}
                    </button>
                </div>

            </div>
        </div>

        <!-- Modal conferma esercizio -->
        <div v-if="showConfermaModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Conferma esercizio {{ esercizio }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Verranno registrate in prima nota <strong>{{ riepilogo.bozze }}</strong> quote di ammortamento.
                    L'operazione è irreversibile.
                </p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data di registrazione</label>
                    <input v-model="confermaForm.data_registrazione" type="date"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                </div>
                <div class="flex justify-end gap-3">
                    <button @click="showConfermaModal = false"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Annulla
                    </button>
                    <button @click="confermaEsercizio" :disabled="confermaForm.processing"
                            class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 transition">
                        {{ confermaForm.processing ? 'Conferma…' : 'Conferma e registra' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
