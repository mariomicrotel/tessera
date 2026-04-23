<script setup>
import { PlusIcon, FunnelIcon, EyeIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({ incassi: Object, filters: Object, members: Array });

const form = reactive({
    member_id: props.filters?.member_id ?? '',
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
});

const search = () => router.get(route('quote-sociali.index'), form);
</script>

<template>
    <AppLayout title="Quote sociali">
        <Head title="Quote sociali" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Quote sociali</h2>
                <Link :href="route('incassi.create', { type: 'quota' })">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuova quota</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Socio</label>
                        <select v-model="form.member_id" name="member_id" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                            <option value="">Tutti</option>
                            <option v-for="m in members" :key="m.id" :value="String(m.id)">{{ m.cognome }} {{ m.nome }}</option>
                        </select>
                    </div>
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
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Socio</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Importo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Conto di destinazione</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Ricevuta</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="i in incassi.data" :key="i.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2">{{ i.paid_at ? new Date(i.paid_at).toLocaleDateString('it-IT') : '' }}</td>
                                <td class="px-4 py-2">{{ i.member ? i.member.cognome + ' ' + i.member.nome : '—' }}</td>
                                <td class="px-4 py-2">€ {{ Number(i.amount).toFixed(2) }}</td>
                                <td class="px-4 py-2">{{ i.conto?.name ?? '—' }}</td>
                                <td class="px-4 py-2">{{ i.receipt ? i.receipt.number : '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('incassi.show', i.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"><EyeIcon class="size-4" aria-hidden="true" />Dettaglio</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!incassi.data?.length" class="px-4 py-8 text-center text-gray-500">Nessuna quota registrata.</p>
                    <div v-if="incassi.prev_page_url || incassi.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="incassi.prev_page_url" :href="incassi.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="incassi.next_page_url" :href="incassi.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
