<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    DocumentArrowDownIcon,
    PlusIcon,
    ClockIcon,
    CheckCircleIcon,
    XCircleIcon,
    ExclamationTriangleIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    bundles:              { type: Object, required: true },  // paginator
    filters:              { type: Object, default: () => ({}) },
    tenants_with_bundles: { type: Array,  default: () => [] },
});

const STATUS_CONFIG = {
    pending:    { label: 'In coda',     color: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',     icon: ClockIcon },
    processing: { label: 'In corso',    color: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300', icon: ClockIcon },
    ready:      { label: 'Pronto',      color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300', icon: CheckCircleIcon },
    failed:     { label: 'Fallito',     color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',         icon: XCircleIcon },
    cancelled:  { label: 'Annullato',   color: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',        icon: XCircleIcon },
    expired:    { label: 'Scaduto',     color: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',        icon: ExclamationTriangleIcon },
};

const statusFor = (s) => STATUS_CONFIG[s] ?? { label: s, color: 'bg-gray-100 text-gray-700', icon: ClockIcon };
const fmtDate   = (s) => s ? new Date(s).toLocaleDateString('it-IT') : '—';
const fmtDateTime = (s) => s ? new Date(s).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' }) : '—';

const filter = (k, v) => {
    const params = { ...props.filters };
    if (v === null || v === '') {
        delete params[k];
    } else {
        params[k] = v;
    }
    router.get(route('consultant.exports.index'), params, { preserveState: true, preserveScroll: true });
};

const remove = (bundle) => {
    if (! confirm('Eliminare definitivamente questo export? Il file ZIP verrà rimosso.')) return;
    router.delete(route('consultant.exports.destroy', bundle.id), { preserveScroll: true });
};

const cancel = (bundle) => {
    if (! confirm('Annullare la generazione di questo export?')) return;
    router.post(route('consultant.exports.cancel', bundle.id), {}, { preserveScroll: true });
};

const hasInflight = computed(() => props.bundles.data.some(b => ['pending','processing'].includes(b.status)));

// Auto-refresh ogni 5s se ci sono export in corso
if (typeof window !== 'undefined') {
    let interval = null;
    const setupPolling = () => {
        if (hasInflight.value && !interval) {
            interval = setInterval(() => {
                router.reload({ only: ['bundles'], preserveScroll: true });
            }, 5000);
        } else if (!hasInflight.value && interval) {
            clearInterval(interval);
            interval = null;
        }
    };
    setupPolling();
}
</script>

<template>
    <AppLayout title="Export dati">
        <Head title="Export dati per il commercialista" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Export strutturati
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Bundle ZIP con i dati operativi dei tuoi enti, per periodo selezionabile.
                    </p>
                </div>
                <Link :href="route('consultant.exports.create')">
                    <PrimaryButton class="text-sm">
                        <PlusIcon class="size-4 me-1.5" />
                        Nuovo export
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Filtri -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            Ente:
                            <select
                                :value="filters.tenant_id ?? ''"
                                @change="filter('tenant_id', $event.target.value || null)"
                                class="ml-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"
                            >
                                <option value="">Tutti</option>
                                <option v-for="t in tenants_with_bundles" :key="t.id" :value="t.id">
                                    {{ t.name }}
                                </option>
                            </select>
                        </label>

                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            Stato:
                            <select
                                :value="filters.status ?? ''"
                                @change="filter('status', $event.target.value || null)"
                                class="ml-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"
                            >
                                <option value="">Tutti</option>
                                <option value="pending">In coda</option>
                                <option value="processing">In corso</option>
                                <option value="ready">Pronto</option>
                                <option value="failed">Fallito</option>
                                <option value="cancelled">Annullato</option>
                                <option value="expired">Scaduto</option>
                            </select>
                        </label>

                        <span v-if="hasInflight" class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1.5 ml-auto">
                            <ClockIcon class="size-3.5 animate-pulse" />
                            Aggiornamento automatico ogni 5s
                        </span>
                    </div>
                </div>

                <!-- Lista -->
                <div v-if="bundles.data.length === 0"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <DocumentArrowDownIcon class="size-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Nessun export presente.
                    </p>
                    <Link :href="route('consultant.exports.create')">
                        <PrimaryButton class="text-sm">
                            <PlusIcon class="size-4 me-1.5" />
                            Crea il primo export
                        </PrimaryButton>
                    </Link>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr class="text-left text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="px-5 py-3">Ente</th>
                                <th class="px-5 py-3">Periodo</th>
                                <th class="px-5 py-3">Formati / Dati</th>
                                <th class="px-5 py-3">Stato</th>
                                <th class="px-5 py-3">Dimensione</th>
                                <th class="px-5 py-3">Richiesto</th>
                                <th class="px-5 py-3 text-right">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                            <tr v-for="b in bundles.data" :key="b.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                <td class="px-5 py-3 text-sm">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ b.tenant?.name ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    {{ b.period_label }}
                                </td>
                                <td class="px-5 py-3 text-xs">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="f in b.formats" :key="f"
                                            class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 font-mono">
                                            {{ f }}
                                        </span>
                                    </div>
                                    <div class="text-gray-400 dark:text-gray-500 mt-1">
                                        {{ b.data_types.length }} tabelle
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium', statusFor(b.status).color]">
                                        <component :is="statusFor(b.status).icon" class="size-3" />
                                        {{ statusFor(b.status).label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ b.file_size_human ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ fmtDateTime(b.created_at) }}
                                </td>
                                <td class="px-5 py-3 text-sm text-right whitespace-nowrap">
                                    <Link :href="route('consultant.exports.show', b.id)"
                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 me-3">
                                        Dettaglio
                                    </Link>
                                    <button v-if="b.status === 'pending' || b.status === 'processing'"
                                        @click="cancel(b)"
                                        class="text-amber-600 hover:text-amber-800 dark:text-amber-400 me-3">
                                        Annulla
                                    </button>
                                    <button v-if="['ready','failed','cancelled','expired'].includes(b.status)"
                                        @click="remove(b)"
                                        class="text-red-600 hover:text-red-800 dark:text-red-400"
                                        title="Elimina">
                                        <TrashIcon class="size-4 inline" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginazione -->
                <div v-if="bundles.last_page > 1" class="flex justify-center gap-1">
                    <Link v-for="link in bundles.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 text-xs rounded',
                            link.active ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700',
                            !link.url ? 'opacity-40 cursor-not-allowed' : 'hover:bg-blue-50 dark:hover:bg-gray-700']"
                        v-html="link.label" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
