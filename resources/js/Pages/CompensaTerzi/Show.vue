<script setup>
import {
    UserGroupIcon,
    PencilSquareIcon,
    TrashIcon,
    BanknotesIcon,
    DocumentTextIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    ArrowLeftIcon,
    ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
    compenso: Object,
});

const fmt     = (n) => Number(n ?? 0).toFixed(2);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const isDaVersare = () => props.compenso.stato_ritenuta === 'da_versare';

const tipiLabel = {
    occasionale:   'Occasionale',
    professionale: 'Professionale',
    provvigioni:   'Provvigioni',
};

const causaliLabel = {
    A: 'A — Lavoro autonomo / occasionale',
    Q: 'Q — Provvigioni',
    R: 'R — Provvigioni plurimandatario',
    V: 'V — Provvigioni agente',
};

/* ── Elimina ─────────────────────────────────────────────────────────────── */
const confirmingDelete = ref(false);
const deleteForm = useForm({});
const elimina = () => {
    deleteForm.delete(route('compensi-terzi.destroy', props.compenso.id), {
        onFinish: () => { confirmingDelete.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="`Compenso — ${compenso.nome_percipiente}`">
        <Head :title="`Compenso ${compenso.nome_percipiente}`" />
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <UserGroupIcon class="size-5 text-gray-500" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Compenso — {{ compenso.nome_percipiente }}
                    </h2>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
                        :class="isDaVersare()
                            ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                            : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'">
                        <ExclamationCircleIcon v-if="isDaVersare()" class="size-3" />
                        <CheckCircleIcon v-else class="size-3" />
                        {{ isDaVersare() ? 'Ritenuta da versare' : 'Ritenuta versata' }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('compensi-terzi.index')"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ArrowLeftIcon class="size-4" />Elenco
                    </Link>
                    <Link v-if="isDaVersare()" :href="route('compensi-terzi.edit', compenso.id)"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <PencilSquareIcon class="size-4" />Modifica
                    </Link>
                    <button v-if="isDaVersare()" @click="confirmingDelete = true"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-red-300 rounded-md font-medium text-xs text-red-700 dark:text-red-400 uppercase tracking-widest hover:bg-red-50 dark:hover:bg-red-900/20">
                        <TrashIcon class="size-4" />Elimina
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6 space-y-6">

            <!-- ── Anagrafica ────────────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center gap-2">
                    <DocumentTextIcon class="size-4 text-gray-500" />
                    <h3 class="font-semibold text-sm text-gray-700 dark:text-gray-300 uppercase tracking-wider">Dati percipiente</h3>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Nome / Ragione sociale</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ compenso.nome_percipiente }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Codice Fiscale</p>
                        <p class="font-mono text-gray-900 dark:text-gray-100">{{ compenso.codice_fiscale }}</p>
                    </div>
                    <div v-if="compenso.partita_iva">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Partita IVA</p>
                        <p class="font-mono text-gray-900 dark:text-gray-100">{{ compenso.partita_iva }}</p>
                    </div>
                    <div v-if="compenso.indirizzo">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Indirizzo</p>
                        <p class="text-gray-900 dark:text-gray-100">{{ compenso.indirizzo }}</p>
                    </div>
                    <div v-if="compenso.member">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Membro associato</p>
                        <p class="text-indigo-600 dark:text-indigo-400">{{ compenso.member.nome }} {{ compenso.member.cognome }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Dati compenso ─────────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center gap-2">
                    <ClipboardDocumentListIcon class="size-4 text-gray-500" />
                    <h3 class="font-semibold text-sm text-gray-700 dark:text-gray-300 uppercase tracking-wider">Dettaglio compenso</h3>
                </div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Tipo rapporto</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ tipiLabel[compenso.tipo_rapporto] }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Codice causale</p>
                        <p class="text-gray-900 dark:text-gray-100">{{ causaliLabel[compenso.codice_causale] ?? compenso.codice_causale }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Anno competenza</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ compenso.anno_competenza }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Data pagamento</p>
                        <p class="text-gray-900 dark:text-gray-100">{{ fmtDate(compenso.data_pagamento) }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Causale prestazione</p>
                        <p class="text-gray-900 dark:text-gray-100">{{ compenso.causale_prestazione }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Importi ───────────────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center gap-2">
                    <BanknotesIcon class="size-4 text-gray-500" />
                    <h3 class="font-semibold text-sm text-gray-700 dark:text-gray-300 uppercase tracking-wider">Importi</h3>
                </div>
                <div class="p-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Compenso lordo</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(compenso.compenso_lordo) }}</p>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ritenuta ({{ compenso.aliquota_ritenuta }}%)</p>
                        <p class="text-xl font-bold font-mono text-red-600 dark:text-red-400">€ {{ fmt(compenso.ritenuta) }}</p>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Netto corrisposto</p>
                        <p class="text-xl font-bold font-mono text-green-700 dark:text-green-400">€ {{ fmt(compenso.compenso_netto) }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Base imponibile ritenuta</p>
                        <p class="text-xl font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(compenso.base_imponibile_ritenuta) }}</p>
                    </div>
                </div>

                <!-- Contributi / rimborsi (se presenti) -->
                <div v-if="compenso.rimborsi_spese > 0 || compenso.contributo_inps_beneficiario > 0 || compenso.contributo_inps_committente > 0"
                     class="px-6 pb-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div v-if="compenso.rimborsi_spese > 0">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Rimborsi spese</p>
                        <p class="font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(compenso.rimborsi_spese) }}</p>
                    </div>
                    <div v-if="compenso.contributo_inps_beneficiario > 0">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Contributo INPS (beneficiario)</p>
                        <p class="font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(compenso.contributo_inps_beneficiario) }}</p>
                    </div>
                    <div v-if="compenso.contributo_inps_committente > 0">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Contributo INPS (committente)</p>
                        <p class="font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(compenso.contributo_inps_committente) }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Versamento collegato ──────────────────────────────────── -->
            <div v-if="compenso.versamento" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">
                    Versamento F24 collegato
                </h3>
                <div class="flex items-center justify-between">
                    <div class="text-sm">
                        <p class="text-gray-900 dark:text-gray-100 font-medium">
                            {{ compenso.versamento.mese_riferimento }}/{{ compenso.versamento.anno_riferimento }}
                            — codice tributo {{ compenso.versamento.codice_tributo }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Versato il {{ fmtDate(compenso.versamento.data_versamento) }}
                            — totale € {{ fmt(compenso.versamento.importo_totale) }}
                        </p>
                    </div>
                    <Link :href="route('compensi-terzi.versamento.show', compenso.versamento.id)"
                          class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                        Dettaglio versamento →
                    </Link>
                </div>
            </div>

            <!-- ── Movimento contabile ───────────────────────────────────── -->
            <div v-if="compenso.movimento" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-sm">
                <p class="text-xs text-gray-500 dark:text-gray-400">Movimento contabile collegato</p>
                <p class="font-medium text-gray-900 dark:text-gray-100">
                    {{ compenso.movimento.numero_documento }} — {{ compenso.movimento.descrizione }}
                </p>
            </div>

            <!-- ── Note ──────────────────────────────────────────────────── -->
            <div v-if="compenso.note" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-sm">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Note</p>
                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ compenso.note }}</p>
            </div>

        </div>

        <!-- ── Modal conferma eliminazione ────────────────────────────────── -->
        <div v-if="confirmingDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Conferma eliminazione</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    Sei sicuro di voler eliminare il compenso di <strong>{{ compenso.nome_percipiente }}</strong>?
                    Questa operazione non è reversibile.
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="confirmingDelete = false">Annulla</SecondaryButton>
                    <DangerButton :disabled="deleteForm.processing" @click="elimina">
                        {{ deleteForm.processing ? 'Eliminazione…' : 'Elimina compenso' }}
                    </DangerButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
