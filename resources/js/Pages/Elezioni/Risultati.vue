<script setup>
import { ArrowLeftIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ elezione: Object, conteggi: Array, totaleVotanti: Number });
</script>

<template>
    <AppLayout :title="'Risultati: ' + elezione.titolo">
        <Head :title="'Risultati: ' + elezione.titolo" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Risultati: {{ elezione.titolo }}</h2>
                <Link :href="route('elezioni.show', elezione.id)" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6 space-y-6">
            <div v-if="elezione.invalidata_at" class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                <p class="font-medium text-red-800 dark:text-red-200 flex items-center gap-2"><ExclamationTriangleIcon class="size-5" aria-hidden="true" />Votazione invalidata</p>
                <p class="mt-2 text-sm text-red-700 dark:text-red-300 whitespace-pre-wrap">{{ elezione.motivazione_invalidazione }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Totale votanti: <strong class="text-gray-900 dark:text-gray-100">{{ totaleVotanti }}</strong>. I voti sono anonimi.</p>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Candidato</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Voti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="row in conteggi" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-2 font-medium">{{ row.member?.cognome }} {{ row.member?.nome }}</td>
                            <td class="px-4 py-2 text-right">{{ row.voti_count ?? 0 }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!conteggi?.length" class="px-4 py-4 text-gray-500 text-sm">Nessun candidato.</div>
            </div>
        </div>
    </AppLayout>
</template>
