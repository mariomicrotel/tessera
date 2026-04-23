<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    entries: Array,
    totalEntrate: Number,
    totalUscite: Number,
    filters: Object,
});

const exportUrl = () => {
    const params = new URLSearchParams(props.filters);
    return route('reports.accounting.export') + '?' + params.toString();
};
</script>

<template>
    <AppLayout title="Report contabilità">
        <Head title="Report contabilità" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Report prima nota</h2>
                <a :href="exportUrl()" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-700">Esporta CSV</a>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Totale entrate</div>
                        <div class="text-xl font-semibold text-green-600">€ {{ Number(totalEntrate).toFixed(2) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Totale uscite</div>
                        <div class="text-xl font-semibold text-red-600">€ {{ Number(totalUscite).toFixed(2) }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Conto</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Descrizione</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Importo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="e in entries" :key="e.id">
                                <td class="px-4 py-2">{{ e.date ? new Date(e.date).toLocaleDateString('it-IT') : '' }}</td>
                                <td class="px-4 py-2">{{ e.rendiconto_label || e.rendiconto_code }}</td>
                                <td class="px-4 py-2">{{ e.description || '—' }}</td>
                                <td class="px-4 py-2 text-right" :class="Number(e.amount) >= 0 ? 'text-green-600' : 'text-red-600'">€ {{ Number(e.amount).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
