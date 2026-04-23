<script setup>
import { PlusIcon, FunnelIcon, EyeIcon, ArrowLeftIcon, ArrowRightIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({ spese: Object, filters: Object });

const form = reactive({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

const search = () => router.get(route('spese.index'), form);

function destroy(spesa) {
    if (!confirm('Eliminare questa spesa? Verrà eliminato anche l\'eventuale movimento in prima nota collegato.')) return;
    router.delete(route('spese.destroy', spesa.id));
}
</script>

<template>
    <AppLayout title="Spese">
        <Head title="Spese" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Spese</h2>
                <Link :href="route('spese.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuova spesa</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Da data</label>
                        <input v-model="form.from" type="date" name="from" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">A data</label>
                        <input v-model="form.to" type="date" name="to" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" />
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Descrizione</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Importo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Conto</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">In prima nota</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="s in spese.data" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2">{{ s.date ? new Date(s.date).toLocaleDateString('it-IT') : '' }}</td>
                                <td class="px-4 py-2">{{ s.description || '—' }}</td>
                                <td class="px-4 py-2">€ {{ Number(s.amount).toFixed(2) }}</td>
                                <td class="px-4 py-2">{{ s.conto?.name ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <template v-if="s.genera_prima_nota && s.rendiconto_label">
                                        Sì – {{ s.rendiconto_label }}
                                    </template>
                                    <span v-else>No</span>
                                </td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    <Link :href="route('spese.show', s.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"><EyeIcon class="size-4" aria-hidden="true" />Dettaglio</Link>
                                    <button type="button" @click="destroy(s)" class="inline-flex items-center gap-1 text-sm text-red-600 dark:text-red-400 hover:underline"><TrashIcon class="size-4" aria-hidden="true" />Elimina</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!spese.data?.length" class="px-4 py-8 text-center text-gray-500">Nessuna spesa registrata.</p>
                    <div v-if="spese.prev_page_url || spese.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="spese.prev_page_url" :href="spese.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="spese.next_page_url" :href="spese.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
