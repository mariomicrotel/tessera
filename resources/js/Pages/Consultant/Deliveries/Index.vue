<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    ArrowLeftIcon, PlusIcon, DocumentArrowUpIcon,
    CheckCircleIcon, ClockIcon, ExclamationCircleIcon, EyeIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    entity:     { type: Object, required: true },
    deliveries: { type: Object, required: true },
});

const STATE_ICON = {
    bozza:       ClockIcon,
    consegnato:  DocumentArrowUpIcon,
    letto:       EyeIcon,
    accettato:   CheckCircleIcon,
    contestato:  ExclamationCircleIcon,
};
const STATE_COLOR = {
    gray:  'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    blue:  'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    cyan:  'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    red:   'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
};

const fmtDateTime = (s) => s ? new Date(s).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' }) : '—';
</script>

<template>
    <AppLayout :title="`Consegne — ${entity.name}`">
        <Head :title="`Consegne — ${entity.name}`" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('consultant.entities.show', entity.slug)"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
                            <ArrowLeftIcon class="size-3" /> {{ entity.name }}
                        </Link>
                    </div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Consegne
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Documenti che hai consegnato all'ente (F24, bilanci, comunicazioni).
                    </p>
                </div>
                <Link :href="route('consultant.deliveries.create', entity.slug)">
                    <PrimaryButton class="text-sm">
                        <PlusIcon class="size-4 me-1.5" /> Nuova consegna
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <div v-if="deliveries.data.length === 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <DocumentArrowUpIcon class="size-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Nessuna consegna effettuata.
                    </p>
                    <Link :href="route('consultant.deliveries.create', entity.slug)">
                        <PrimaryButton class="text-sm">
                            <PlusIcon class="size-4 me-1.5" /> Crea la prima
                        </PrimaryButton>
                    </Link>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr class="text-left text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="px-5 py-3">Titolo</th>
                                <th class="px-5 py-3">Tipo</th>
                                <th class="px-5 py-3">Stato</th>
                                <th class="px-5 py-3">File</th>
                                <th class="px-5 py-3">Consegnato</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                            <tr v-for="d in deliveries.data" :key="d.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                <td class="px-5 py-3 text-sm">
                                    <Link :href="route('consultant.deliveries.show', [entity.slug, d.id])"
                                        class="font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400">
                                        {{ d.titolo }}
                                    </Link>
                                    <p v-if="d.feedback_note" class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1 mt-0.5">
                                        Feedback: {{ d.feedback_note }}
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-xs">
                                    <span class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                        {{ d.tipo_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium',
                                        STATE_COLOR[d.stato_badge_color]]">
                                        <component :is="STATE_ICON[d.stato]" class="size-3" />
                                        {{ d.stato_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ d.documents_count }}
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ fmtDateTime(d.data_consegna) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
