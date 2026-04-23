<script setup>
import { PlusIcon, PencilSquareIcon, TrashIcon, FunnelIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ memberTypes: Object, filters: Object });

const form = reactive({
    search: props.filters?.search ?? '',
});

const search = () => router.get(route('member-types.index'), form);

function elimina(t) {
    if (!confirm(`Eliminare la tipologia "${t.display_name || t.name}"?`)) return;
    router.delete(route('member-types.destroy', t.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Tipologie socio">
        <Head title="Tipologie socio" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Tipologie socio</h2>
                <Link :href="route('member-types.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuova tipologia</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cerca (nome)</label>
                        <TextInput v-model="form.search" type="text" class="block w-full" placeholder="Nome tipologia..." />
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nome</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Soci associati</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="t in memberTypes.data" :key="t.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 font-medium">{{ t.display_name || t.name }}</td>
                                <td class="px-4 py-2">{{ t.members_count ?? 0 }}</td>
                                <td class="px-4 py-2 text-right">
                                    <span v-if="(t.members_count ?? 0) > 0" class="text-xs text-gray-400 me-3">Non eliminabile (ha soci)</span>
                                    <Link :href="route('member-types.edit', t.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline me-3">
                                        <PencilSquareIcon class="size-4" aria-hidden="true" />Modifica
                                    </Link>
                                    <button
                                        type="button"
                                        :disabled="(t.members_count ?? 0) > 0"
                                        @click="elimina(t)"
                                        class="inline-flex items-center gap-1 text-sm text-red-600 dark:text-red-400 hover:underline disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:no-underline"
                                    >
                                        <TrashIcon class="size-4" aria-hidden="true" />Elimina
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!memberTypes.data?.length" class="px-4 py-8 text-center text-gray-500">Nessuna tipologia. Creane una per iniziare.</div>
                    <div v-if="memberTypes.prev_page_url || memberTypes.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="memberTypes.prev_page_url" :href="memberTypes.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="memberTypes.next_page_url" :href="memberTypes.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
