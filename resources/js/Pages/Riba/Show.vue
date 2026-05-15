<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    riba:       Object,
    statiLabel: Object,
});

const fmt     = (v) => Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2 });
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoBadge = (stato) => ({
    'da_inviare': 'bg-yellow-100 text-yellow-800',
    'inviata':    'bg-blue-100 text-blue-800',
    'accettata':  'bg-indigo-100 text-indigo-800',
    'pagata':     'bg-green-100 text-green-800',
    'insoluta':   'bg-red-100 text-red-800',
    'annullata':  'bg-gray-100 text-gray-600',
}[stato] ?? 'bg-gray-100 text-gray-600');

const markAction = (action) => {
    router.post(route(`riba.${action}`, props.riba), {}, { preserveScroll: true });
};

const destroy = () => {
    if (confirm('Eliminare questa RI.BA?')) {
        router.delete(route('riba.destroy', props.riba));
    }
};
</script>

<template>
    <AppLayout :title="`RI.BA — ${riba.numero_riba ?? riba.id}`">
        <Head :title="`RI.BA ${riba.numero_riba ?? riba.id}`" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Dettaglio RI.BA
                </h2>
                <Link :href="route('riba.index')">
                    <SecondaryButton>← Torna all'elenco</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200">
                            {{ riba.nome_debitore }}
                        </h3>
                        <span :class="['px-3 py-1 rounded-full text-sm font-medium', statoBadge(riba.stato)]">
                            {{ statiLabel[riba.stato] ?? riba.stato }}
                        </span>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">N° RI.BA</dt>
                            <dd class="font-mono font-medium">{{ riba.numero_riba ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Importo</dt>
                            <dd class="font-bold text-lg text-green-700">€ {{ fmt(riba.importo) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Data emissione</dt>
                            <dd>{{ fmtDate(riba.data_emissione) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Data scadenza</dt>
                            <dd class="font-semibold">{{ fmtDate(riba.data_scadenza) }}</dd>
                        </div>
                        <div v-if="riba.cf_piva_debitore">
                            <dt class="text-gray-500 dark:text-gray-400">CF / P.IVA</dt>
                            <dd class="font-mono">{{ riba.cf_piva_debitore }}</dd>
                        </div>
                        <div v-if="riba.iban_debitore">
                            <dt class="text-gray-500 dark:text-gray-400">IBAN debitore</dt>
                            <dd class="font-mono text-xs">{{ riba.iban_debitore }}</dd>
                        </div>
                        <div v-if="riba.banca_presentatrice">
                            <dt class="text-gray-500 dark:text-gray-400">Banca presentatrice</dt>
                            <dd>{{ riba.banca_presentatrice }}</dd>
                        </div>
                        <div v-if="riba.data_invio_banca">
                            <dt class="text-gray-500 dark:text-gray-400">Data invio banca</dt>
                            <dd>{{ fmtDate(riba.data_invio_banca) }}</dd>
                        </div>
                        <div v-if="riba.fattura_attiva" class="col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Fattura collegata</dt>
                            <dd>
                                <Link :href="route('iva.fatture-attive.show', riba.fattura_attiva)"
                                      class="text-indigo-600 hover:underline">
                                    {{ riba.fattura_attiva.numero_fattura }}
                                </Link>
                            </dd>
                        </div>
                        <div v-if="riba.note" class="col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Note</dt>
                            <dd class="text-gray-700 dark:text-gray-300">{{ riba.note }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Azioni -->
                <div class="flex flex-wrap gap-3">
                    <PrimaryButton v-if="riba.stato === 'da_inviare'" @click="markAction('inviata')">
                        Segna Inviata
                    </PrimaryButton>
                    <PrimaryButton v-if="['inviata','accettata'].includes(riba.stato)"
                                   class="!bg-green-600 hover:!bg-green-700" @click="markAction('pagata')">
                        Segna Pagata
                    </PrimaryButton>
                    <SecondaryButton v-if="['inviata','accettata'].includes(riba.stato)"
                                     class="!text-red-600" @click="markAction('insoluta')">
                        Segna Insoluta
                    </SecondaryButton>
                    <SecondaryButton class="!text-red-700 ml-auto" @click="destroy">
                        Elimina
                    </SecondaryButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
