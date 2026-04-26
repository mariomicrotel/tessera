<script setup>
import {
    HeartIcon,
    PlusIcon,
    ArrowDownTrayIcon,
    DocumentTextIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    CheckCircleIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    erogazioni:  Object,    // paginated
    kpi:         Object,
    annoFiltro:  Number,
    anniRange:   Array,
    modalita:    Object,
    tipiDonante: Object,
});

const page   = usePage();
const tenant = page.props.tenant;

const anno = ref(props.annoFiltro);

watch(anno, (val) => {
    router.get(route('erogazioni-liberali.index', tenant), { anno: val }, { preserveState: true, replace: true });
});

const fmt     = (n) => Number(n ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const modalitaBadge = (m) => ({
    bonifico:           'bg-blue-100 text-blue-700',
    assegno_circolare:  'bg-purple-100 text-purple-700',
    carta_credito:      'bg-indigo-100 text-indigo-700',
    carta_debito:       'bg-indigo-100 text-indigo-700',
    altro_tracciabile:  'bg-gray-100 text-gray-700',
    contante:           'bg-red-100 text-red-700',
}[m] ?? 'bg-gray-100 text-gray-600');

// Import da incassi
const importa = () => {
    if (confirm(`Importare le donazioni dall'archivio Incassi per l'anno ${anno.value}?`)) {
        router.post(route('erogazioni-liberali.importa', tenant), { anno: anno.value });
    }
};
</script>

<template>
    <AppLayout title="Erogazioni Liberali">
        <Head title="Erogazioni Liberali" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <HeartIcon class="w-6 h-6 text-rose-500" />
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Erogazioni Liberali</h2>
                    <span class="text-xs text-gray-400">Art. 83 D.Lgs. 117/2017</span>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('erogazioni-liberali.riepilogo', [tenant, { anno }])"
                          class="inline-flex items-center gap-1 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-200 rounded-lg px-3 py-1.5">
                        <DocumentTextIcon class="w-4 h-4" />
                        Riepilogo
                    </Link>
                    <Link :href="route('erogazioni-liberali.create', tenant)"
                          class="inline-flex items-center">
                        <PrimaryButton class="flex items-center gap-2">
                            <PlusIcon class="w-4 h-4" />
                            Nuova erogazione
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Anno filter -->
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Anno:</span>
                <div class="flex flex-wrap gap-2">
                    <button v-for="a in anniRange" :key="a"
                            @click="anno = a"
                            :class="['px-4 py-1.5 rounded-lg border text-sm font-semibold transition',
                                     anno === a
                                       ? 'bg-rose-600 border-rose-600 text-white'
                                       : 'bg-white border-gray-300 text-gray-600 hover:border-rose-300 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300']">
                        {{ a }}
                    </button>
                </div>
            </div>

            <!-- KPI cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <div class="text-xs text-gray-400 uppercase">Totale raccolto</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">€ {{ fmt(kpi.totale) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <div class="text-xs text-gray-400 uppercase">Di cui detraibili</div>
                    <div class="text-2xl font-bold text-green-700 mt-1">€ {{ fmt(kpi.detraibili) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <div class="text-xs text-gray-400 uppercase">N. donazioni</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ kpi.count }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <div class="text-xs text-gray-400 uppercase">Detraibili</div>
                    <div class="text-2xl font-bold text-green-700 mt-1">{{ kpi.count_detraibili }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">su {{ kpi.count }} totali</div>
                </div>
            </div>

            <!-- Export + Import actions bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-gray-800 rounded-xl shadow px-5 py-3">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                    Export comunicazione AdE (solo detraibili):
                </span>
                <div class="flex gap-2">
                    <a :href="route('erogazioni-liberali.csv', [tenant, { anno }])"
                       class="inline-flex items-center gap-1.5 text-sm bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg px-3 py-1.5">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        CSV AdE
                    </a>
                    <a :href="route('erogazioni-liberali.xml', [tenant, { anno }])"
                       class="inline-flex items-center gap-1.5 text-sm bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg px-3 py-1.5">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        XML AdE
                    </a>
                    <button @click="importa"
                            class="inline-flex items-center gap-1.5 text-sm bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 rounded-lg px-3 py-1.5">
                        Importa da Incassi
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="erogazioni.data.length === 0"
                 class="bg-white dark:bg-gray-800 rounded-xl shadow p-12 text-center">
                <HeartIcon class="w-14 h-14 text-rose-200 mx-auto mb-4" />
                <p class="text-gray-500 dark:text-gray-400 font-medium mb-2">Nessuna erogazione per il {{ anno }}</p>
                <p class="text-xs text-gray-400 mb-5">
                    Registra donazioni detraibili ai sensi dell'art. 83 CTS,<br>
                    oppure importa le donazioni già presenti in Incassi.
                </p>
                <Link :href="route('erogazioni-liberali.create', tenant)">
                    <PrimaryButton>Registra prima donazione</PrimaryButton>
                </Link>
            </div>

            <!-- Table -->
            <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Donante</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">C.F.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Modalità</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Importo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Detraibile</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Aliquota</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="e in erogazioni.data" :key="e.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                {{ fmtDate(e.data_erogazione) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ e.donante_ragione_sociale ?? [e.donante_cognome, e.donante_nome].filter(Boolean).join(' ') || '—' }}
                                </div>
                                <div class="text-xs text-gray-400">{{ tipiDonante[e.donante_tipo] ?? e.donante_tipo }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm font-mono text-gray-600 dark:text-gray-300">
                                {{ e.donante_cf || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-block px-2 py-0.5 rounded-full text-xs font-medium', modalitaBadge(e.modalita_pagamento)]">
                                    {{ modalita[e.modalita_pagamento] ?? e.modalita_pagamento }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">
                                € {{ fmt(e.importo) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <CheckCircleIcon v-if="e.is_detraibile" class="w-5 h-5 text-green-600 mx-auto" />
                                <XCircleIcon v-else class="w-5 h-5 text-red-400 mx-auto" />
                            </td>
                            <td class="px-4 py-3 text-center text-sm font-medium">
                                <span v-if="e.aliquota_detrazione"
                                      :class="e.donante_tipo === 'ente' ? 'text-purple-700' : 'text-blue-700'">
                                    {{ e.aliquota_detrazione }}%
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('erogazioni-liberali.show', [tenant, e.id])"
                                      class="text-blue-600 hover:text-blue-800 text-sm font-medium mr-3">
                                    Dettaglio
                                </Link>
                                <Link :href="route('erogazioni-liberali.edit', [tenant, e.id])"
                                      class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                    Modifica
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="erogazioni.last_page > 1"
                     class="flex justify-between items-center px-5 py-3 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-500">
                    <span>Pagina {{ erogazioni.current_page }} di {{ erogazioni.last_page }}</span>
                    <div class="flex gap-2">
                        <Link v-if="erogazioni.prev_page_url" :href="erogazioni.prev_page_url"
                              class="p-1 rounded hover:bg-gray-100">
                            <ArrowLeftIcon class="w-4 h-4" />
                        </Link>
                        <Link v-if="erogazioni.next_page_url" :href="erogazioni.next_page_url"
                              class="p-1 rounded hover:bg-gray-100">
                            <ArrowRightIcon class="w-4 h-4" />
                        </Link>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
