<script setup>
import { PlusIcon, PencilSquareIcon, TrashIcon, FunnelIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ templates: Object, filters: Object });

const form = reactive({
    search: props.filters?.search ?? '',
    categoria: props.filters?.categoria ?? '',
});

const search = () => router.get(route('templates.index'), form);

function usoLabel(t) {
    if (t.categoria === 'documento') return 'Documento';
    if (t.categoria === 'verbale') return t.tipo_label ? `Verbale – ${t.tipo_label}` : 'Verbale';
    return t.categoria;
}

function elimina(t) {
    if (!confirm(`Eliminare il template "${t.nome}"?`)) return;
    router.delete(route('templates.destroy', t.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Template">
        <Head title="Template" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Template</h2>
                <div class="flex gap-2">
                    <Link :href="route('documents.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Documenti</Link>
                    <Link :href="route('templates.create')">
                        <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuovo template</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div class="min-w-[180px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                        <TextInput v-model="form.search" type="text" class="block w-full" placeholder="Cerca..." />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoria</label>
                        <select v-model="form.categoria" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                            <option value="">Tutte</option>
                            <option value="documento">Documento</option>
                            <option value="verbale">Verbale</option>
                        </select>
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nome</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Uso</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="t in templates.data" :key="t.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ t.nome }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ usoLabel(t) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('templates.edit', t.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline me-3">
                                        <PencilSquareIcon class="size-4" aria-hidden="true" />Modifica
                                    </Link>
                                    <button
                                        type="button"
                                        @click="elimina(t)"
                                        class="inline-flex items-center gap-1 text-sm text-red-600 dark:text-red-400 hover:underline"
                                    >
                                        <TrashIcon class="size-4" aria-hidden="true" />Elimina
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!templates.data?.length" class="px-4 py-8 text-center text-gray-500">Nessun template. Creane uno per usarlo nella redazione di documenti o verbali.</div>
                    <div v-if="templates.prev_page_url || templates.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="templates.prev_page_url" :href="templates.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="templates.next_page_url" :href="templates.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
