<script setup>
import { PlusIcon, EyeIcon, FunnelIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    refunds: Object,
    filters: Object,
    members: Array,
    requestForSelf: { type: Boolean, default: false },
    canApprove: { type: Boolean, default: false },
});

const form = reactive({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
    member_id: props.filters?.member_id ?? '',
    status: props.filters?.status ?? '',
});

const search = () => router.get(route('expense-refunds.index'), form);

const statusLabel = (status) => {
    const labels = { bozza: 'Bozza', richiesta: 'Richiesta', approvato: 'Approvato', stampata: 'Stampata', contabilizzata: 'Contabilizzata' };
    return labels[status] || status;
};

const statusClass = (status) => {
    const classes = {
        bozza: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        richiesta: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        approvato: 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400',
        stampata: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        contabilizzata: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    };
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
};
</script>

<template>
    <AppLayout :title="requestForSelf ? 'I miei rimborsi' : 'Rimborsi spese'">
        <Head :title="requestForSelf ? 'I miei rimborsi' : 'Rimborsi spese'" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ requestForSelf ? 'I miei rimborsi' : 'Rimborsi spese' }}</h2>
                <Link :href="route('expense-refunds.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />{{ requestForSelf ? 'Richiedi rimborso' : 'Nuovo rimborso' }}</PrimaryButton>
                </Link>
            </div>
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
                    <template v-if="!requestForSelf && members?.length">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Beneficiario</label>
                            <select v-model="form.member_id" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                                <option value="">Tutti</option>
                                <option v-for="m in members" :key="m.id" :value="String(m.id)">{{ m.cognome }} {{ m.nome }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stato</label>
                            <select v-model="form.status" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                                <option value="">Tutti</option>
                                <option value="bozza">Bozza</option>
                                <option value="richiesta">Richiesta</option>
                                <option value="approvato">Approvato</option>
                                <option value="stampata">Stampata</option>
                                <option value="contabilizzata">Contabilizzata</option>
                            </select>
                        </div>
                    </template>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Beneficiario</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Totale</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="r in refunds.data" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2">{{ r.refund_date ? new Date(r.refund_date).toLocaleDateString('it-IT') : '' }}</td>
                                <td class="px-4 py-2">{{ r.member ? r.member.cognome + ' ' + r.member.nome : '—' }}</td>
                                <td class="px-4 py-2">€ {{ Number(r.total).toFixed(2) }}</td>
                                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs" :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span></td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('expense-refunds.show', r.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline"><EyeIcon class="size-4" aria-hidden="true" />Apri</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!refunds.data?.length" class="px-4 py-8 text-center text-gray-500">Nessun rimborso.</p>
                    <div v-if="refunds.prev_page_url || refunds.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="refunds.prev_page_url" :href="refunds.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="refunds.next_page_url" :href="refunds.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
