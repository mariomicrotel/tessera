<script setup>
import { PlusIcon, PencilSquareIcon, TrashIcon, FunnelIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ locations: Object, filters: Object });

const form = reactive({
    search: props.filters?.search ?? '',
});

const search = () => router.get(route('locations.index'), form);

function elimina(id) {
    if (!confirm('Eliminare questa sede?')) return;
    router.delete(route('locations.destroy', id));
}
</script>

<template>
    <AppLayout title="Sedi">
        <Head title="Sedi" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Sedi</h2>
                <Link :href="route('locations.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuova sede</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cerca (nome)</label>
                        <TextInput v-model="form.search" type="text" class="block w-full" placeholder="Nome sede..." />
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nome</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Indirizzo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Magazzini</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="loc in locations.data" :key="loc.id">
                                <td class="px-4 py-2">{{ loc.name }}</td>
                                <td class="px-4 py-2">{{ loc.tipo === 'legale' ? 'Legale' : 'Operativa' }}</td>
                                <td class="px-4 py-2">{{ loc.address || '—' }}</td>
                                <td class="px-4 py-2">{{ loc.warehouses_count ?? 0 }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('locations.edit', loc.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline me-2"><PencilSquareIcon class="size-4" aria-hidden="true" />Modifica</Link>
                                    <button type="button" @click="elimina(loc.id)" class="inline-flex items-center gap-1 text-sm text-red-600 dark:text-red-400 hover:underline"><TrashIcon class="size-4" aria-hidden="true" />Elimina</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!locations.data?.length" class="px-4 py-8 text-center text-gray-500">Nessuna sede. <Link :href="route('locations.create')" class="text-indigo-600 dark:text-indigo-400 hover:underline">Aggiungi la prima sede</Link>.</p>
                    <div v-if="locations.prev_page_url || locations.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="locations.prev_page_url" :href="locations.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="locations.next_page_url" :href="locations.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
