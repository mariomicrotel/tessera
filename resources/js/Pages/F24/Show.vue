<script setup>
import {
    DocumentTextIcon,
    PencilSquareIcon,
    TrashIcon,
    ArrowDownTrayIcon,
    CheckCircleIcon,
    ArrowLeftIcon,
    BanknotesIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
    modello: Object,
    sezioni: Object,
});

const fmt     = (n) => Number(n ?? 0).toFixed(2);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const mesiLabel = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                   'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

const periodoLabel = () => props.modello.mese
    ? `${mesiLabel[props.modello.mese]} ${props.modello.anno}`
    : `Anno ${props.modello.anno}`;

const isVersato  = () => props.modello.stato === 'versato';
const isBozza    = () => props.modello.stato === 'bozza';

const statoBadgeClass = {
    bozza:     'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    compilato: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    versato:   'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
};

const statiLabel = { bozza: 'Bozza', compilato: 'Compilato', versato: 'Versato' };

/* ── Modal: Segna versato ────────────────────────────────────────────────── */
const showVersaModal = ref(false);
const versaForm = useForm({ data_versamento: new Date().toISOString().substring(0, 10) });
const segnaVersato = () => {
    versaForm.post(route('f24.versa', props.modello.id), {
        onSuccess: () => { showVersaModal.value = false; },
    });
};

/* ── Modal: Elimina ─────────────────────────────────────────────────────── */
const showDeleteModal = ref(false);
const deleteForm = useForm({});
const elimina = () => {
    deleteForm.delete(route('f24.destroy', props.modello.id), {
        onFinish: () => { showDeleteModal.value = false; },
    });
};

/* ── Righe per sezione ───────────────────────────────────────────────────── */
const righePerSezione = (codSezione) =>
    (props.modello.righe ?? []).filter(r => r.sezione === codSezione);
</script>

<template>
    <AppLayout :title="`F24 — ${periodoLabel()}`">
        <Head :title="`F24 ${periodoLabel()}`" />
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <DocumentTextIcon class="size-5 text-gray-500" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        F24 — {{ periodoLabel() }}
                    </h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
                        :class="statoBadgeClass[modello.stato]">
                        {{ statiLabel[modello.stato] ?? modello.stato }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('f24.index')"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ArrowLeftIcon class="size-4" />Elenco
                    </Link>
                    <a :href="route('f24.pdf', modello.id)" target="_blank"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ArrowDownTrayIcon class="size-4" />PDF
                    </a>
                    <a :href="route('f24.xml', modello.id)"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ArrowDownTrayIcon class="size-4" />XML
                    </a>
                    <Link v-if="!isVersato()" :href="route('f24.edit', modello.id)"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <PencilSquareIcon class="size-4" />Modifica
                    </Link>
                    <button v-if="!isVersato()" @click="showVersaModal = true"
                        class="inline-flex items-center gap-1 px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md font-medium text-xs uppercase tracking-widest">
                        <CheckCircleIcon class="size-4" />Segna versato
                    </button>
                    <button v-if="!isVersato()" @click="showDeleteModal = true"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-red-300 rounded-md font-medium text-xs text-red-700 dark:text-red-400 uppercase tracking-widest hover:bg-red-50 dark:hover:bg-red-900/20">
                        <TrashIcon class="size-4" />Elimina
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-5xl mx-auto sm:px-6 space-y-6">

            <!-- Metadati -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Periodo</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ periodoLabel() }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Data compilazione</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ fmtDate(modello.data_compilazione) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Data versamento</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ fmtDate(modello.data_versamento) }}</p>
                </div>
                <div v-if="modello.liquidazione_iva">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Liquidazione IVA collegata</p>
                    <p class="text-indigo-600 dark:text-indigo-400 text-xs">{{ modello.liquidazione_iva.periodo_label }}</p>
                </div>
                <div v-if="modello.versamento_ritenuta">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Versamento ritenute collegato</p>
                    <p class="text-indigo-600 dark:text-indigo-400 text-xs">
                        {{ modello.versamento_ritenuta.mese_riferimento }}/{{ modello.versamento_ritenuta.anno_riferimento }}
                    </p>
                </div>
            </div>

            <!-- Sezioni con righe -->
            <template v-for="(labelSezione, codSezione) in sezioni" :key="codSezione">
                <div v-if="righePerSezione(codSezione).length"
                     class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-3 bg-indigo-700 text-white text-xs font-bold uppercase tracking-wider">
                        Sezione {{ labelSezione }}
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Codice tributo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Descrizione</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Rateaz.</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Anno rif.</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Importo debiti</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Importo crediti</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="riga in righePerSezione(codSezione)" :key="riga.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 font-mono font-bold text-gray-900 dark:text-gray-100">{{ riga.codice_tributo }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ riga.descrizione || '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ riga.rateazione || '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">{{ riga.anno_riferimento || '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">
                                    {{ riga.importo_debito > 0 ? `€ ${fmt(riga.importo_debito)}` : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-green-700 dark:text-green-400">
                                    {{ riga.importo_credito > 0 ? `€ ${fmt(riga.importo_credito)}` : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <p v-if="!modello.righe?.length" class="py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                Nessuna riga inserita.
            </p>

            <!-- Riquadro saldi -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="grid grid-cols-3 gap-6 text-center">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Totale debiti</p>
                        <p class="text-2xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(modello.totale_debiti) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Totale crediti</p>
                        <p class="text-2xl font-bold font-mono text-green-700 dark:text-green-400">€ {{ fmt(modello.totale_crediti) }}</p>
                    </div>
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Saldo da versare</p>
                        <p class="text-2xl font-bold font-mono"
                           :class="modello.saldo > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400'">
                            € {{ fmt(Math.max(0, modello.saldo)) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Note -->
            <div v-if="modello.note" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-sm">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Note</p>
                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ modello.note }}</p>
            </div>

        </div>

        <!-- ── Modal: Segna versato ───────────────────────────────────────── -->
        <div v-if="showVersaModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                    <CheckCircleIcon class="size-5 text-green-600" />Segna come versato
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Conferma la data di effettivo versamento dell'F24.
                </p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Data versamento <span class="text-red-500">*</span>
                    </label>
                    <input v-model="versaForm.data_versamento" type="date" required
                        class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showVersaModal = false">Annulla</SecondaryButton>
                    <PrimaryButton :disabled="versaForm.processing" @click="segnaVersato">
                        {{ versaForm.processing ? 'Salvataggio…' : 'Conferma' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>

        <!-- ── Modal: Elimina ────────────────────────────────────────────── -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Conferma eliminazione</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    Sei sicuro di voler eliminare questo F24? L'operazione non è reversibile.
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="showDeleteModal = false">Annulla</SecondaryButton>
                    <DangerButton :disabled="deleteForm.processing" @click="elimina">
                        {{ deleteForm.processing ? 'Eliminazione…' : 'Elimina F24' }}
                    </DangerButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
