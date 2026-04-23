<script setup>
import {
    PlusIcon,
    FunnelIcon,
    PencilSquareIcon,
    TrashIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    entries: Object,
    rendicontoVoci: Array,
    conti: Array,
    totals: Object,
    filters: Object,
});

const page = usePage();
const showDeleteConfirmModal  = ref(false);
const pendingDeleteId         = ref(null);
const showConfirmDestroyModal = ref(false);
const entryIdToDestroy        = ref(null);

const form = reactive({
    from:            props.filters?.from            ?? '',
    to:              props.filters?.to              ?? '',
    rendiconto_code: props.filters?.rendiconto_code ?? '',
    conto_id:        props.filters?.conto_id        ?? '',
    search:          props.filters?.search          ?? '',
    tipo:            props.filters?.tipo            ?? '',
    gestione:        props.filters?.gestione        ?? '',
});

const hasFilters = () => Object.values(form).some((v) => v !== '');

function applyFilters() {
    router.get(route('prima-nota.index'), form, { preserveState: true, replace: true });
}

function resetFilters() {
    Object.keys(form).forEach((k) => (form[k] = ''));
    applyFilters();
}

// Debounce 300 ms per la ricerca testo
let searchTimer = null;
function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

// Le select applicano subito
function onSelectChange() {
    applyFilters();
}

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.type === 'confirm_anno_precedente_required' && flash?.destroy_entry_id) {
            entryIdToDestroy.value = flash.destroy_entry_id;
            showConfirmDestroyModal.value = true;
        }
    },
    { immediate: true },
);

function requestDelete(id) {
    pendingDeleteId.value = id;
    showDeleteConfirmModal.value = true;
}
function closeDeleteConfirmModal() {
    showDeleteConfirmModal.value = false;
    pendingDeleteId.value = null;
}
function confirmDeleteProceed() {
    const id = pendingDeleteId.value;
    showDeleteConfirmModal.value = false;
    pendingDeleteId.value = null;
    if (id) router.delete(route('prima-nota.destroy', id));
}
function confirmDestroyProceed() {
    const id = entryIdToDestroy.value;
    showConfirmDestroyModal.value = false;
    entryIdToDestroy.value = null;
    if (id) router.delete(route('prima-nota.destroy', id) + '?confirm_anno_precedente=1');
}
function closeConfirmDestroyModal() {
    showConfirmDestroyModal.value = false;
    entryIdToDestroy.value = null;
}

function fmtEur(val) {
    return '€\u00a0' + Number(val).toFixed(2);
}
</script>

