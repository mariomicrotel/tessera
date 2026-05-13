<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {
    PlusIcon,
    FunnelIcon,
    PencilSquareIcon,
    TrashIcon,
    XMarkIcon,
    Cog6ToothIcon,
    ArrowsRightLeftIcon,
    ArrowUpCircleIcon,
    ArrowDownCircleIcon,
    TagIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    movimenti:      { type: Object, required: true },
    filters:        { type: Object, default: () => ({}) },
    totals:         { type: Object, default: () => ({}) },
    conti:          { type: Array,  default: () => [] },
    saldi_am:       { type: Object, default: () => ({}) },
    categories:     { type: Array,  default: () => [] },
    all_categories: { type: Array,  default: () => [] },
});

/* ── Filtri ──────────────────────────────────────────────────────────── */
const form = reactive({
    from:        props.filters.from ?? '',
    to:          props.filters.to   ?? '',
    tipo:        props.filters.tipo ?? '',
    category_id: props.filters.category_id ?? '',
    conto_id:    props.filters.conto_id    ?? '',
    search:      props.filters.search      ?? '',
});

let searchTimer = null;
function applyFilters() {
    const params = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    router.get(route('movimenti-amministrativi.index'), params, { preserveState: true, replace: true });
}
function resetFilters() {
    Object.keys(form).forEach(k => form[k] = '');
    applyFilters();
}
watch(() => form.search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

/* ── Delete ──────────────────────────────────────────────────────────── */
const deleteId = ref(null);
const confirmDelete = (id) => { deleteId.value = id; };
const doDelete = () => {
    router.delete(route('movimenti-amministrativi.destroy', deleteId.value), {
        onFinish: () => { deleteId.value = null; },
    });
};

/* ── Gestione Categorie (modal) ──────────────────────────────────────── */
const showCatModal = ref(false);
const editingCat = ref(null);
const catForm = useForm({ nome: '', tipo: 'qualsiasi', colore: '#3B82F6', icona: '' });

const openNewCat = () => {
    editingCat.value = null;
    catForm.reset();
    catForm.colore = '#3B82F6';
    catForm.tipo = 'qualsiasi';
    showCatModal.value = true;
};
const openEditCat = (cat) => {
    editingCat.value = cat;
    catForm.nome   = cat.nome;
    catForm.tipo   = cat.tipo;
    catForm.colore = cat.colore ?? '#3B82F6';
    catForm.icona  = cat.icona ?? '';
    showCatModal.value = true;
};
const submitCat = () => {
    if (editingCat.value) {
        catForm.put(route('movimenti-amministrativi.categories.update', editingCat.value.id), {
            onSuccess: () => { showCatModal.value = false; catForm.reset(); },
        });
    } else {
        catForm.post(route('movimenti-amministrativi.categories.store'), {
            onSuccess: () => { showCatModal.value = false; catForm.reset(); },
        });
    }
};
const destroyCategory = (cat) => {
    if (!confirm(`Eliminare la categoria "${cat.nome}"?`)) return;
    router.delete(route('movimenti-amministrativi.categories.destroy', cat.id), { preserveScroll: true });
};

/* ── Helpers UI ──────────────────────────────────────────────────────── */
const badgeClass = (color) => ({
    green: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    red:   'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    blue:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    gray:  'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
})[color] ?? 'bg-gray-100 text-gray-600';

const fmt = (v) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(v);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const liquiditaTotale = computed(() =>
    props.conti
        .filter(c => c.type === 'cassa' || c.type === 'banca')
        .reduce((sum, c) => sum + (props.saldi_am[c.id] ?? 0), 0)
);

const hasFilters = computed(() => Object.values(form).some(v => v !== ''));
</script>

<template>
    <AppLayout title="Movimenti Amministrativi">
        <Head title="Movimenti Amministrativi" />
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Movimenti Amministrativi
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Amministrazione semplificata — entrate, uscite, giroconti
                    </p>
                </div>
                <div class="flex gap-2">
                    <button @click="showCatModal = true; editingCat = null; catForm.reset();"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                        <TagIcon class="size-4" />Categorie
                    </button>
                    <Link :href="route('movimenti-amministrativi.create')">
                        <PrimaryButton>
                            <PlusIcon class="size-4 me-1.5" />Nuovo movimento
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">

                <!-- KPI totali + saldi conti -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <!-- Entrate periodo -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <ArrowUpCircleIcon class="size-5 text-green-500" />
                            <span class="text-xs text-gray-500 dark:text-gray-400">Entrate periodo</span>
                        </div>
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ fmt(totals.entrate) }}</p>
                    </div>
                    <!-- Uscite periodo -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <ArrowDownCircleIcon class="size-5 text-red-500" />
                            <span class="text-xs text-gray-500 dark:text-gray-400">Uscite periodo</span>
                        </div>
                        <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ fmt(totals.uscite) }}</p>
                    </div>
                    <!-- Saldo periodo -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Saldo periodo</p>
                        <p class="text-xl font-bold" :class="totals.saldo >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                            {{ fmt(totals.saldo) }}
                        </p>
                    </div>
                    <!-- Liquidità totale -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Liquidità totale</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ fmt(liquiditaTotale) }}</p>
                        <div class="mt-1 space-y-0.5">
                            <div v-for="c in conti.filter(c => c.type === 'cassa' || c.type === 'banca')" :key="c.id"
                                class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span class="truncate">{{ c.name }}</span>
                                <span :class="(saldi_am[c.id] ?? 0) < 0 ? 'text-red-500' : ''">
                                    {{ fmt(saldi_am[c.id] ?? 0) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtri -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Dal</label>
                            <input v-model="form.from" type="date" @change="applyFilters"
                                class="border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-1.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Al</label>
                            <input v-model="form.to" type="date" @change="applyFilters"
                                class="border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-1.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Tipo</label>
                            <select v-model="form.tipo" @change="applyFilters"
                                class="border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-1.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Tutti</option>
                                <option value="entrata">Entrate</option>
                                <option value="uscita">Uscite</option>
                                <option value="giroconto">Giroconti</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Categoria</label>
                            <select v-model="form.category_id" @change="applyFilters"
                                class="border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-1.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Tutte</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.icona }} {{ cat.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Conto</label>
                            <select v-model="form.conto_id" @change="applyFilters"
                                class="border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-1.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Tutti</option>
                                <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Cerca</label>
                            <input v-model="form.search" type="text" placeholder="Descrizione, riferimento..."
                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-1.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        <button v-if="hasFilters" @click="resetFilters"
                            class="flex items-center gap-1 text-xs text-gray-500 hover:text-red-600 dark:text-gray-400 pb-1 underline">
                            <XMarkIcon class="size-3.5" />Rimuovi filtri
                        </button>
                    </div>
                </div>

                <!-- Tabella movimenti -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div v-if="movimenti.data.length === 0" class="px-6 py-14 text-center">
                        <p class="text-gray-400 dark:text-gray-500 text-sm">Nessun movimento nel periodo selezionato.</p>
                        <Link :href="route('movimenti-amministrativi.create')"
                            class="mt-2 inline-flex items-center gap-1 text-sm text-blue-600 hover:underline dark:text-blue-400">
                            <PlusIcon class="size-4" />Registra il primo movimento
                        </Link>
                    </div>

                    <table v-else class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                            <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                <th class="px-5 py-3 text-left">Data</th>
                                <th class="px-5 py-3 text-left">Tipo</th>
                                <th class="px-5 py-3 text-left">Descrizione</th>
                                <th class="px-5 py-3 text-left hidden md:table-cell">Categoria</th>
                                <th class="px-5 py-3 text-left hidden lg:table-cell">Conto</th>
                                <th class="px-5 py-3 text-right">Importo</th>
                                <th class="px-5 py-3 text-right">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="m in movimenti.data" :key="m.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300 text-xs whitespace-nowrap">
                                    {{ fmtDate(m.data) }}
                                </td>
                                <td class="px-5 py-3">
                                    <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', badgeClass(m.tipo_badge_color)]">
                                        {{ m.tipo_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 max-w-xs">
                                    <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ m.descrizione }}</p>
                                    <p v-if="m.riferimento" class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Rif. {{ m.riferimento }}</p>
                                </td>
                                <td class="px-5 py-3 hidden md:table-cell">
                                    <span v-if="m.category" class="inline-flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                        <span v-if="m.category.icona">{{ m.category.icona }}</span>
                                        <span :style="m.category.colore ? `color:${m.category.colore}` : ''">{{ m.category.nome }}</span>
                                    </span>
                                    <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400 hidden lg:table-cell">
                                    <span v-if="m.tipo === 'giroconto' && m.conto && m.conto_destinazione">
                                        {{ m.conto.name }} → {{ m.conto_destinazione.name }}
                                    </span>
                                    <span v-else-if="m.conto">{{ m.conto.name }}</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-5 py-3 text-right font-semibold tabular-nums whitespace-nowrap"
                                    :class="m.tipo === 'entrata' ? 'text-green-600 dark:text-green-400' : m.tipo === 'uscita' ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400'">
                                    {{ m.tipo === 'uscita' ? '−' : '' }}{{ fmt(m.importo) }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="route('movimenti-amministrativi.edit', m.id)"
                                            class="p-1.5 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                            <PencilSquareIcon class="size-4" />
                                        </Link>
                                        <button @click="confirmDelete(m.id)"
                                            class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                            <TrashIcon class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <!-- Totali piè di pagina -->
                        <tfoot class="border-t-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/30">
                            <tr>
                                <td colspan="5" class="px-5 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">
                                    Totale periodo
                                </td>
                                <td class="px-5 py-2 text-right text-sm font-bold tabular-nums"
                                    :class="totals.saldo >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    {{ fmt(totals.saldo) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                    <!-- Paginazione -->
                    <div v-if="movimenti.last_page > 1"
                        class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ movimenti.from }}–{{ movimenti.to }} di {{ movimenti.total }}</span>
                        <div class="flex gap-2">
                            <Link v-if="movimenti.prev_page_url" :href="movimenti.prev_page_url"
                                class="px-3 py-1 border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">← Prec</Link>
                            <Link v-if="movimenti.next_page_url" :href="movimenti.next_page_url"
                                class="px-3 py-1 border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700">Succ →</Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ═══ Modal conferma elimina ═══════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="deleteId" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="deleteId = null">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-sm w-full p-6 text-center">
                    <p class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Eliminare questo movimento?</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">L'operazione è irreversibile.</p>
                    <div class="flex justify-center gap-3">
                        <button @click="deleteId = null"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            Annulla
                        </button>
                        <button @click="doDelete"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg">
                            Elimina
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ═══ Modal gestione categorie ════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showCatModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCatModal = false">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Gestione categorie</h3>
                        <button @click="showCatModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <XMarkIcon class="size-5" />
                        </button>
                    </div>

                    <div class="px-6 py-4 space-y-6">
                        <!-- Form nuova/modifica categoria -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                {{ editingCat ? 'Modifica categoria' : 'Nuova categoria' }}
                            </h4>
                            <form @submit.prevent="submitCat" class="space-y-3">
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="col-span-2">
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Nome *</label>
                                        <input v-model="catForm.nome" type="text" required placeholder="Es. Quote associative"
                                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-1.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500" />
                                        <p v-if="catForm.errors.nome" class="text-xs text-red-600 mt-0.5">{{ catForm.errors.nome }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Tipo *</label>
                                        <select v-model="catForm.tipo"
                                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-1.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="entrata">Entrata</option>
                                            <option value="uscita">Uscita</option>
                                            <option value="qualsiasi">Entrata/Uscita</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Colore</label>
                                        <div class="flex items-center gap-2">
                                            <input v-model="catForm.colore" type="color"
                                                class="h-8 w-12 rounded cursor-pointer border border-gray-300" />
                                            <input v-model="catForm.colore" type="text" placeholder="#3B82F6"
                                                class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-1.5 dark:bg-gray-700 dark:text-gray-200" />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Icona (emoji)</label>
                                        <input v-model="catForm.icona" type="text" placeholder="💰"
                                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg text-sm px-3 py-1.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500" />
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <button v-if="editingCat" type="button" @click="editingCat = null; catForm.reset();"
                                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400">
                                        Annulla modifica
                                    </button>
                                    <div v-else></div>
                                    <button type="submit" :disabled="catForm.processing"
                                        class="px-4 py-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-50">
                                        {{ catForm.processing ? 'Salvataggio...' : editingCat ? 'Salva modifiche' : 'Crea categoria' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Lista categorie esistenti -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Categorie esistenti</h4>
                            <div v-if="all_categories.length === 0" class="text-sm text-gray-400 dark:text-gray-500 py-4 text-center">
                                Nessuna categoria ancora
                            </div>
                            <ul v-else class="space-y-1.5">
                                <li v-for="cat in all_categories" :key="cat.id"
                                    class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                    <span class="text-lg w-6 text-center">{{ cat.icona || '📁' }}</span>
                                    <span :style="cat.colore ? `color:${cat.colore}` : ''"
                                        class="flex-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ cat.nome }}
                                    </span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500 capitalize">{{ cat.tipo.replace('qualsiasi', 'E/U') }}</span>
                                    <span :class="['text-xs px-1.5 py-0.5 rounded', cat.attiva ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                                        {{ cat.attiva ? 'Attiva' : 'Inattiva' }}
                                    </span>
                                    <button @click="openEditCat(cat)"
                                        class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded transition">
                                        <PencilSquareIcon class="size-3.5" />
                                    </button>
                                    <button @click="destroyCategory(cat)"
                                        class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded transition">
                                        <TrashIcon class="size-3.5" />
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
