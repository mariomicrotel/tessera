<script setup>
import {
    FunnelIcon,
    XMarkIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    DocumentArrowDownIcon,
} from '@heroicons/vue/24/outline';
import { reactive, ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    fatture: Object,
    totali: Object,
    filters: Object,
    dataInizio: String,
    dataFine: String,
    errore: String,
});

const page = usePage();
const tenant = computed(() => page.props.currentTenant);

// ── Filtri ────────────────────────────────────────────────────────────────
const annoCorrente = new Date().getFullYear();
const anni = Array.from({ length: 7 }, (_, i) => annoCorrente - 3 + i);

const mesiLabel = [
    'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
];
const trimestriLabel = ['Q1 (Gen–Mar)', 'Q2 (Apr–Giu)', 'Q3 (Lug–Set)', 'Q4 (Ott–Dic)'];

const form = reactive({
    anno:         props.filters?.anno         ?? annoCorrente,
    periodo:      props.filters?.periodo      ?? new Date().getMonth() + 1,
    tipo_periodo: props.filters?.tipoPeriodo  ?? 'mensile',
});

const periodOptions = computed(() => {
    if (form.tipo_periodo === 'mensile')     return mesiLabel.map((label, i) => ({ value: i + 1, label }));
    if (form.tipo_periodo === 'trimestrale') return trimestriLabel.map((label, i) => ({ value: i + 1, label }));
    return [{ value: 1, label: 'Anno completo' }];
});

function applyFilters() {
    router.get(
        route('iva.registro-vendite', { tenant: tenant.value?.slug }),
        { anno: form.anno, periodo: form.periodo, tipo_periodo: form.tipo_periodo },
        { preserveState: true, replace: true },
    );
}

function resetFilters() {
    form.anno         = annoCorrente;
    form.periodo      = new Date().getMonth() + 1;
    form.tipo_periodo = 'mensile';
    applyFilters();
}

function onTipoPeriodoChange() {
    form.periodo = 1;
    applyFilters();
}

// ── Espansione righe ──────────────────────────────────────────────────────
const expanded = ref(new Set());
function toggleRighe(id) {
    if (expanded.value.has(id)) {
        expanded.value.delete(id);
    } else {
        expanded.value.add(id);
    }
    expanded.value = new Set(expanded.value);
}

// ── Formattazione ─────────────────────────────────────────────────────────
function fmtEur(val) {
    return '€\u00a0' + Number(val ?? 0).toFixed(2);
}
function fmtData(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('it-IT');
}

// ── Badge stato ───────────────────────────────────────────────────────────
const statoBadge = {
    bozza:        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    emessa:       'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    inviata_sdi:  'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    accettata:    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    scartata:     'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    annullata:    'bg-gray-200 text-gray-500 dark:bg-gray-800 dark:text-gray-500',
};
const statoLabel = {
    bozza:       'Bozza',
    emessa:      'Emessa',
    inviata_sdi: 'Inviata SDI',
    accettata:   'Accettata',
    scartata:    'Scartata',
    annullata:   'Annullata',
};
</script>

