<script setup>
import {
    PencilSquareIcon,
    TrashIcon,
    ArrowLeftIcon,
    DocumentTextIcon,
    CurrencyEuroIcon,
    UserIcon,
    ClockIcon,
    ArrowPathIcon,
    DocumentArrowDownIcon,
    CheckCircleIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { ref } from 'vue';

const props = defineProps({
    fattura:       Object,
    contiIncasso:  Array,
    contiCrediti:  Array,
});

const fmt     = (n) => Number(n ?? 0).toFixed(2);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

/* ── Badge helpers ───────────────────────────────────────────────────────── */
const statoBadgeClass = (stato) => ({
    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300':         stato === 'bozza',
    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200':         stato === 'emessa',
    'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200': stato === 'inviata_sdi',
    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':     stato === 'accettata',
    'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300':             stato === 'scartata',
    'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500':         stato === 'annullata',
});

const pagBadgeClass = (stato) => ({
    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': stato === 'da_incassare',
    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':     stato === 'incassata',
    'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': stato === 'parzialmente_incassata',
});

const statiLabels = {
    bozza:         'Bozza',
    emessa:        'Emessa',
    inviata_sdi:   'Inviata SDI',
    accettata:     'Accettata',
    scartata:      'Scartata',
    annullata:     'Annullata',
};

const pagLabels = {
    da_incassare:           'Da incassare',
    incassata:              'Incassata',
    parzialmente_incassata: 'Parz. incassata',
};

const tipiLabel = {
    TD01: 'Fattura',
    TD04: 'Nota di credito',
    TD07: 'Fattura semplificata',
    TD24: 'Fattura differita',
};

/* ── Modale Pagamento ────────────────────────────────────────────────────── */
const showPagModal = ref(false);
const pagForm = useForm({
    importo:          '',
    data_pagamento:   new Date().toISOString().slice(0, 10),
    conto_incasso_id: null,
    conto_crediti_id: null,
});

function openPagModal() { showPagModal.value = true; }
function closePagModal() { showPagModal.value = false; pagForm.reset(); }

function submitPagamento() {
    pagForm.post(route('iva.fatture-attive.paga', props.fattura.id), {
        onSuccess: () => closePagModal(),
    });
}

/* ── Azioni ──────────────────────────────────────────────────────────────── */
function storna() {
    if (!confirm(`Generare una nota di credito per la fattura "${props.fattura.numero_fattura}"? La fattura verrà annullata.`)) return;
    router.post(route('iva.fatture-attive.storna', props.fattura.id));
}

function destroy() {
    if (!confirm(`Eliminare definitivamente la fattura "${props.fattura.numero_fattura}"?`)) return;
    router.delete(route('iva.fatture-attive.destroy', props.fattura.id));
}

const isBozza     = () => props.fattura.stato === 'bozza';
const isDaIncassare = () => props.fattura.stato_pagamento === 'da_incassare';
const isEmessa    = () => props.fattura.stato === 'emessa';
const isAnnullata = () => props.fattura.stato === 'annullata';
const isNotaCredito = () => props.fattura.tipo_documento === 'TD04';
</script>

<template>
    <AppLayout :title="`Fattura ${fattura.numero_fattura}`">
        <Head :title="`Fattura ${fattura.numero_fattura}`" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('iva.fatture-attive.index')"
                          class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <ArrowLeftIcon class="size-5" aria-hidden="true" />
                    </Link>
                    <DocumentTextIcon class="size-5 text-gray-500" aria-hidden="true" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight font-mono">
                        {{ fattura.numero_fattura }}
                    </h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                          :class="statoBadgeClass(fattura.stato)">
                        {{ statiLabels[fattura.stato] ?? fattura.stato }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                          :class="pagBadgeClass(fattura.stato_pagamento)">
                        {{ pagLabels[fattura.stato_pagamento] ?? fattura.stato_pagamento }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <!-- PDF -->
                    <a :href="route('iva.fatture-attive.pdf', fattura.id)" target="_blank"
                       class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <DocumentArrowDownIcon class="size-4" />PDF
                    </a>
                    <!-- Modifica (solo bozza) -->
                    <Link v-if="isBozza()" :href="route('iva.fatture-attive.edit', fattura.id)">
                        <PrimaryButton>
                            <PencilSquareIcon class="size-4 me-2" aria-hidden="true" />Modifica
                        </PrimaryButton>
                    </Link>
                    <!-- Registra pagamento (emessa + da_incassare) -->
                    <button v-if="isEmessa() && isDaIncassare()"
                        type="button" @click="openPagModal"
                        class="inline-flex items-center gap-1 px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md font-medium text-xs uppercase tracking-widest transition-colors">
                        <CheckCircleIcon class="size-4" />Registra incasso
                    </button>
                    <!-- Crea Nota di Credito (emessa + da_incassare, tipo TD01) -->
                    <Link v-if="isEmessa() && isDaIncassare() && !isNotaCredito()" :href="route('iva.fatture-attive.crea-nota-credito', fattura.id)"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-orange-300 dark:border-orange-700 rounded-md font-medium text-xs text-orange-700 dark:text-orange-400 uppercase tracking-widest hover:bg-orange-50 dark:hover:bg-orange-900/30">
                        <ArrowPathIcon class="size-4" />Crea NC
                    </Link>
                    <!-- Storna (legacy - emessa + da_incassare) -->
                    <button v-if="isEmessa() && isDaIncassare() && !isNotaCredito()"
                        type="button" @click="storna"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-orange-300 dark:border-orange-700 rounded-md font-medium text-xs text-orange-700 dark:text-orange-400 uppercase tracking-widest hover:bg-orange-50 dark:hover:bg-orange-900/30">
                        <ArrowPathIcon class="size-4" />Storna
                    </button>
                    <!-- Elimina (solo bozza) -->
                    <button v-if="isBozza()"
                        type="button" @click="destroy"
                        class="inline-flex items-center gap-1 px-3 py-2 border border-red-300 dark:border-red-700 rounded-md font-medium text-xs text-red-700 dark:text-red-400 uppercase tracking-widest hover:bg-red-50 dark:hover:bg-red-900/30">
                        <TrashIcon class="size-4" />Elimina
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-5xl mx-auto sm:px-6 space-y-6">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- ── Dati documento ──────────────────────────────────── -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                        <DocumentTextIcon class="size-4" />Dati documento
                    </h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Numero fattura</dt>
                            <dd class="font-medium font-mono text-gray-900 dark:text-gray-100">{{ fattura.numero_fattura }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Tipo documento</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ tipiLabel[fattura.tipo_documento] ?? fattura.tipo_documento }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Data fattura</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ fmtDate(fattura.data_fattura) }}</dd>
                        </div>
                        <div v-if="fattura.data_scadenza">
                            <dt class="text-gray-500 dark:text-gray-400">Scadenza</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ fmtDate(fattura.data_scadenza) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Esigibilità IVA</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100 capitalize">{{ fattura.esigibilita }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Anno</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ fattura.anno }}</dd>
                        </div>
                        <div v-if="fattura.note" class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Note</dt>
                            <dd class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ fattura.note }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- ── Sidebar ────────────────────────────────────────── -->
                <div class="space-y-4">

                    <!-- Cliente -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 text-sm space-y-2">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                            <UserIcon class="size-3.5" />Cliente
                        </h3>
                        <p v-if="fattura.cliente_id" class="text-gray-700 dark:text-gray-300">
                            Cliente #{{ fattura.cliente_id }}
                        </p>
                        <p v-else class="text-gray-400 italic">Non specificato</p>
                    </div>

                    <!-- Documento collegato (se NC) -->
                    <div v-if="isNotaCredito() && fattura.fattura_collegata_id" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 shadow rounded-lg p-5 text-sm space-y-3">
                        <h3 class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider flex items-center gap-2">
                            <DocumentTextIcon class="size-3.5" />Documento collegato
                        </h3>
                        <div class="space-y-1">
                            <p class="text-blue-700 dark:text-blue-300 font-medium">
                                Storno di: <span class="font-mono">{{ fattura.fattura_collegata_id }}</span>
                            </p>
                            <p v-if="fattura.motivo_nota_credito" class="text-sm text-blue-600 dark:text-blue-400">
                                {{ fattura.motivo_nota_credito }}
                            </p>
                        </div>
                    </div>

                    <!-- Totali -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 text-sm space-y-2">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                            <CurrencyEuroIcon class="size-3.5" />Importi
                        </h3>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Imponibile</span>
                            <span class="font-mono">€ {{ fmt(fattura.imponibile_totale) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">IVA</span>
                            <span class="font-mono">€ {{ fmt(fattura.iva_totale) }}</span>
                        </div>
                        <div class="flex justify-between border-t dark:border-gray-700 pt-2 font-semibold">
                            <span>Totale</span>
                            <span class="font-mono">€ {{ fmt(fattura.totale_documento) }}</span>
                        </div>
                    </div>

                    <!-- Stato pagamento info -->
                    <div v-if="!isAnnullata()" class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 text-sm space-y-2">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                            <ClockIcon class="size-3.5" />Stato incasso
                        </h3>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                  :class="pagBadgeClass(fattura.stato_pagamento)">
                                {{ pagLabels[fattura.stato_pagamento] ?? fattura.stato_pagamento }}
                            </span>
                        </div>
                        <p v-if="isEmessa() && isDaIncassare()" class="text-xs text-gray-400">
                            Clicca "Registra incasso" per registrare il pagamento ricevuto.
                        </p>
                    </div>

                </div>
            </div>

            <!-- ── Righe fattura ───────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Righe fattura</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Descrizione</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Cod. IVA</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Qtà</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Prezzo unit.</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Sconto</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Imponibile</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">IVA</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Totale</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="riga in fattura.righe" :key="riga.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ riga.descrizione }}</td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                <span class="font-mono">{{ riga.codice_iva?.codice }}</span>
                                <span class="text-xs text-gray-400 ml-1">({{ riga.codice_iva?.percentuale }}%)</span>
                            </td>
                            <td class="px-4 py-2 text-right font-mono">{{ Number(riga.quantita).toFixed(2) }}</td>
                            <td class="px-4 py-2 text-right font-mono">€ {{ Number(riga.prezzo_unitario).toFixed(4) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500 dark:text-gray-400">
                                {{ Number(riga.sconto_percentuale) > 0 ? Number(riga.sconto_percentuale).toFixed(1) + '%' : '—' }}
                            </td>
                            <td class="px-4 py-2 text-right font-mono">€ {{ fmt(riga.imponibile) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-gray-600 dark:text-gray-400">€ {{ fmt(riga.iva) }}</td>
                            <td class="px-4 py-2 text-right font-medium font-mono">€ {{ fmt(riga.totale) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-700 font-medium">
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400 uppercase">Totali</td>
                            <td class="px-4 py-2 text-right font-mono">€ {{ fmt(fattura.imponibile_totale) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-gray-600 dark:text-gray-400">€ {{ fmt(fattura.iva_totale) }}</td>
                            <td class="px-4 py-2 text-right font-mono">€ {{ fmt(fattura.totale_documento) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

        <!-- ── Modal Registra Incasso ─────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showPagModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6 space-y-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <CheckCircleIcon class="size-5 text-green-600" />Registra incasso
                        </h3>
                        <button type="button" @click="closePagModal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <XCircleIcon class="size-5" />
                        </button>
                    </div>

                    <form @submit.prevent="submitPagamento" class="space-y-4">
                        <div>
                            <InputLabel for="pag_importo" value="Importo *" />
                            <TextInput id="pag_importo" v-model="pagForm.importo"
                                type="number" step="0.01" min="0.01"
                                :placeholder="fmt(fattura.totale_documento)"
                                class="mt-1 block w-full font-mono" required />
                            <InputError class="mt-1" :message="pagForm.errors.importo" />
                        </div>
                        <div>
                            <InputLabel for="pag_data" value="Data incasso *" />
                            <TextInput id="pag_data" v-model="pagForm.data_pagamento"
                                type="date" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="pagForm.errors.data_pagamento" />
                        </div>
                        <div>
                            <InputLabel for="pag_conto_incasso" value="Conto incasso" />
                            <select id="pag_conto_incasso" v-model="pagForm.conto_incasso_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <option :value="null">— Nessuno —</option>
                                <option v-for="c in contiIncasso" :key="c.id" :value="c.id">{{ c.codice }} – {{ c.descrizione }}</option>
                            </select>
                            <InputError class="mt-1" :message="pagForm.errors.conto_incasso_id" />
                        </div>
                        <div>
                            <InputLabel for="pag_conto_crediti" value="Conto crediti" />
                            <select id="pag_conto_crediti" v-model="pagForm.conto_crediti_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <option :value="null">— Nessuno —</option>
                                <option v-for="c in contiCrediti" :key="c.id" :value="c.id">{{ c.codice }} – {{ c.descrizione }}</option>
                            </select>
                            <InputError class="mt-1" :message="pagForm.errors.conto_crediti_id" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <PrimaryButton type="submit" :disabled="pagForm.processing">
                                <CheckCircleIcon class="size-4 me-2" />Registra
                            </PrimaryButton>
                            <button type="button" @click="closePagModal"
                                class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                                Annulla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