<template>
    <AppLayout title="Prima nota">
        <Head title="Prima nota" />
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Prima nota</h2>
                <div class="flex gap-2">
                    <Link :href="route('prima-nota.giroconto.create')">
                        <PrimaryButton type="button" class="!bg-gray-600 hover:!bg-gray-700">Giroconto</PrimaryButton>
                    </Link>
                    <Link :href="route('prima-nota.create')">
                        <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuovo movimento</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- ── Barra filtri ───────────────────────────────────────── -->
                <form
                    @submit.prevent="applyFilters"
                    class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 space-y-3"
                >
                    <!-- Riga 1: periodo + voce rendiconto -->
                    <div class="flex flex-wrap gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Da</label>
                            <input
                                v-model="form.from"
                                type="date"
                                @change="onSelectChange"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">A</label>
                            <input
                                v-model="form.to"
                                type="date"
                                @change="onSelectChange"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Voce rendiconto</label>
                            <select
                                v-model="form.rendiconto_code"
                                @change="onSelectChange"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            >
                                <option value="">Tutte</option>
                                <option v-for="v in rendicontoVoci" :key="v.code" :value="v.code">
                                    {{ v.ministerial_code ? v.ministerial_code + ' – ' + v.name : v.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Riga 2: nuovi filtri avanzati -->
                    <div class="flex flex-wrap gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Conto</label>
                            <select
                                v-model="form.conto_id"
                                @change="onSelectChange"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            >
                                <option value="">Tutti</option>
                                <option v-for="c in conti" :key="c.id" :value="c.id">
                                    {{ c.name }}{{ c.code ? ' (' + c.code + ')' : '' }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Cerca descrizione</label>
                            <input
                                v-model="form.search"
                                type="text"
                                placeholder="Testo libero…"
                                @input="onSearchInput"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm w-48"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Tipo</label>
                            <select
                                v-model="form.tipo"
                                @change="onSelectChange"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            >
                                <option value="">Tutti</option>
                                <option value="entrata">Entrate</option>
                                <option value="uscita">Uscite</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Gestione</label>
                            <select
                                v-model="form.gestione"
                                @change="onSelectChange"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            >
                                <option value="">Tutte</option>
                                <option value="istituzionale">Istituzionale</option>
                                <option value="commerciale">Commerciale</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <PrimaryButton type="submit">
                                <FunnelIcon class="size-4 me-1" aria-hidden="true" />Filtra
                            </PrimaryButton>
                            <button
                                v-if="hasFilters()"
                                type="button"
                                @click="resetFilters"
                                class="inline-flex items-center gap-1 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <XMarkIcon class="size-4" aria-hidden="true" />Azzera
                            </button>
                        </div>
                    </div>
                </form>

                <!-- ── Riepilogo totali periodo ────────────────────────────── -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-center">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Entrate</p>
                        <p class="mt-1 text-xl font-semibold text-green-600 dark:text-green-400">
                            {{ fmtEur(totals?.entrate ?? 0) }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-center">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Uscite</p>
                        <p class="mt-1 text-xl font-semibold text-red-600 dark:text-red-400">
                            {{ fmtEur(totals?.uscite ?? 0) }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-center">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Saldo periodo</p>
                        <p
                            class="mt-1 text-xl font-semibold"
                            :class="(totals?.saldo ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ fmtEur(totals?.saldo ?? 0) }}
                        </p>
                    </div>
                </div>

                <!-- ── Tabella movimenti ───────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Conto</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Voce</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Descrizione</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Gestione</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Importo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-28">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="e in entries.data"
                                :key="e.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-2 text-sm whitespace-nowrap">
                                    {{ e.date ? new Date(e.date).toLocaleDateString('it-IT') : '' }}
                                </td>
                                <td class="px-4 py-2 text-sm">{{ e.conto?.name ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm">{{ e.rendiconto_label || e.rendiconto_code || '—' }}</td>
                                <td class="px-4 py-2 text-sm">{{ e.description || '—' }}</td>
                                <td class="px-4 py-2 text-sm capitalize">{{ e.gestione || '—' }}</td>
                                <td
                                    class="px-4 py-2 text-sm text-right font-medium whitespace-nowrap"
                                    :class="Number(e.amount) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                >
                                    {{ fmtEur(e.amount) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex gap-2 justify-end">
                                        <Link
                                            :href="route('prima-nota.edit', e.id)"
                                            class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                                        >
                                            <PencilSquareIcon class="size-4" aria-hidden="true" />Modifica
                                        </Link>
                                        <button
                                            type="button"
                                            @click="requestDelete(e.id)"
                                            class="inline-flex items-center gap-1 text-sm text-red-600 dark:text-red-400 hover:underline"
                                        >
                                            <TrashIcon class="size-4" aria-hidden="true" />Elimina
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!entries.data?.length" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                        Nessun movimento trovato.
                    </p>

                    <!-- Paginazione -->
                    <div
                        v-if="entries.prev_page_url || entries.next_page_url"
                        class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center"
                    >
                        <Link
                            v-if="entries.prev_page_url"
                            :href="entries.prev_page_url"
                            class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            <ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro
                        </Link>
                        <span v-else></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Pagina {{ entries.current_page }} di {{ entries.last_page }}
                            ({{ entries.total }} movimenti)
                        </span>
                        <Link
                            v-if="entries.next_page_url"
                            :href="entries.next_page_url"
                            class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            Avanti<ArrowRightIcon class="size-4" aria-hidden="true" />
                        </Link>
                        <span v-else></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Modali conferma ─────────────────────────────────────────── -->
        <Teleport to="body">
            <!-- Elimina singolo movimento -->
            <div v-if="showDeleteConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="delete-confirm-title"
                >
                    <h3 id="delete-confirm-title" class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Eliminare il movimento?
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Questa azione non può essere annullata.</p>
                    <div class="mt-4 flex justify-end gap-2">
                        <SecondaryButton @click="closeDeleteConfirmModal">Annulla</SecondaryButton>
                        <PrimaryButton class="!bg-red-600 hover:!bg-red-700" @click="confirmDeleteProceed">Elimina</PrimaryButton>
                    </div>
                </div>
            </div>

            <!-- Conferma anno precedente -->
            <div v-if="showConfirmDestroyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                    <p class="text-gray-700 dark:text-gray-300">
                        {{ page.props.flash?.message || 'Operazioni su anni precedenti possono alterare i rendiconti già generati. Vuoi procedere?' }}
                    </p>
                    <div class="mt-4 flex justify-end gap-2">
                        <SecondaryButton @click="closeConfirmDestroyModal">Annulla</SecondaryButton>
                        <PrimaryButton @click="confirmDestroyProceed">Procedi</PrimaryButton>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
