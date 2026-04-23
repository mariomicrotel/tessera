<script setup>
import { PlusIcon, EyeIcon, PencilSquareIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({ verbali: Object, filters: Object });

const currentTipo = props.filters?.tipo ?? '';

const applyFilter = (tipo) => {
  router.get(route('verbali.index'), tipo ? { tipo } : {}, { preserveState: true });
};

const statusLabel = (stato) => (stato === 'confermato' ? 'Confermato' : 'Bozza');
const statusClass = (stato) =>
  stato === 'confermato'
    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
</script>

<template>
    <AppLayout title="Verbali">
        <Head title="Verbali" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Verbali</h2>
                <Link :href="route('verbali.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuovo verbale</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap gap-2 items-center">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipo:</span>
                    <button
                        type="button"
                        :class="[!currentTipo ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600']"
                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                        @click="applyFilter('')"
                    >
                        Tutti
                    </button>
                    <button
                        type="button"
                        :class="[currentTipo === 'consiglio_direttivo' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600']"
                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                        @click="applyFilter('consiglio_direttivo')"
                    >
                        Consiglio direttivo
                    </button>
                    <button
                        type="button"
                        :class="[currentTipo === 'assemblea' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600']"
                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                        @click="applyFilter('assemblea')"
                    >
                        Assemblea
                    </button>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Titolo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">N.</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="v in verbali.data" :key="v.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2">{{ v.data ? new Date(v.data).toLocaleDateString('it-IT') : '—' }}</td>
                                <td class="px-4 py-2">{{ v.tipo_label || v.tipo }}</td>
                                <td class="px-4 py-2">{{ v.titolo }}</td>
                                <td class="px-4 py-2">{{ v.numero != null && v.anno ? `n. ${v.numero}/${v.anno}` : (v.numero ?? '—') }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 rounded text-xs font-medium" :class="statusClass(v.stato)">{{ statusLabel(v.stato) }}</span>
                                </td>
                                <td class="px-4 py-2 text-right flex gap-2 justify-end">
                                    <Link :href="route('verbali.show', { verbale: v.id })" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                        <EyeIcon class="size-4" aria-hidden="true" />Visualizza
                                    </Link>
                                    <Link v-if="v.in_bozza" :href="route('verbali.edit', { verbale: v.id })" class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:underline">
                                        <PencilSquareIcon class="size-4" aria-hidden="true" />Modifica
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!verbali.data?.length" class="px-4 py-8 text-center text-gray-500">Nessun verbale.</p>
                    <div v-if="verbali.prev_page_url || verbali.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="verbali.prev_page_url" :href="verbali.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="verbali.next_page_url" :href="verbali.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
