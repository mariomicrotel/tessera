<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, MagnifyingGlassIcon, TableCellsIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    assets: Object,
    filters: Object,
    categorie: Array,
    statiLabel: Object,
});

const search    = ref(props.filters?.search ?? '');
const stato     = ref(props.filters?.stato ?? '');
const categoria = ref(props.filters?.categoria ?? '');

function applyFilters() {
    router.get(route('cespiti.index'), {
        search:    search.value || undefined,
        stato:     stato.value || undefined,
        categoria: categoria.value || undefined,
    }, { preserveState: true, replace: true });
}

watch([stato, categoria], applyFilters);

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});

function statoBadgeClass(stato) {
    if (stato === 'in_uso')   return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
    if (stato === 'dismesso') return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
    if (stato === 'venduto')  return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
    return 'bg-gray-100 text-gray-600';
}

function fmt(n) {
    return Number(n ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// URL per il download del PDF con i filtri correnti applicati
const pdfUrl = computed(() => {
    const params = new URLSearchParams();
    params.set('esercizio', new Date().getFullYear());
    if (stato.value)     params.set('stato', stato.value);
    if (categoria.value) params.set('categoria', categoria.value);
    return route('cespiti.registro-pdf') + '?' + params.toString();
});
</script>

<template>
    <AppLayout title="Registro Cespiti">
        <Head title="Registro Cespiti" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <TableCellsIcon class="size-6 text-gray-500" />
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Registro Cespiti</h2>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="pdfUrl" target="_blank"
                       class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <DocumentArrowDownIcon class="size-4" />
                        PDF Registro
                    </a>
                    <Link :href="route('cespiti.create')"
                          class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                        <PlusIcon class="size-4" />
                        Nuovo cespite
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Filtri -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
                            <input v-model="search" type="text" placeholder="Cerca nome, codice, matricola…"
                                   class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                        </div>
                        <select v-model="stato" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">Tutti gli stati</option>
                            <option v-for="(label, key) in statiLabel" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <select v-model="categoria" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">Tutte le categorie</option>
                            <option v-for="cat in categorie" :key="cat.id" :value="cat.id">{{ cat.codice }} — {{ cat.descrizione }}</option>
                        </select>
                    </div>
                </div>

                <!-- Tabella -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cespite</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Categoria</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Costo storico</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">VNC corrente</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Stato</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Inizio amm.</th>
                                <th class="relative px-4 py-3"><span class="sr-only">Azioni</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="assets.data.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-gray-400 dark:text-gray-500 text-sm">
                                    Nessun cespite trovato.
                                    <Link :href="route('cespiti.create')" class="ml-1 text-blue-600 hover:underline">Crea il primo</Link>.
                                </td>
                            </tr>
                            <tr v-for="asset in assets.data" :key="asset.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ asset.name }}</div>
                                    <div v-if="asset.code || asset.matricola" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ [asset.code, asset.matricola].filter(Boolean).join(' · ') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                    {{ asset.category ? `${asset.category.codice} — ${asset.category.descrizione}` : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-mono text-gray-800 dark:text-gray-200">
                                    € {{ fmt(asset.costo_storico) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-mono"
                                    :class="asset.vnc_corrente > 0 ? 'text-gray-800 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500'">
                                    € {{ fmt(asset.vnc_corrente) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                                          :class="statoBadgeClass(asset.stato)">
                                        {{ statiLabel[asset.stato] ?? asset.stato }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ asset.data_inizio_ammortamento ? asset.data_inizio_ammortamento.substring(0, 10) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('cespiti.show', asset.id)"
                                          class="text-sm text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                        Dettaglio
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Paginazione -->
                    <div v-if="assets.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ assets.from }}–{{ assets.to }} di {{ assets.total }}</span>
                        <div class="flex gap-1">
                            <Link v-for="link in assets.links" :key="link.label"
                                  :href="link.url ?? '#'"
                                  v-html="link.label"
                                  class="px-3 py-1 rounded border transition"
                                  :class="link.active
                                      ? 'bg-blue-600 text-white border-blue-600'
                                      : link.url
                                          ? 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                                          : 'border-gray-200 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-default'" />
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
