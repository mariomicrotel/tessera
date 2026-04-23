<script setup>
import { computed, ref } from 'vue';
import {
    ArrowLeftIcon, ArrowDownTrayIcon, BanknotesIcon,
    ArrowUpTrayIcon, ArrowDownOnSquareIcon, InformationCircleIcon,
    CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    libretto:       Object,
    movimenti:      Object,
    riepilogo_anno: Object,
});

const today = new Date().toISOString().slice(0, 10);

// Form deposito
const formDeposita = useForm({
    importo: '',
    data:    today,
    desc:    '',
});

// Form prelievo
const formPreleva = useForm({
    importo:   '',
    data:      today,
    desc:      '',
    prenotato: false,
});

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmt     = (v) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v) || 0);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const fmtPct  = (v) => ((Number(v) || 0) * 100).toFixed(2).replace('.', ',') + '%';

const memberName = computed(() => {
    const m = props.libretto?.member;
    if (!m) return '—';
    if (m.tipo_persona === 'giuridica') return m.ragione_sociale ?? '—';
    return `${m.cognome ?? ''} ${m.nome ?? ''}`.trim() || '—';
});

const statusConfig = {
    attivo:  { label: 'Attivo',  cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
    sospeso: { label: 'Sospeso', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    chiuso:  { label: 'Chiuso',  cls: 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400' },
};
const badge = (s) => statusConfig[s] ?? { label: s, cls: 'bg-gray-100 text-gray-600' };

const tipoConfig = {
    deposito:          { label: 'Deposito',         cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
    prelievo:          { label: 'Prelievo',          cls: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' },
    interessi:         { label: 'Interessi',         cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
    ritenuta_fiscale:  { label: 'Ritenuta 26%',      cls: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300' },
    rettifica:         { label: 'Rettifica',         cls: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' },
};
const tipoB = (t) => tipoConfig[t] ?? { label: t, cls: 'bg-gray-100 text-gray-600' };

const isAttivo  = computed(() => props.libretto?.status === 'attivo');
const importoBig = computed(() => Number(props.formPreleva?.importo || 0) > 5000);

// Costruisce URL export con date default (anno corrente)
const exportUrl = computed(() => {
    const today = new Date();
    const dal = `${today.getFullYear()}-01-01`;
    const al  = today.toISOString().slice(0, 10);
    return route('prestito-sociale.export', props.libretto.id) + `?dal=${dal}&al=${al}`;
});
</script>

<template>
    <AppLayout :title="`Libretto ${libretto.numero_libretto}`">
        <Head :title="`Libretto ${libretto.numero_libretto}`" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Prestito Sociale
                </h2>
                <div class="flex items-center gap-3">
                    <a
                        :href="exportUrl"
                        class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <ArrowDownTrayIcon class="size-4" />
                        Estratto conto CSV
                    </a>
                    <Link
                        :href="route('prestito-sociale.index')"
                        class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 text-sm"
                    >
                        <ArrowLeftIcon class="size-4" />Torna all'elenco
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Info libretto -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ memberName }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ libretto.member?.tipo_persona === 'giuridica' ? '🏢 Persona giuridica' : '👤 Persona fisica' }}
                            </p>
                        </div>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold shrink-0"
                              :class="badge(libretto.status).cls">
                            {{ badge(libretto.status).label }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">N° Libretto</p>
                            <p class="text-sm font-mono font-semibold text-indigo-700 dark:text-indigo-400 mt-0.5">{{ libretto.numero_libretto }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Saldo attuale</p>
                            <p class="text-2xl font-bold mt-0.5"
                               :class="Number(libretto.saldo_attuale) > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-500'">
                                {{ fmt(libretto.saldo_attuale) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Tasso annuo</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ fmtPct(libretto.tasso_interesse_annuo) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Data apertura</p>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-0.5">{{ fmtDate(libretto.data_apertura) }}</p>
                        </div>
                    </div>

                    <!-- Riepilogo anno corrente -->
                    <div class="grid grid-cols-3 gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 px-3 py-2 text-center">
                            <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Interessi {{ riepilogo_anno.anno }} (lordi)</p>
                            <p class="text-sm font-bold text-blue-800 dark:text-blue-300 mt-0.5">{{ fmt(riepilogo_anno.interessi) }}</p>
                        </div>
                        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 px-3 py-2 text-center">
                            <p class="text-xs text-red-600 dark:text-red-400 font-medium">Ritenute {{ riepilogo_anno.anno }}</p>
                            <p class="text-sm font-bold text-red-800 dark:text-red-300 mt-0.5">{{ fmt(riepilogo_anno.ritenute) }}</p>
                        </div>
                        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 px-3 py-2 text-center">
                            <p class="text-xs text-green-600 dark:text-green-400 font-medium">Interessi netti {{ riepilogo_anno.anno }}</p>
                            <p class="text-sm font-bold text-green-800 dark:text-green-300 mt-0.5">{{ fmt(riepilogo_anno.interessi_netti) }}</p>
                        </div>
                    </div>

                    <!-- Note -->
                    <div v-if="libretto.note" class="rounded-lg bg-gray-50 dark:bg-gray-700/50 px-4 py-3">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Note</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ libretto.note }}</p>
                    </div>
                </div>

                <!-- Deposita e Preleva affiancati (solo se attivo) -->
                <div v-if="isAttivo" class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Form Deposita -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
                        <div class="flex items-center gap-2 mb-4">
                            <ArrowDownOnSquareIcon class="size-5 text-green-600 dark:text-green-400" />
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Deposita</h3>
                        </div>
                        <form @submit.prevent="formDeposita.post(route('prestito-sociale.deposita', libretto.id))" class="space-y-3">
                            <div>
                                <InputLabel for="dep_importo" value="Importo (€) *" />
                                <TextInput id="dep_importo" v-model="formDeposita.importo" type="number" min="0.01" step="0.01"
                                    class="mt-1 block w-full" required />
                                <InputError class="mt-1" :message="formDeposita.errors.importo" />
                            </div>
                            <div>
                                <InputLabel for="dep_data" value="Data *" />
                                <TextInput id="dep_data" v-model="formDeposita.data" type="date" class="mt-1 block w-full" required />
                                <InputError class="mt-1" :message="formDeposita.errors.data" />
                            </div>
                            <div>
                                <InputLabel for="dep_desc" value="Descrizione" />
                                <TextInput id="dep_desc" v-model="formDeposita.desc" type="text" maxlength="255"
                                    class="mt-1 block w-full" placeholder="Opzionale…" />
                            </div>
                            <PrimaryButton type="submit" :disabled="formDeposita.processing" class="!bg-green-600 hover:!bg-green-700 w-full justify-center">
                                <ArrowDownOnSquareIcon class="size-4 me-2" />
                                Registra Deposito
                            </PrimaryButton>
                        </form>
                    </div>

                    <!-- Form Preleva -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 border border-amber-200 dark:border-amber-700">
                        <div class="flex items-center gap-2 mb-4">
                            <ArrowUpTrayIcon class="size-5 text-amber-600 dark:text-amber-400" />
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Preleva</h3>
                        </div>
                        <form @submit.prevent="formPreleva.post(route('prestito-sociale.preleva', libretto.id))" class="space-y-3">
                            <div>
                                <InputLabel for="pre_importo" value="Importo (€) *" />
                                <TextInput id="pre_importo" v-model="formPreleva.importo" type="number" min="0.01" step="0.01"
                                    :max="libretto.saldo_attuale" class="mt-1 block w-full" required />
                                <p class="text-xs text-gray-400 mt-0.5">Disponibile: {{ fmt(libretto.saldo_attuale) }}</p>
                                <InputError class="mt-1" :message="formPreleva.errors.importo" />
                            </div>
                            <div>
                                <InputLabel for="pre_data" value="Data *" />
                                <TextInput id="pre_data" v-model="formPreleva.data" type="date" class="mt-1 block w-full" required />
                                <InputError class="mt-1" :message="formPreleva.errors.data" />
                            </div>
                            <div>
                                <InputLabel for="pre_desc" value="Descrizione" />
                                <TextInput id="pre_desc" v-model="formPreleva.desc" type="text" maxlength="255"
                                    class="mt-1 block w-full" placeholder="Opzionale…" />
                            </div>
                            <!-- Prenotazione per importi > 5000 -->
                            <div
                                v-if="Number(formPreleva.importo) > 5000"
                                class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-3 py-2"
                            >
                                <label class="flex items-start gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="formPreleva.prenotato" class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                                    <span class="text-xs text-amber-700 dark:text-amber-300">
                                        <strong>Prelievo superiore a €5.000:</strong> confermo che il socio ha effettuato la prenotazione con preavviso di almeno 24 ore.
                                    </span>
                                </label>
                                <InputError class="mt-1" :message="formPreleva.errors.prenotato" />
                            </div>
                            <button type="submit" :disabled="formPreleva.processing"
                                class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white rounded-md text-sm font-medium">
                                <ArrowUpTrayIcon class="size-4" />
                                Registra Prelievo
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Stato chiuso -->
                <div v-else-if="libretto.status === 'chiuso'"
                    class="rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 px-4 py-4 flex items-center gap-3">
                    <XCircleIcon class="size-6 text-gray-400 shrink-0" />
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Libretto chiuso</p>
                        <p v-if="libretto.data_chiusura" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Chiuso il {{ fmtDate(libretto.data_chiusura) }}
                        </p>
                    </div>
                </div>

                <!-- Tabella movimenti -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Movimenti</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Importo</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden sm:table-cell">Netto</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Saldo dopo</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden md:table-cell">Descrizione</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="mov in movimenti.data" :key="mov.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-2.5 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                        {{ fmtDate(mov.data_valuta) }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium" :class="tipoB(mov.tipo).cls">
                                            {{ tipoB(mov.tipo).label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-sm text-right font-mono font-medium"
                                        :class="mov.segno === 'avere' ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                        {{ mov.segno === 'avere' ? '+' : '−' }}{{ fmt(mov.importo) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-sm text-right text-gray-500 dark:text-gray-400 hidden sm:table-cell">
                                        {{ mov.importo_netto != null ? fmt(mov.importo_netto) : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-sm text-right font-semibold text-gray-800 dark:text-gray-200">
                                        {{ fmt(mov.saldo_dopo) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400 hidden md:table-cell max-w-xs truncate">
                                        {{ mov.descrizione || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p v-if="!movimenti.data?.length" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                            Nessun movimento registrato.
                        </p>
                    </div>

                    <!-- Paginazione movimenti -->
                    <div v-if="movimenti.prev_page_url || movimenti.next_page_url"
                        class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <Link v-if="movimenti.prev_page_url" :href="movimenti.prev_page_url"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline text-sm">
                            <ArrowLeftIcon class="size-4" />Indietro
                        </Link>
                        <span v-else></span>
                        <span class="text-xs text-gray-400">{{ movimenti.current_page }}/{{ movimenti.last_page }}</span>
                        <Link v-if="movimenti.next_page_url" :href="movimenti.next_page_url"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline text-sm">
                            Avanti<ArrowRightIcon class="size-4" />
                        </Link>
                        <span v-else></span>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
