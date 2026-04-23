<script setup>
import {
    PencilSquareIcon,
    TrashIcon,
    ArrowLeftIcon,
    BuildingOfficeIcon,
    DocumentTextIcon,
    CurrencyEuroIcon,
    CheckCircleIcon,
    XCircleIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    fattura:        Object,
    isReadOnly:     Boolean,
    statiLabel:     Object,
    tipiDocumento:  Object,
});

const fmt     = (n) => Number(n ?? 0).toFixed(2);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoBadgeClass = (stato) => ({
    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200':        stato === 'da_pagare',
    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':    stato === 'pagata',
    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200':stato === 'parzialmente_pagata',
    'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400':        stato === 'annullata',
});

function postAction(routeName) {
    router.post(route(routeName, props.fattura.id));
}

function destroy() {
    if (!confirm(`Eliminare la fattura "${props.fattura.numero_fattura}"? L'operazione non può essere annullata.`)) return;
    router.delete(route('iva.fatture-passive.destroy', props.fattura.id));
}
</script>

<template>
    <AppLayout :title="`Fattura ${fattura.numero_fattura}`">
        <Head :title="`Fattura passiva ${fattura.numero_fattura}`" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('iva.fatture-passive.index')"
                          class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <ArrowLeftIcon class="size-5" aria-hidden="true" />
                    </Link>
                    <DocumentTextIcon class="size-5 text-gray-500" aria-hidden="true" />
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight font-mono">
                        {{ fattura.numero_fattura }}
                    </h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                          :class="statoBadgeClass(fattura.stato_pagamento)">
                        {{ statiLabel[fattura.stato_pagamento] ?? fattura.stato_pagamento }}
                    </span>
                    <span v-if="isReadOnly"
                          class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                        Liquidata — sola lettura
                    </span>
                </div>
                <div v-if="!isReadOnly" class="flex gap-2">
                    <Link :href="route('iva.fatture-passive.edit', fattura.id)">
                        <PrimaryButton>
                            <PencilSquareIcon class="size-4 me-2" aria-hidden="true" />Modifica
                        </PrimaryButton>
                    </Link>
                    <button type="button" @click="destroy"
                        class="inline-flex items-center gap-1 px-4 py-2 border border-red-300 dark:border-red-700 rounded-md font-medium text-xs text-red-700 dark:text-red-400 uppercase tracking-widest hover:bg-red-50 dark:hover:bg-red-900/30">
                        <TrashIcon class="size-4" />Elimina
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-5xl mx-auto sm:px-6 space-y-6">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Dati testata -->
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
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ tipiDocumento[fattura.tipo_documento] ?? fattura.tipo_documento }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Data fattura</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ fmtDate(fattura.data_fattura) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Data registrazione</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ fmtDate(fattura.data_registrazione) }}</dd>
                        </div>
                        <div v-if="fattura.data_ricezione">
                            <dt class="text-gray-500 dark:text-gray-400">Data ricezione</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ fmtDate(fattura.data_ricezione) }}</dd>
                        </div>
                        <div v-if="fattura.data_scadenza">
                            <dt class="text-gray-500 dark:text-gray-400">Scadenza</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ fmtDate(fattura.data_scadenza) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Esigibilità IVA</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100 capitalize">{{ fattura.esigibilita }}</dd>
                        </div>
                        <div v-if="fattura.note" class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Note</dt>
                            <dd class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ fattura.note }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Sidebar: fornitore + totali + azioni stato -->
                <div class="space-y-4">

                    <!-- Fornitore -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 text-sm space-y-2">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                            <BuildingOfficeIcon class="size-3.5" />Fornitore
                        </h3>
                        <template v-if="fattura.supplier">
                            <Link :href="route('suppliers.show', fattura.supplier.id)"
                                  class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline block">
                                {{ fattura.supplier.ragione_sociale || fattura.supplier.name }}
                            </Link>
                            <p v-if="fattura.supplier.partita_iva" class="text-gray-500 dark:text-gray-400 font-mono text-xs">
                                P.IVA {{ fattura.supplier.partita_iva }}
                            </p>
                        </template>
                        <p v-else class="text-gray-400 italic">Fornitore non specificato</p>
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

                    <!-- Azioni stato (solo se non read-only) -->
                    <div v-if="!isReadOnly" class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 text-sm space-y-2">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                            <ClockIcon class="size-3.5" />Cambia stato
                        </h3>
                        <div class="flex flex-col gap-2">
                            <button v-if="fattura.stato_pagamento !== 'pagata' && fattura.stato_pagamento !== 'annullata'"
                                type="button" @click="postAction('iva.fatture-passive.marca-pagata')"
                                class="inline-flex items-center justify-center gap-1 px-3 py-1.5 rounded text-xs font-medium bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800 transition-colors">
                                <CheckCircleIcon class="size-4" />Marca come pagata
                            </button>
                            <button v-if="fattura.stato_pagamento !== 'parzialmente_pagata' && fattura.stato_pagamento !== 'annullata'"
                                type="button" @click="postAction('iva.fatture-passive.marca-parzialmente-pagata')"
                                class="inline-flex items-center justify-center gap-1 px-3 py-1.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900 dark:text-yellow-200 dark:hover:bg-yellow-800 transition-colors">
                                <ClockIcon class="size-4" />Pagamento parziale
                            </button>
                            <button v-if="fattura.stato_pagamento !== 'da_pagare'"
                                type="button" @click="postAction('iva.fatture-passive.reimposta-da-pagare')"
                                class="inline-flex items-center justify-center gap-1 px-3 py-1.5 rounded text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800 transition-colors">
                                <ArrowLeftIcon class="size-4" />Reimposta da pagare
                            </button>
                            <button v-if="fattura.stato_pagamento !== 'annullata'"
                                type="button" @click="postAction('iva.fatture-passive.annulla')"
                                class="inline-flex items-center justify-center gap-1 px-3 py-1.5 rounded text-xs font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 transition-colors">
                                <XCircleIcon class="size-4" />Annulla fattura
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Righe fattura -->
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
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Imponibile</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">IVA</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Totale riga</th>
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
                            <td class="px-4 py-2 text-right font-mono">€ {{ fmt(riga.imponibile) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-gray-600 dark:text-gray-400">€ {{ fmt(riga.iva) }}</td>
                            <td class="px-4 py-2 text-right font-medium font-mono">€ {{ fmt(riga.totale) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-700 font-medium">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400 uppercase">Totali</td>
                            <td class="px-4 py-2 text-right font-mono">€ {{ fmt(fattura.imponibile_totale) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-gray-600 dark:text-gray-400">€ {{ fmt(fattura.iva_totale) }}</td>
                            <td class="px-4 py-2 text-right font-mono">€ {{ fmt(fattura.totale_documento) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </AppLayout>
</template>
