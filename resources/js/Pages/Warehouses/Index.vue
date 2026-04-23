<script setup>
import { PlusIcon, EyeIcon, PencilSquareIcon, FunnelIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ warehouses: Object, filters: Object });

const form = reactive({
    search: props.filters?.search ?? '',
});

const search = () => router.get(route('warehouses.index'), form);
</script>

<template>
    <AppLayout title="Magazzini">
        <Head title="Magazzini" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Magazzini</h2>
                <Link :href="route('warehouses.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuovo magazzino</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cerca (nome)</label>
                        <TextInput v-model="form.search" type="text" class="block w-full" placeholder="Nome magazzino..." />
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nome</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Sede</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="w in warehouses.data" :key="w.id">
                                <td class="px-4 py-2">{{ w.name }}</td>
                                <td class="px-4 py-2">{{ w.location?.name || '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('warehouses.show', w.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline me-2"><EyeIcon class="size-4" aria-hidden="true" />Dettaglio</Link>
                                    <Link :href="route('warehouses.edit', w.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"><PencilSquareIcon class="size-4" aria-hidden="true" />Modifica</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!warehouses.data?.length" class="px-4 py-8 text-center text-gray-500">Nessun magazzino. <Link :href="route('warehouses.create')" class="text-indigo-600 dark:text-indigo-400 hover:underline">Aggiungi il primo magazzino</Link>.</p>
                    <div v-if="warehouses.prev_page_url || warehouses.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="warehouses.prev_page_url" :href="warehouses.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="warehouses.next_page_url" :href="warehouses.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
