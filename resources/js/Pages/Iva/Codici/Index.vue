<script setup>
import { ArrowLeftIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';

const props = defineProps({
    codici: Object,
    filters: Object,
});

const showConfirmDelete = ref(false);
const selectedCodice = ref(null);

const toggleFilter = (field) => {
    const newFilters = { ...props.filters };
    if (newFilters[field]) {
        delete newFilters[field];
    } else {
        newFilters[field] = true;
    }
    router.get(route('iva.codici.index'), newFilters, { preserveScroll: true });
};

const confirmDelete = (codice) => {
    selectedCodice.value = codice;
    showConfirmDelete.value = true;
};

const deleteCode = () => {
    if (selectedCodice.value) {
        router.delete(route('iva.codici.destroy', selectedCodice.value.id), {
            onFinish: () => {
                showConfirmDelete.value = false;
                selectedCodice.value = null;
            },
        });
    }
};

const tipiLabel = {
    normale: 'Normale',
    esente: 'Esente',
    fuori_campo: 'Fuori campo',
    non_imponibile: 'Non imponibile',
    reverse_charge: 'Reverse charge',
    split_payment: 'Split payment',
};

const natureSdi = {
    N: 'Normale',
    E: 'Esente',
    F: 'Fuori campo',
    L: 'Non imponibile',
    R: 'Reverse charge',
    S: 'Split payment',
};
</script>

<template>
    <Head title="Codici IVA" />

    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Codici IVA</h1>
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('iva.codici.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        + Nuovo Codice IVA
                    </Link>
                    <Link
                        :href="route('iva.dashboard')"
                        class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900"
                    >
                        <ArrowLeftIcon class="w-4 h-4 mr-2" />
                        IVA Dashboard
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex items-center gap-4">
                <label class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        :checked="filters.attivi"
                        @change="toggleFilter('attivi')"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    />
                    <span class="text-sm text-gray-700">Solo codici attivi</span>
                </label>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Codice
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Descrizione
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aliquota (%)
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tipo
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Natura SDI
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    % Indetraibile
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Stato
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Azioni
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="codice in codici.data" :key="codice.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-900">{{ codice.codice }}</span>
                                        <span
                                            v-if="codice.di_sistema"
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                        >
                                            Sistema
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-gray-900">{{ codice.descrizione }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <span class="text-gray-900 font-semibold">{{ codice.percentuale }}%</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-600">{{ tipiLabel[codice.tipo] }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-600">{{ codice.natura_sdi }} - {{ natureSdi[codice.natura_sdi] }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <span class="text-gray-900">{{ codice.indetraibile_percentuale }}%</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                            codice.attivo
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-800',
                                        ]"
                                    >
                                        {{ codice.attivo ? 'Attivo' : 'Inattivo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <Link
                                        v-if="!codice.di_sistema"
                                        :href="route('iva.codici.edit', codice.id)"
                                        class="text-indigo-600 hover:text-indigo-900 inline-flex items-center gap-1 mr-3"
                                    >
                                        <PencilIcon class="w-4 h-4" />
                                        Modifica
                                    </Link>
                                    <button
                                        v-if="!codice.di_sistema"
                                        @click="confirmDelete(codice)"
                                        class="text-red-600 hover:text-red-900 inline-flex items-center gap-1"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                        Elimina
                                    </button>
                                    <span v-else class="text-gray-400 text-xs">
                                        (non modificabile)
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="codici.data.length === 0">
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    Nessun codice IVA trovato.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="codici.data.length > 0" class="mt-6">
                <Pagination :links="codici.links" />
            </div>

            <!-- Delete Confirmation Modal -->
            <div v-if="showConfirmDelete" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <!-- Background overlay -->
                    <div
                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                        @click="showConfirmDelete = false"
                    ></div>

                    <!-- Modal -->
                    <div
                        class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                    >
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                Elimina Codice IVA
                            </h3>
                            <p class="text-gray-500">
                                Sei sicuro di voler eliminare il codice IVA
                                <strong>{{ selectedCodice?.codice }} - {{ selectedCodice?.descrizione }}</strong>?
                            </p>
                            <p class="text-sm text-gray-400 mt-2">
                                Questa azione non può essere annullata.
                            </p>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                            <button
                                @click="deleteCode"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Elimina
                            </button>
                            <button
                                @click="showConfirmDelete = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Annulla
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
