<script setup>
import { PencilSquareIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    receiptTemplates: { type: Array, default: () => [] },
});

function previewText(input) {
    const raw = (input || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return raw.length > 120 ? raw.slice(0, 120) + '…' : raw;
}
</script>

<template>
    <AppLayout title="Template ricevute">
        <Head title="Template ricevute" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Template ricevute</h2>
                <Link :href="route('templates.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />Template documenti
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo ricevuta</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Anteprima testo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="t in receiptTemplates" :key="t.tipo" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ t.label }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ previewText(t.body_text) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('receipt-templates.edit', t.tipo)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                        <PencilSquareIcon class="size-4" aria-hidden="true" />Modifica
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
