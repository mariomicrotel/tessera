<script setup>
import { EyeIcon, PlusIcon, PencilSquareIcon, FunnelIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ events: Object, filters: Object });

const form = reactive({
    search: props.filters?.search ?? '',
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

const search = () => router.get(route('events.index'), form);
</script>

<template>
    <AppLayout title="Eventi">
        <Head title="Eventi" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Eventi</h2>
                <Link :href="route('events.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuovo evento</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Titolo</label>
                        <TextInput v-model="form.search" type="text" class="block w-full" placeholder="Cerca per titolo..." />
                    </div>
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
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Titolo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Iscritti</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="e in events.data" :key="e.id">
                                <td class="px-4 py-2">{{ e.title }}</td>
                                <td class="px-4 py-2">{{ e.start_at ? new Date(e.start_at).toLocaleString('it-IT') : '' }}</td>
                                <td class="px-4 py-2">{{ e.registrations_count ?? 0 }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('events.show', e.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline me-2"><EyeIcon class="size-4" aria-hidden="true" />Dettaglio</Link>
                                    <Link :href="route('events.edit', e.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"><PencilSquareIcon class="size-4" aria-hidden="true" />Modifica</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!events.data?.length" class="px-4 py-8 text-center text-gray-500">Nessun evento.</p>
                    <div v-if="events.prev_page_url || events.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="events.prev_page_url" :href="events.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="events.next_page_url" :href="events.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
