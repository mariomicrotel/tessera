<script setup>
import { PencilSquareIcon, FunnelIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ emailTemplates: Object, typeLabels: Object, filters: Object });

const form = reactive({
    search: props.filters?.search ?? '',
});

const search = () => router.get(route('email-templates.index'), form);

const rows = () => props.emailTemplates?.data ?? [];
function labelFor(tipo) {
    return (props.typeLabels && props.typeLabels[tipo]) || tipo;
}
</script>

<template>
    <AppLayout title="Template email">
        <Head title="Template email" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Template email</h2>
                <Link :href="route('templates.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />Template documenti
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cerca (tipo o oggetto)</label>
                        <TextInput v-model="form.search" type="text" class="block w-full" placeholder="Tipo, oggetto..." />
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo email</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Oggetto</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="t in rows()" :key="t.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ labelFor(t.tipo) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ t.subject || '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('email-templates.edit', t.tipo)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                        <PencilSquareIcon class="size-4" aria-hidden="true" />Modifica
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!rows().length" class="px-4 py-8 text-center text-gray-500">Nessun template email trovato.</div>
                    <div v-if="emailTemplates?.prev_page_url || emailTemplates?.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="emailTemplates.prev_page_url" :href="emailTemplates.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="emailTemplates.next_page_url" :href="emailTemplates.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
