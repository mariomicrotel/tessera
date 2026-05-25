<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { InboxArrowDownIcon, CheckCircleIcon, ExclamationCircleIcon, EyeIcon, ClockIcon } from '@heroicons/vue/24/outline';

defineProps({
    deliveries: { type: Object, required: true },
});

const $page = usePage();

const STATE_COLOR = {
    blue:  'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    cyan:  'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    red:   'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    gray:  'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};

const STATE_ICON = {
    consegnato: ClockIcon, letto: EyeIcon,
    accettato: CheckCircleIcon, contestato: ExclamationCircleIcon,
};

const fmt = (s) => s ? new Date(s).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' }) : '—';
</script>

<template>
    <AppLayout title="Consegne consulente">
        <Head title="Consegne dal consulente" />

        <template #header>
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Consegne dal consulente
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Documenti che il commercialista ti ha consegnato (F24, bilanci firmati, comunicazioni).
                </p>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <div v-if="deliveries.data.length === 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <InboxArrowDownIcon class="size-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Nessuna consegna ricevuta dal consulente.
                    </p>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="d in deliveries.data" :key="d.id"
                            :class="['hover:bg-gray-50 dark:hover:bg-gray-900/30', d.is_unread ? 'bg-blue-50/30 dark:bg-blue-900/10' : '']">
                            <Link :href="route('tenant.consultant-inbox.deliveries.show', [$page.props.currentTenant?.slug, d.id])"
                                class="block px-5 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span :class="['inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium', STATE_COLOR[d.stato_badge_color]]">
                                                <component :is="STATE_ICON[d.stato]" v-if="STATE_ICON[d.stato]" class="size-3" />
                                                {{ d.stato_label }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ d.tipo_label }}</span>
                                            <span v-if="d.documenti_count > 0" class="text-xs text-gray-400">
                                                · {{ d.documenti_count }} file
                                            </span>
                                            <span v-if="d.is_unread"
                                                class="text-xs font-bold text-blue-600 dark:text-blue-400">
                                                ● Nuovo
                                            </span>
                                        </div>
                                        <p :class="['text-sm', d.is_unread ? 'font-semibold' : 'font-medium', 'text-gray-900 dark:text-gray-100']">
                                            {{ d.titolo }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Da {{ d.consulente }} · {{ fmt(d.data_consegna) }}
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
