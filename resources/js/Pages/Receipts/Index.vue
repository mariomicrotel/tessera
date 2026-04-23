<script setup>
import { FunnelIcon, EyeIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({ receipts: Object, filters: Object });

const form = reactive({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

const search = () => router.get(route('receipts.index'), form);
</script>

<template>
    <AppLayout title="Ricevute">
        <Head title="Ricevute" />
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Ricevute</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Da data</label>
                        <input v-model="form.from" type="date" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">A data</label>
                        <input v-model="form.to" type="date" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" />
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">N. Ricevuta</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Socio</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="r in receipts.data" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 font-medium">{{ r.number }}</td>
                                <td class="px-4 py-2">{{ r.member ? r.member.cognome + ' ' + r.member.nome : '—' }}</td>
                                <td class="px-4 py-2">{{ r.issued_at ? new Date(r.issued_at).toLocaleDateString('it-IT') : '' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('receipts.show', r.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline mr-2"><EyeIcon class="size-4" aria-hidden="true" />Dettaglio</Link>
                                    <a v-if="r.file_path" :href="route('receipts.download', r.id)" target="_blank" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Scarica PDF</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!receipts.data?.length" class="px-4 py-8 text-center text-gray-500">Nessuna ricevuta.</p>
                    <div v-if="receipts.prev_page_url || receipts.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="receipts.prev_page_url" :href="receipts.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="receipts.next_page_url" :href="receipts.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
