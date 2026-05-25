<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    ArrowLeftIcon, ArrowDownTrayIcon,
    CheckCircleIcon, XCircleIcon, ClockIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    bundle: { type: Object, required: true },
});

const STATUS_CONFIG = {
    pending:    { label: 'In coda',     color: 'text-blue-600 dark:text-blue-400',     icon: ClockIcon,             desc: 'Il job è in coda di esecuzione. Avvierà entro pochi secondi.' },
    processing: { label: 'In corso',    color: 'text-amber-600 dark:text-amber-400',   icon: ClockIcon,             desc: 'Generazione in corso. Tempo stimato 30s-2min in base alla quantità di dati.' },
    ready:      { label: 'Pronto',      color: 'text-green-600 dark:text-green-400',   icon: CheckCircleIcon,       desc: 'Il bundle è pronto al download.' },
    failed:     { label: 'Fallito',     color: 'text-red-600 dark:text-red-400',       icon: XCircleIcon,           desc: 'Si è verificato un errore. Puoi riprovare creando un nuovo export.' },
    cancelled:  { label: 'Annullato',   color: 'text-gray-600 dark:text-gray-400',     icon: XCircleIcon,           desc: 'Generazione annullata.' },
    expired:    { label: 'Scaduto',     color: 'text-gray-500 dark:text-gray-500',     icon: ExclamationTriangleIcon, desc: 'Il file è stato rimosso (TTL 30 giorni).' },
};

const status = computed(() => STATUS_CONFIG[props.bundle.status] ?? STATUS_CONFIG.pending);
const fmtDateTime = (s) => s ? new Date(s).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' }) : '—';

// Auto-refresh ogni 3s se in pending/processing
let pollInterval = null;
onMounted(() => {
    if (['pending', 'processing'].includes(props.bundle.status)) {
        pollInterval = setInterval(() => {
            router.reload({ only: ['bundle'], preserveScroll: true });
        }, 3000);
    }
});
onBeforeUnmount(() => {
    if (pollInterval) clearInterval(pollInterval);
});

const cancel = () => {
    if (! confirm('Annullare la generazione di questo export?')) return;
    router.post(route('consultant.exports.cancel', props.bundle.id), {});
};
</script>

<template>
    <AppLayout title="Dettaglio export">
        <Head :title="`Export ${bundle.tenant?.name ?? '—'}`" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('consultant.exports.index')"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
                            <ArrowLeftIcon class="size-3" /> Export
                        </Link>
                    </div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ bundle.tenant?.name ?? '—' }} — {{ bundle.period_label }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Status card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 text-center">
                    <component :is="status.icon"
                        :class="['size-12 mx-auto mb-3', status.color,
                            (['pending','processing'].includes(bundle.status)) ? 'animate-pulse' : '']" />
                    <h3 :class="['text-lg font-bold mb-1', status.color]">{{ status.label }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">{{ status.desc }}</p>

                    <p v-if="bundle.error_message"
                        class="mt-3 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded p-2 font-mono">
                        {{ bundle.error_message }}
                    </p>

                    <!-- Download button -->
                    <div v-if="bundle.is_downloadable && bundle.download_url" class="mt-5">
                        <a :href="bundle.download_url" target="_blank">
                            <PrimaryButton class="text-sm">
                                <ArrowDownTrayIcon class="size-4 me-1.5" />
                                Scarica ZIP ({{ bundle.file_size_human ?? '?' }})
                            </PrimaryButton>
                        </a>
                        <p v-if="bundle.download_count > 0" class="text-xs text-gray-400 mt-2">
                            Già scaricato {{ bundle.download_count }} {{ bundle.download_count === 1 ? 'volta' : 'volte' }}
                        </p>
                    </div>

                    <!-- Cancel button -->
                    <div v-if="['pending','processing'].includes(bundle.status)" class="mt-5">
                        <button @click="cancel" class="text-xs text-amber-600 hover:text-amber-800 dark:text-amber-400 underline">
                            Annulla generazione
                        </button>
                    </div>
                </div>

                <!-- Metadati -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Dettagli</h3>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Bundle ID</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-mono text-xs break-all">{{ bundle.id }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Ente</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ bundle.tenant?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Periodo</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ bundle.period_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Dimensione</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ bundle.file_size_human ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Richiesto</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ fmtDateTime(bundle.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Completato</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ fmtDateTime(bundle.completed_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Scade il</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ fmtDateTime(bundle.expires_at) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Formati:</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="f in bundle.formats" :key="f"
                                class="text-xs font-mono bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 px-2 py-0.5 rounded">
                                {{ f }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Tabelle ({{ bundle.data_types.length }}):</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="d in bundle.data_types" :key="d"
                                class="text-xs font-mono bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">
                                {{ d }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
