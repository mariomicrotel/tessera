<script setup>
import { PlusIcon, EyeIcon, PencilSquareIcon, FunnelIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ properties: Object, filters: Object });

const form = reactive({
    search: props.filters?.search ?? '',
});

const search = () => router.get(route('properties.index'), form);
</script>

<template>
    <AppLayout title="Immobili">
        <Head title="Immobili" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Immobili</h2>
                <Link :href="route('properties.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuovo immobile</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cerca (nome)</label>
                        <TextInput v-model="form.search" type="text" class="block w-full" placeholder="Nome immobile..." />
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nome</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Indirizzo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Cespiti</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="p in properties.data" :key="p.id">
                                <td class="px-4 py-2">{{ p.name }}</td>
                                <td class="px-4 py-2">{{ p.address || '—' }}</td>
                                <td class="px-4 py-2">{{ p.assets_count ?? 0 }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('properties.show', p.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline me-2"><EyeIcon class="size-4" aria-hidden="true" />Dettaglio</Link>
                                    <Link :href="route('properties.edit', p.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"><PencilSquareIcon class="size-4" aria-hidden="true" />Modifica</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!properties.data?.length" class="px-4 py-8 text-center text-gray-500">Nessun immobile. <Link :href="route('properties.create')" class="text-indigo-600 dark:text-indigo-400 hover:underline">Aggiungi il primo immobile</Link>.</p>
                    <div v-if="properties.prev_page_url || properties.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="properties.prev_page_url" :href="properties.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="properties.next_page_url" :href="properties.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
