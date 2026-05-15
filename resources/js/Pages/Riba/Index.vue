<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    PlusIcon, ArrowDownTrayIcon, FunnelIcon,
    CheckCircleIcon, ExclamationTriangleIcon, XMarkIcon,
    ChevronLeftIcon, ChevronRightIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    riba:                Object,
    totaleSelezionabile: Number,
    statiLabel:          Object,
    filters:             Object,
});

const form = reactive({
    stato: props.filters?.stato ?? '',
    dal:   props.filters?.dal   ?? '',
    al:    props.filters?.al    ?? '',
});

const filter = () => router.get(route('riba.index'), form, { preserveState: true });

const selected = ref([]);
const toggleAll = (e) => {
    selected.value = e.target.checked
        ? props.riba.data.filter(r => r.stato === 'da_inviare').map(r => r.id)
        : [];
};

const fmt    = (v) => Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2 });
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoBadge = (stato) => ({
    'da_inviare': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    'inviata':    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'accettata':  'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    'pagata':     'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'insoluta':   'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    'annullata':  'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
}[stato] ?? 'bg-gray-100 text-gray-600');

const exportCbi = () => {
    const params = selected.value.length
        ? '?' + selected.value.map(id => `ids[]=${id}`).join('&')
        : '';
    window.location.href = route('riba.export-cbi') + params;
};

const markAction = (riba, action) => {
    router.post(route(`riba.${action}`, riba), {}, { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="RI.BA">
        <Head title="RI.BA — Ricevute Bancarie" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    RI.BA — Ricevute Bancarie
                </h2>
                <div class="flex gap-2">
                    <SecondaryButton @click="exportCbi">
                        <ArrowDownTrayIcon class="size-4 mr-1" />
                        Esporta CBI (.rtr){{ selected.length ? ` (${selected.length})` : '' }}
                    </SecondaryButton>
                    <Link :href="route('riba.create')">
                        <PrimaryButton><PlusIcon class="size-4 mr-1" />Nuova RI.BA</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- KPI -->
                <div v-if="totaleSelezionabile > 0"
                     class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 rounded-lg p-3 text-sm text-blue-800 dark:text-blue-200">
                    Totale RI.BA da inviare: <strong>€ {{ fmt(totaleSelezionabile) }}</strong>
                </div>

                <!-- Filtri -->
                <form @submit.prevent="filter"
                      class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Stato</label>
                        <select v-model="form.stato"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                            <option value="">Tutti</option>
                            <option v-for="(label, key) in statiLabel" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Scadenza dal</label>
                        <input v-model="form.dal" type="date"
                               class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Al</label>
                        <input v-model="form.al" type="date"
                               class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 mr-1" />Filtra</PrimaryButton>
                </form>

                <!-- Tabella -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-3 py-3">
                                    <input type="checkbox" @change="toggleAll"
                                           class="rounded border-gray-300 dark:border-gray-700" />
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Scadenza</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Debitore</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">N° RI.BA</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Importo</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Stato</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="!riba.data.length">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                    Nessuna RI.BA trovata.
                                </td>
                            </tr>
                            <tr v-for="r in riba.data" :key="r.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-3 py-3">
                                    <input v-if="r.stato === 'da_inviare'" type="checkbox"
                                           :value="r.id" v-model="selected"
                                           class="rounded border-gray-300 dark:border-gray-700" />
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                    {{ fmtDate(r.data_scadenza) }}
                                </td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">
                                    {{ r.nome_debitore }}
                                    <div v-if="r.cf_piva_debitore" class="text-xs text-gray-400">{{ r.cf_piva_debitore }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ r.numero_riba ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">
                                    € {{ fmt(r.importo) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statoBadge(r.stato)]">
                                        {{ statiLabel[r.stato] ?? r.stato }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button v-if="r.stato === 'da_inviare'"
                                                @click="markAction(r, 'inviata')"
                                                title="Segna come inviata"
                                                class="text-blue-600 hover:text-blue-800 text-xs underline">
                                            Inviata
                                        </button>
                                        <button v-if="['inviata','accettata'].includes(r.stato)"
                                                @click="markAction(r, 'pagata')"
                                                title="Segna come pagata"
                                                class="text-green-600 hover:text-green-800 text-xs underline">
                                            Pagata
                                        </button>
                                        <button v-if="['inviata','accettata'].includes(r.stato)"
                                                @click="markAction(r, 'insoluta')"
                                                title="Segna come insoluta"
                                                class="text-red-600 hover:text-red-800 text-xs underline">
                                            Insoluta
                                        </button>
                                        <Link :href="route('riba.show', r)"
                                              class="text-indigo-600 hover:text-indigo-800 text-xs underline">
                                            Dettaglio
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Paginazione -->
                    <div v-if="riba.last_page > 1"
                         class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <span class="text-sm text-gray-500">Pagina {{ riba.current_page }} di {{ riba.last_page }}</span>
                        <div class="flex gap-1">
                            <Link v-if="riba.prev_page_url" :href="riba.prev_page_url">
                                <SecondaryButton class="py-1"><ChevronLeftIcon class="size-4" /></SecondaryButton>
                            </Link>
                            <Link v-if="riba.next_page_url" :href="riba.next_page_url">
                                <SecondaryButton class="py-1"><ChevronRightIcon class="size-4" /></SecondaryButton>
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
