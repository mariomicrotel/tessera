<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { PlusIcon, EyeIcon, PencilSquareIcon, CheckCircleIcon, ArchiveBoxIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ statuti: Array });

const formatDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoStyle = (stato) => ({
    approvato:  'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    archiviato: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    bozza:      'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
}[stato] ?? '');

const statoLabel = (stato) => ({ approvato: 'Approvato', archiviato: 'Archiviato', bozza: 'Bozza' }[stato] ?? stato);

const elimina = (id) => {
    if (!confirm('Eliminare questo statuto?')) return;
    router.delete(route('ets.statuto.destroy', id));
};
</script>

<template>
    <AppLayout title="Statuto">
        <Head title="Statuto ETS" />
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Statuto</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Versioni dello statuto associativo</p>
                </div>
                <Link :href="route('ets.statuto.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" />Nuovo statuto</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

                <div v-if="statuti.length === 0" class="bg-white dark:bg-gray-800 shadow rounded-lg px-6 py-12 text-center">
                    <ArchiveBoxIcon class="size-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-gray-500 dark:text-gray-400 mb-4">Nessuno statuto ancora caricato.</p>
                    <Link :href="route('ets.statuto.create')">
                        <PrimaryButton>Crea il primo statuto</PrimaryButton>
                    </Link>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Versione</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Titolo</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stato</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Approvazione</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Artt.</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Allegati</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="s in statuti" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 text-sm font-mono text-gray-700 dark:text-gray-300">{{ s.versione }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ s.titolo }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="statoStyle(s.stato)" class="text-xs font-medium px-2 py-1 rounded-full">{{ statoLabel(s.stato) }}</span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ formatDate(s.data_approvazione) }}</td>
                                <td class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ s.clausole_count ?? 0 }}</td>
                                <td class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ s.attachments?.length ?? 0 }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Link :href="route('ets.statuto.show', s.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300" title="Visualizza">
                                            <EyeIcon class="size-4" />
                                        </Link>
                                        <Link :href="route('ets.statuto.edit', s.id)" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" title="Modifica">
                                            <PencilSquareIcon class="size-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <Link :href="route('ets.compliance.dashboard')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← Torna al pannello compliance</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
