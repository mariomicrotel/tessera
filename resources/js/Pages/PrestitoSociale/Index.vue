<script setup>
import { computed, ref, watch } from 'vue';
import {
    PlusIcon, ArrowLeftIcon, ArrowRightIcon,
    BanknotesIcon, UserGroupIcon, ChartBarIcon, CalculatorIcon,
    XMarkIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    libretti:  Object,
    kpi:       Object,
    filters:   Object,
});

// ── Filtri ────────────────────────────────────────────────────────────────────
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
let filterTimeout = null;

function applyFilters() {
    router.get(
        route('prestito-sociale.index'),
        { search: search.value || undefined, status: status.value || undefined },
        { preserveState: true, replace: true },
    );
}

watch([search, status], () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(applyFilters, 300);
});

// ── Modal calcola interessi ───────────────────────────────────────────────────
const showCalcolaModal = ref(false);
const formInteressi = useForm({
    anno: new Date().getFullYear(),
    mese: new Date().getMonth() + 1,
});

function submitCalcolaInteressi() {
    formInteressi.post(route('prestito-sociale.calcola-interessi'), {
        onSuccess: () => { showCalcolaModal.value = false; },
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmt = (v) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v) || 0);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const fmtPct  = (v) => ((Number(v) || 0) * 100).toFixed(2).replace('.', ',') + '%';

const memberName = (lib) => {
    const m = lib.member;
    if (!m) return '—';
    if (m.tipo_persona === 'giuridica') return m.ragione_sociale ?? '—';
    return `${m.cognome ?? ''} ${m.nome ?? ''}`.trim() || '—';
};

const statusConfig = {
    attivo:   { label: 'Attivo',   cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
    sospeso:  { label: 'Sospeso',  cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    chiuso:   { label: 'Chiuso',   cls: 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400' },
};
const badge = (s) => statusConfig[s] ?? { label: s, cls: 'bg-gray-100 text-gray-600' };

const mesiLabels = ['', 'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
    'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
</script>

<template>
    <AppLayout title="Prestito Sociale">
        <Head title="Prestito Sociale" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Prestito Sociale
                </h2>
                <div class="flex gap-2 flex-wrap">
                    <button
                        type="button"
                        @click="showCalcolaModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 border border-purple-300 dark:border-purple-600 rounded-md text-sm font-medium text-purple-700 dark:text-purple-300 hover:bg-purple-50 dark:hover:bg-purple-900/20"
                    >
                        <CalculatorIcon class="size-4" aria-hidden="true" />
                        Calcola Interessi Mese
                    </button>
                    <Link :href="route('prestito-sociale.create')">
                        <PrimaryButton>
                            <PlusIcon class="size-4 me-2" aria-hidden="true" />
                            Nuovo Libretto
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- KPI Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 flex items-start gap-4">
                        <div class="p-2 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 shrink-0">
                            <UserGroupIcon class="size-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide truncate">Libretti Attivi</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-0.5">{{ kpi.num_libretti_attivi }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Saldo: {{ fmt(kpi.tot_saldo_attivo) }}</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 flex items-start gap-4">
                        <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900/40 shrink-0">
                            <BanknotesIcon class="size-5 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide truncate">Totale Depositi</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ fmt(kpi.totale_depositi) }}</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 flex items-start gap-4">
                        <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/40 shrink-0">
                            <ChartBarIcon class="size-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide truncate">Interessi {{ kpi.anno }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ fmt(kpi.interessi_anno) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Lordi</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 flex items-start gap-4">
                        <div class="p-2 rounded-lg bg-red-100 dark:bg-red-900/40 shrink-0">
                            <BanknotesIcon class="size-5 text-red-500 dark:text-red-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide truncate">Ritenute {{ kpi.anno }}</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ fmt(kpi.ritenute_anno) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">26% su interessi</p>
                        </div>
                    </div>
                </div>

                <!-- Filtri -->
                <div class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Cerca socio</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Nome, cognome, ragione sociale…"
                            class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm w-56"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Status</label>
                        <select
                            v-model="status"
                            class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                        >
                            <option value="">Tutti</option>
                            <option value="attivo">Attivo</option>
                            <option value="sospeso">Sospeso</option>
                            <option value="chiuso">Chiuso</option>
                        </select>
                    </div>
                </div>

                <!-- Tabella libretti -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Socio</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">N° Libretto</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Saldo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tasso annuo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data apertura</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="lib in libretti.data"
                                :key="lib.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                :class="{ 'opacity-60': lib.status === 'chiuso' }"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ memberName(lib) }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-indigo-700 dark:text-indigo-400">{{ lib.numero_libretto }}</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold"
                                    :class="Number(lib.saldo_attuale) > 0 ? 'text-green-700 dark:text-green-400' : 'text-gray-500'">
                                    {{ fmt(lib.saldo_attuale) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">
                                    {{ fmtPct(lib.tasso_interesse_annuo) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ fmtDate(lib.data_apertura) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium" :class="badge(lib.status).cls">
                                        {{ badge(lib.status).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm space-x-3 whitespace-nowrap">
                                    <Link :href="route('prestito-sociale.show', lib.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        Dettaglio
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!libretti.data?.length" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                        Nessun libretto trovato.
                    </p>

                    <!-- Paginazione -->
                    <div v-if="libretti.prev_page_url || libretti.next_page_url" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <Link v-if="libretti.prev_page_url" :href="libretti.prev_page_url"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline text-sm">
                            <ArrowLeftIcon class="size-4" />Indietro
                        </Link>
                        <span v-else></span>
                        <span class="text-xs text-gray-400">Pagina {{ libretti.current_page }} / {{ libretti.last_page }}</span>
                        <Link v-if="libretti.next_page_url" :href="libretti.next_page_url"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline text-sm">
                            Avanti<ArrowRightIcon class="size-4" />
                        </Link>
                        <span v-else></span>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Calcola Interessi -->
        <Teleport to="body">
            <div v-if="showCalcolaModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <CalculatorIcon class="size-5 text-purple-600" />
                            Calcola Interessi Mensili
                        </h3>
                        <button type="button" @click="showCalcolaModal = false"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <XMarkIcon class="size-5" />
                        </button>
                    </div>

                    <form @submit.prevent="submitCalcolaInteressi" class="space-y-4">
                        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-3 py-2 text-xs text-amber-700 dark:text-amber-300">
                            Calcola gli interessi maturati per tutti i libretti attivi nel mese selezionato.
                            L'operazione è idempotente: se il mese è già stato elaborato, viene saltato.
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anno</label>
                                <input
                                    v-model.number="formInteressi.anno"
                                    type="number"
                                    min="2000"
                                    max="2100"
                                    class="mt-0.5 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                                    required
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mese</label>
                                <select
                                    v-model.number="formInteressi.mese"
                                    class="mt-0.5 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                                    required
                                >
                                    <option v-for="m in 12" :key="m" :value="m">{{ mesiLabels[m] }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button
                                type="submit"
                                :disabled="formInteressi.processing"
                                class="flex-1 inline-flex justify-center items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white rounded-md text-sm font-medium"
                            >
                                <CalculatorIcon class="size-4" />
                                Calcola
                            </button>
                            <button
                                type="button"
                                @click="showCalcolaModal = false"
                                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-md text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Annulla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
