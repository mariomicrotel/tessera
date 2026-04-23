<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PencilSquareIcon, PlusIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ warehouse: Object, items: Array });

const stockForm = useForm({
    item_id: '',
    quantity: '0',
});

const editingStockId = ref(null);
const editingQuantity = ref('');

function addStock() {
    const itemId = stockForm.item_id;
    const qty = Number(stockForm.quantity);
    if (!itemId || (qty < 0 && qty !== 0)) return;
    stockForm.transform(() => ({ item_id: itemId, quantity: qty })).post(route('warehouses.stocks.store', props.warehouse.id), {
        preserveScroll: true,
        onSuccess: () => stockForm.reset('item_id', 'quantity'),
    });
}

function startEdit(stock) {
    editingStockId.value = stock.id;
    editingQuantity.value = stock.quantity != null ? String(stock.quantity) : '0';
}

function cancelEdit() {
    editingStockId.value = null;
}

function saveQuantity(stock) {
    const qty = Number(editingQuantity.value);
    if (qty < 0) return;
    router.put(route('warehouses.stocks.update', [props.warehouse.id, stock.id]), { quantity: qty }, {
        preserveScroll: true,
        onSuccess: () => { editingStockId.value = null; },
    });
}

function formatQty(val) {
    if (val == null || val === '') return '0';
    const n = Number(val);
    return Number.isFinite(n) ? n.toFixed(2) : '0';
}

function eliminaGiacenza(stockId) {
    if (!confirm('Eliminare questa giacenza?')) return;
    router.delete(route('warehouses.stocks.destroy', [props.warehouse.id, stockId]), {
        preserveScroll: true,
    });
}

function eliminaMagazzino() {
    if (!confirm('Eliminare il magazzino e tutte le giacenze?')) return;
    router.delete(route('warehouses.destroy', props.warehouse.id));
}
</script>

<template>
    <AppLayout title="Magazzino">
        <Head :title="warehouse.name" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ warehouse.name }}</h2>
                <div class="flex items-center gap-2">
                    <Link :href="route('warehouses.edit', warehouse.id)" class="inline-flex items-center gap-1 px-3 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <PencilSquareIcon class="size-4" aria-hidden="true" />Modifica
                    </Link>
                    <button
                        type="button"
                        @click="eliminaMagazzino"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded-md text-sm font-medium text-red-700 dark:text-red-300 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-700 hover:bg-red-50 dark:hover:bg-red-900/20"
                    >
                        <TrashIcon class="size-4" aria-hidden="true" />Elimina magazzino
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Nome</dt><dd>{{ warehouse.name }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Sede</dt><dd>{{ warehouse.location?.name || '—' }}</dd></div>
                </dl>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium mb-4">Giacenze</h3>
                <template v-if="items?.length">
                    <form @submit.prevent="addStock" class="mb-4 flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px]">
                            <InputLabel for="stock_item_id" value="Articolo *" />
                            <select
                                id="stock_item_id"
                                v-model="stockForm.item_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                            >
                                <option value="">Seleziona articolo...</option>
                                <option v-for="i in items" :key="i.id" :value="i.id">{{ i.name }}{{ i.code ? ' (' + i.code + ')' : '' }}</option>
                            </select>
                            <InputError class="mt-1" :message="stockForm.errors.item_id" />
                        </div>
                        <div class="w-28">
                            <InputLabel for="stock_quantity" value="Quantità *" />
                            <TextInput id="stock_quantity" v-model="stockForm.quantity" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="stockForm.errors.quantity" />
                        </div>
                        <PrimaryButton type="submit" :disabled="stockForm.processing || !stockForm.item_id">
                            <PlusIcon class="size-4 me-2" aria-hidden="true" />Aggiungi giacenza
                        </PrimaryButton>
                    </form>
                </template>
                <p v-else class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    <Link :href="route('items.create')" class="text-indigo-600 dark:text-indigo-400 hover:underline">Aggiungi prima degli articoli</Link> per poter inserire giacenze.
                </p>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Articolo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Codice</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Unità</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Quantità</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="s in warehouse.stocks" :key="s.id">
                                <td class="px-4 py-2">{{ s.item?.name || '—' }}</td>
                                <td class="px-4 py-2">{{ s.item?.code || '—' }}</td>
                                <td class="px-4 py-2">{{ s.item?.unit || 'pz' }}</td>
                                <td class="px-4 py-2">
                                    <template v-if="editingStockId === s.id">
                                        <input
                                            v-model="editingQuantity"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-24 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                                        />
                                        <button type="button" @click="saveQuantity(s)" class="ml-2 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Salva</button>
                                        <button type="button" @click="cancelEdit" class="ml-2 text-sm text-gray-500 hover:underline">Annulla</button>
                                    </template>
                                    <template v-else>
                                        {{ formatQty(s.quantity) }}
                                        <button type="button" @click="startEdit(s)" class="ml-2 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Modifica</button>
                                    </template>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <button
                                        type="button"
                                        @click="eliminaGiacenza(s.id)"
                                        class="p-1.5 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                        title="Elimina giacenza"
                                    >
                                        <XMarkIcon class="size-4" aria-hidden="true" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="!warehouse.stocks?.length" class="py-4 text-sm text-gray-500 dark:text-gray-400">Nessuna giacenza.</p>
            </div>
        </div>
    </AppLayout>
</template>