<template>
    <AppLayout title="Registro Vendite IVA">
        <Head title="Registro Vendite IVA" />

        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Registro IVA Vendite
                    </h2>
                    <p v-if="dataInizio && dataFine" class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Periodo: {{ fmtData(dataInizio) }} – {{ fmtData(dataFine) }}
                    </p>
                </div>
                <a
                    :href="route('iva.registro-vendite', { tenant: tenant?.slug }) + '?anno=' + form.anno + '&periodo=' + form.periodo + '&tipo_periodo=' + form.tipo_periodo + '&export=csv'"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                    <DocumentArrowDownIcon class="size-4" />Export CSV
                </a>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Errore periodo non valido -->
                <div v-if="errore" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 text-red-700 dark:text-red-400 text-sm">
                    {{ errore }}
                </div>

                <!-- ── Filtri ──────────────────────────────────────────────────── -->
                <form @submit.prevent="applyFilters" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                    <div class="flex flex-wrap gap-3 items-end">
                        <!-- Anno -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Anno</label>
                            <select
                                v-model.number="form.anno"
                                @change="applyFilters"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            >
                                <option v-for="a in anni" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                        <!-- Tipo periodo -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Tipo</label>
                            <select
                                v-model="form.tipo_periodo"
                                @change="onTipoPeriodoChange"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            >
                                <option value="mensile">Mensile</option>
                                <option value="trimestrale">Trimestrale</option>
                                <option value="annuale">Annuale</option>
                            </select>
                        </div>
                        <!-- Periodo -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Periodo</label>
                            <select
                                v-model.number="form.periodo"
                                @change="applyFilters"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            >
                                <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <PrimaryButton type="submit">
                                <FunnelIcon class="size-4 me-1" />Filtra
                            </PrimaryButton>
                            <button
                                type="button"
                                @click="resetFilters"
                                class="inline-flex items-center gap-1 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <XMarkIcon class="size-4" />Azzera
                            </button>
                        </div>
                    </div>
                </form>

                <!-- ── Totali periodo ──────────────────────────────────────────── -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-center">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Imponibile</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ fmtEur(totali?.imponibile) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-center">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">IVA</p>
                        <p class="mt-1 text-xl font-semibold text-orange-600 dark:text-orange-400">{{ fmtEur(totali?.iva) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-center">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Totale</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ fmtEur(totali?.totale) }}</p>
                    </div>
                </div>

                <!-- ── Tabella fatture ─────────────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="w-8 px-2 py-2"></th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data Fatt.</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">N° Fattura</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Sezionale</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Imponibile</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">IVA</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Totale</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <template v-for="f in fatture.data" :key="f.id">
                                <!-- Riga principale -->
                                <tr
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/40 cursor-pointer"
                                    @click="toggleRighe(f.id)"
                                >
                                    <td class="px-2 py-2 text-center text-gray-400">
                                        <ChevronDownIcon v-if="expanded.has(f.id)" class="size-4 mx-auto" />
                                        <ChevronRightIcon v-else class="size-4 mx-auto" />
                                    </td>
                                    <td class="px-4 py-2 text-sm whitespace-nowrap">{{ fmtData(f.data_fattura) }}</td>
                                    <td class="px-4 py-2 text-sm font-medium">{{ f.numero_fattura || '—' }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 uppercase">{{ f.sezionale || '—' }}</td>
                                    <td class="px-4 py-2 text-sm text-right whitespace-nowrap">{{ fmtEur(f.imponibile_totale) }}</td>
                                    <td class="px-4 py-2 text-sm text-right whitespace-nowrap text-orange-600 dark:text-orange-400">{{ fmtEur(f.iva_totale) }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-medium whitespace-nowrap">{{ fmtEur(f.totale_documento) }}</td>
                                    <td class="px-4 py-2">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                            :class="statoBadge[f.stato] ?? 'bg-gray-100 text-gray-700'"
                                        >
                                            {{ statoLabel[f.stato] ?? f.stato ?? '—' }}
                                        </span>
                                    </td>
                                </tr>
                                <!-- Righe IVA espanse -->
                                <tr v-if="expanded.has(f.id) && f.righe?.length" class="bg-orange-50/50 dark:bg-orange-900/10">
                                    <td colspan="8" class="px-8 py-2">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-500 dark:text-gray-400">
                                                    <th class="text-left pb-1 font-medium">Descrizione</th>
                                                    <th class="text-right pb-1 font-medium">Aliquota</th>
                                                    <th class="text-right pb-1 font-medium">Qnt</th>
                                                    <th class="text-right pb-1 font-medium">Imponibile</th>
                                                    <th class="text-right pb-1 font-medium">IVA</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-orange-100 dark:divide-orange-900/30">
                                                <tr v-for="r in f.righe" :key="r.id">
                                                    <td class="py-0.5 text-gray-700 dark:text-gray-300">{{ r.descrizione || '—' }}</td>
                                                    <td class="py-0.5 text-right">{{ r.codice_iva?.aliquota ?? '—' }}%</td>
                                                    <td class="py-0.5 text-right">{{ r.quantita }}</td>
                                                    <td class="py-0.5 text-right">{{ fmtEur(r.imponibile) }}</td>
                                                    <td class="py-0.5 text-right text-orange-600 dark:text-orange-400">{{ fmtEur(r.iva) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <p v-if="!fatture.data?.length" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                        Nessuna fattura attiva nel periodo selezionato.
                    </p>

                    <!-- Paginazione -->
                    <div
                        v-if="fatture.prev_page_url || fatture.next_page_url"
                        class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center"
                    >
                        <a
                            v-if="fatture.prev_page_url"
                            :href="fatture.prev_page_url"
                            class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            <ArrowLeftIcon class="size-4" />Indietro
                        </a>
                        <span v-else></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Pagina {{ fatture.current_page }} di {{ fatture.last_page }}
                            ({{ fatture.total }} fatture)
                        </span>
                        <a
                            v-if="fatture.next_page_url"
                            :href="fatture.next_page_url"
                            class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            Avanti<ArrowRightIcon class="size-4" />
                        </a>
                        <span v-else></span>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
