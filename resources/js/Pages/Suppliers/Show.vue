<script setup>
import {
    PencilSquareIcon,
    TrashIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    BuildingOfficeIcon,
    EnvelopeIcon,
    PhoneIcon,
    MapPinIcon,
    CreditCardIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    supplier:        Object,
    fatturePassive:  Object,
    condizioniLabel: String,
    categoriaLabel:  String,
});

function destroy() {
    if (!confirm(`Eliminare il fornitore "${props.supplier.nome_completo}"?`)) return;
    router.delete(route('suppliers.destroy', props.supplier.id));
}
</script>

<template>
    <AppLayout :title="supplier.nome_completo">
        <Head :title="supplier.nome_completo" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('suppliers.index')" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <ArrowLeftIcon class="size-5" aria-hidden="true" />
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ supplier.nome_completo }}
                    </h2>
                    <span
                        :class="supplier.attivo
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                    >
                        {{ supplier.attivo ? 'Attivo' : 'Inattivo' }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('suppliers.edit', supplier.id)">
                        <PrimaryButton>
                            <PencilSquareIcon class="size-4 me-2" aria-hidden="true" />Modifica
                        </PrimaryButton>
                    </Link>
                    <button
                        type="button"
                        @click="destroy"
                        class="inline-flex items-center gap-1 px-4 py-2 border border-red-300 dark:border-red-700 rounded-md font-medium text-xs text-red-700 dark:text-red-400 uppercase tracking-widest hover:bg-red-50 dark:hover:bg-red-900/30"
                    >
                        <TrashIcon class="size-4" aria-hidden="true" />Elimina
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-5xl mx-auto sm:px-6 space-y-6">

            <!-- Scheda anagrafica -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Dati identificativi -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                        <BuildingOfficeIcon class="size-4" aria-hidden="true" />Dati identificativi
                    </h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Nome commerciale</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ supplier.name }}</dd>
                        </div>
                        <div v-if="supplier.ragione_sociale">
                            <dt class="text-gray-500 dark:text-gray-400">Ragione sociale</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ supplier.ragione_sociale }}</dd>
                        </div>
                        <div v-if="supplier.partita_iva">
                            <dt class="text-gray-500 dark:text-gray-400">Partita IVA</dt>
                            <dd class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ supplier.partita_iva }}</dd>
                        </div>
                        <div v-if="supplier.codice_fiscale">
                            <dt class="text-gray-500 dark:text-gray-400">Codice fiscale</dt>
                            <dd class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ supplier.codice_fiscale }}</dd>
                        </div>
                        <div v-if="supplier.codice_sdi">
                            <dt class="text-gray-500 dark:text-gray-400">Codice SDI</dt>
                            <dd class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ supplier.codice_sdi }}</dd>
                        </div>
                        <div v-if="supplier.pec">
                            <dt class="text-gray-500 dark:text-gray-400">PEC</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ supplier.pec }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Sidebar: contatti + dati commerciali -->
                <div class="space-y-4">
                    <!-- Contatti -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 space-y-3 text-sm">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                            <PhoneIcon class="size-3.5" aria-hidden="true" />Contatti
                        </h3>
                        <div v-if="supplier.email" class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <EnvelopeIcon class="size-4 text-gray-400 shrink-0" aria-hidden="true" />
                            <a :href="`mailto:${supplier.email}`" class="hover:underline break-all">{{ supplier.email }}</a>
                        </div>
                        <div v-if="supplier.phone" class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                            <PhoneIcon class="size-4 text-gray-400 shrink-0" aria-hidden="true" />
                            <a :href="`tel:${supplier.phone}`" class="hover:underline">{{ supplier.phone }}</a>
                        </div>
                        <div v-if="supplier.indirizzo_completo" class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                            <MapPinIcon class="size-4 text-gray-400 shrink-0 mt-0.5" aria-hidden="true" />
                            <span>{{ supplier.indirizzo_completo }}</span>
                        </div>
                        <p v-if="!supplier.email && !supplier.phone && !supplier.indirizzo_completo" class="text-gray-400 dark:text-gray-500 italic">Nessun contatto</p>
                    </div>

                    <!-- Dati commerciali -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 space-y-3 text-sm">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                            <CreditCardIcon class="size-3.5" aria-hidden="true" />Dati commerciali
                        </h3>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Categoria</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ categoriaLabel }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Pagamento</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ condizioniLabel }}</span>
                        </div>
                        <div v-if="supplier.iban" class="pt-1 border-t dark:border-gray-700">
                            <span class="text-gray-500 dark:text-gray-400">IBAN</span>
                            <div class="font-mono text-xs text-gray-900 dark:text-gray-100 mt-0.5 break-all">{{ supplier.iban }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Note -->
            <div v-if="supplier.note" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Note interne</h3>
                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ supplier.note }}</p>
            </div>

            <!-- Storico fatture passive -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center gap-2">
                    <DocumentTextIcon class="size-5 text-gray-400" aria-hidden="true" />
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Fatture passive</h3>
                    <span class="ml-auto text-sm text-gray-500 dark:text-gray-400">{{ fatturePassive.total }} totale</span>
                </div>

                <table v-if="fatturePassive.data?.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Numero</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Imponibile</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">IVA</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Totale</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="fp in fatturePassive.data" :key="fp.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2 font-medium">
                                <Link :href="route('iva.fatture-passive.show', fp.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ fp.numero_fattura }}
                                </Link>
                            </td>
                            <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                {{ fp.data_fattura ? new Date(fp.data_fattura).toLocaleDateString('it-IT') : '—' }}
                            </td>
                            <td class="px-4 py-2 text-right">€ {{ Number(fp.imponibile_totale).toFixed(2) }}</td>
                            <td class="px-4 py-2 text-right">€ {{ Number(fp.iva_totale).toFixed(2) }}</td>
                            <td class="px-4 py-2 text-right font-medium">€ {{ Number(fp.totale_documento).toFixed(2) }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': fp.stato_pagamento === 'pagata',
                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': fp.stato_pagamento === 'parziale',
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': fp.stato_pagamento === 'scaduta',
                                        'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300': !['pagata','parziale','scaduta'].includes(fp.stato_pagamento),
                                    }"
                                >
                                    {{ fp.stato_pagamento ?? 'da_pagare' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-else class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                    Nessuna fattura passiva registrata per questo fornitore.
                </p>

                <!-- Paginazione fatture -->
                <div v-if="fatturePassive.prev_page_url || fatturePassive.next_page_url"
                    class="px-4 py-3 border-t dark:border-gray-700 flex justify-between text-sm">
                    <Link v-if="fatturePassive.prev_page_url" :href="fatturePassive.prev_page_url"
                        class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        <ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro
                    </Link>
                    <span v-else />
                    <Link v-if="fatturePassive.next_page_url" :href="fatturePassive.next_page_url"
                        class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                        Avanti<ArrowRightIcon class="size-4" aria-hidden="true" />
                    </Link>
                    <span v-else />
                </div>
            </div>

        </div>
    </AppLayout>
</template>
