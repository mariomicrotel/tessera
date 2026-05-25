<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ClipboardDocumentCheckIcon, ClockIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';

defineProps({
    richieste: { type: Object, required: true },
});

const BADGE_PRIORITA = {
    urgente: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    alta:    'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
    normale: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    bassa:   'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};
const STATO_LABEL = {
    aperta: 'Aperta',
    in_attesa_risposta: 'In attesa risposta',
    risposta_ricevuta: 'Risposta inviata',
};
const isScaduta = (s) => s && new Date(s) < new Date();
const fmt = (s) => s ? new Date(s).toLocaleDateString('it-IT') : '—';
</script>

<template>
    <AppLayout title="Richieste consulente">
        <Head title="Richieste dal consulente" />

        <template #header>
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Richieste dal consulente
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Documenti che il commercialista ti ha richiesto. Carica i file richiesti per soddisfare la richiesta.
                </p>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <div v-if="richieste.data.length === 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <ClipboardDocumentCheckIcon class="size-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Nessuna richiesta pendente dal consulente.
                    </p>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="r in richieste.data" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                            <Link :href="route('tenant.consultant-inbox.requests.show', [$page.props.currentTenant?.slug, r.id])"
                                class="block px-5 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span :class="['text-xs font-medium px-1.5 py-0.5 rounded', BADGE_PRIORITA[r.priorita] || BADGE_PRIORITA.normale]">
                                                {{ r.priorita?.toUpperCase() }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ STATO_LABEL[r.stato] ?? r.stato }}
                                            </span>
                                            <span v-if="r.documenti_count > 0" class="text-xs text-gray-400">
                                                · {{ r.documenti_count }} file
                                            </span>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ r.titolo }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Da {{ r.consulente }} · {{ fmt(r.created_at) }}
                                        </p>
                                    </div>
                                    <div v-if="r.data_scadenza" class="flex-shrink-0 text-right">
                                        <p :class="['text-xs flex items-center gap-1 justify-end',
                                            isScaduta(r.data_scadenza) ? 'text-red-500' : 'text-gray-400']">
                                            <ExclamationCircleIcon v-if="isScaduta(r.data_scadenza)" class="size-3.5" />
                                            <ClockIcon v-else class="size-3.5" />
                                            {{ fmt(r.data_scadenza) }}
                                        </p>
                                    </div>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
