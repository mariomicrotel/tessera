<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    PlusIcon, FunnelIcon, MagnifyingGlassIcon,
    ArrowTopRightOnSquareIcon, InboxArrowDownIcon,
    PaperAirplaneIcon, DocumentTextIcon, ChevronLeftIcon,
    ChevronRightIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    protocolli:       { type: Object, required: true },
    filters:          { type: Object, default: () => ({}) },
    anni_disponibili: { type: Array, default: () => [] },
    tipi:             { type: Array, default: () => ['entrata', 'uscita'] },
    stats:            { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');
const tipo   = ref(props.filters.tipo ?? '');
const anno   = ref(props.filters.anno ?? new Date().getFullYear());

let debounceTimer = null;
function applyFilters() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(route('protocolli.index'), {
            search: search.value || undefined,
            tipo:   tipo.value   || undefined,
            anno:   anno.value,
        }, { preserveScroll: true, replace: true });
    }, 300);
}

function goPage(url) {
    if (url) router.get(url, {}, { preserveScroll: true });
}

const tipoBadge = (t) => t === 'entrata'
    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300';

const tipoLabel = (t) => t === 'entrata' ? 'Entrata' : 'Uscita';
const fmt = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const linkedLabel = (p) => {
    if (!p.linked_type) return null;
    if (p.linked_type.endsWith('Receipt')) return 'Ricevuta';
    if (p.linked_type.endsWith('Incasso')) return 'Incasso';
    if (p.linked_type.endsWith('FatturaAttiva')) return 'Fatt. attiva';
    return 'Collegato';
};
</script>

<template>
    <AppLayout title="Protocollo comunicazioni">
        <Head title="Protocollo comunicazioni" />

        <template #header>
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Protocollo comunicazioni
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Registro in entrata e uscita — anno {{ anno }}
                    </p>
                </div>
                <Link :href="route('protocolli.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    <PlusIcon class="size-4" /> Nuova voce
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- KPI bar -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Totali anno</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-0.5">{{ stats.totali ?? 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <InboxArrowDownIcon class="size-3.5 text-blue-500" /> Entrata
                        </p>
                        <p class="text-2xl font-bold text-blue-700 dark:text-blue-300 mt-0.5">{{ stats.entrata ?? 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <PaperAirplaneIcon class="size-3.5 text-emerald-500" /> Uscita
                        </p>
                        <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-0.5">{{ stats.uscita ?? 0 }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <DocumentTextIcon class="size-3.5 text-indigo-500" /> Con ricevuta
                        </p>
                        <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300 mt-0.5">{{ stats.con_ricevuta ?? 0 }}</p>
                    </div>
                </div>

                <!-- Filtri -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3">
                    <div class="flex flex-wrap gap-3 items-center">
                        <FunnelIcon class="size-4 text-gray-400 shrink-0" />
                        <!-- Anno -->
                        <select v-model="anno" @change="applyFilters()"
                            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option v-for="a in anni_disponibili" :key="a" :value="a">{{ a }}</option>
                        </select>
                        <!-- Tipo -->
                        <select v-model="tipo" @change="applyFilters()"
                            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">Tutti i tipi</option>
                            <option value="entrata">Entrata</option>
                            <option value="uscita">Uscita</option>
                        </select>
                        <!-- Ricerca libera -->
                        <div class="relative flex-1 min-w-[180px]">
                            <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
                            <input v-model="search" @input="applyFilters()" type="search"
                                placeholder="Cerca oggetto, mittente, destinatario…"
                                class="w-full pl-8 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" />
                        </div>
                    </div>
                </div>

                <!-- Tabella -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div v-if="protocolli.data?.length === 0" class="p-10 text-center text-sm text-gray-400">
                        Nessuna voce di protocollo trovata.
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">N° Protocollo</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Data</th>
                                <th class="px-4 py-3">Oggetto</th>
                                <th class="px-4 py-3">Mittente / Destinatario</th>
                                <th class="px-4 py-3 text-center">Collegato</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr v-for="p in protocolli.data" :key="p.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-900/30 group">
                                <td class="px-4 py-3 text-sm font-mono font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                    {{ p.numero_formattato }}
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium', tipoBadge(p.tipo)]">
                                        <InboxArrowDownIcon v-if="p.tipo === 'entrata'" class="size-3" />
                                        <PaperAirplaneIcon v-else class="size-3" />
                                        {{ tipoLabel(p.tipo) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    {{ fmt(p.data_registrazione) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 max-w-xs">
                                    <p class="truncate" :title="p.oggetto">{{ p.oggetto }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 max-w-xs">
                                    <p class="truncate" :title="p.tipo === 'entrata' ? p.mittente : p.destinatario">
                                        {{ p.tipo === 'entrata' ? (p.mittente || '—') : (p.destinatario || '—') }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="linkedLabel(p)"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300">
                                        <DocumentTextIcon class="size-3" />
                                        {{ linkedLabel(p) }}
                                    </span>
                                    <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('protocolli.show', p.id)"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 inline-flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        Apri <ArrowTopRightOnSquareIcon class="size-3" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Paginazione -->
                    <div v-if="protocolli.last_page > 1"
                        class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ protocolli.from }}–{{ protocolli.to }} di {{ protocolli.total }}
                        </p>
                        <div class="flex gap-1">
                            <button @click="goPage(protocolli.prev_page_url)"
                                :disabled="!protocolli.prev_page_url"
                                class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30">
                                <ChevronLeftIcon class="size-4" />
                            </button>
                            <button @click="goPage(protocolli.next_page_url)"
                                :disabled="!protocolli.next_page_url"
                                class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30">
                                <ChevronRightIcon class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
