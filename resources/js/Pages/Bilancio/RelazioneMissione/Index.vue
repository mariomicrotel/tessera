<script setup>
import {
    DocumentTextIcon,
    PlusIcon,
    CheckCircleIcon,
    ClockIcon,
    PencilSquareIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    relazioni:  Array,
    statiLabel: Object,
    anniRange:  Array,
});

const annoFiltro = ref(new Date().getFullYear() - 1);

watch(annoFiltro, (val) => {
    router.get(route('relazione-missione.create', usePage().props.tenant), { anno: val }, { preserveState: true });
});

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoBadgeClass = (stato) => ({
    bozza:      'bg-yellow-100 text-yellow-800',
    definitiva: 'bg-blue-100 text-blue-800',
    approvata:  'bg-green-100 text-green-800',
}[stato] ?? 'bg-gray-100 text-gray-700');

import { usePage } from '@inertiajs/vue3';
const page = usePage();
const tenant = page.props.tenant;
</script>

<template>
    <AppLayout title="Relazione di Missione">
        <Head title="Relazione di Missione" />
        <template #header>
            <div class="flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <DocumentTextIcon class="w-6 h-6 text-blue-600" />
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Relazione di Missione</h2>
                    <span class="text-xs text-gray-400 ml-1">Art. 13 D.Lgs. 117/2017</span>
                </div>
                <Link :href="route('relazione-missione.create', tenant)">
                    <PrimaryButton class="flex items-center gap-2">
                        <PlusIcon class="w-4 h-4" />
                        Nuova Relazione
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Empty state -->
            <div v-if="relazioni.length === 0"
                 class="text-center bg-white dark:bg-gray-800 rounded-xl shadow p-16">
                <DocumentTextIcon class="w-14 h-14 text-gray-300 mx-auto mb-4" />
                <p class="text-gray-500 dark:text-gray-400 text-lg font-medium mb-2">Nessuna relazione presente</p>
                <p class="text-sm text-gray-400 mb-6">
                    La Relazione di Missione è obbligatoria per gli ETS ai sensi dell'art. 13 D.Lgs. 117/2017.
                </p>
                <Link :href="route('relazione-missione.create', tenant)">
                    <PrimaryButton>Crea la prima relazione</PrimaryButton>
                </Link>
            </div>

            <!-- Table -->
            <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Anno</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Sezioni compilate</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Approvata da</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Data approvazione</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr v-for="rel in relazioni" :key="rel.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                            <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100">
                                {{ rel.anno }}
                            </td>
                            <td class="px-6 py-4">
                                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', statoBadgeClass(rel.stato)]">
                                    <CheckCircleIcon v-if="rel.stato === 'approvata'" class="w-3.5 h-3.5 mr-1" />
                                    <ClockIcon v-else class="w-3.5 h-3.5 mr-1" />
                                    {{ statiLabel[rel.stato] ?? rel.stato }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ (rel.sezioni ?? []).filter(s => s.testo?.trim()).length }} / {{ (rel.sezioni ?? []).length }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ rel.organo_approvante ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ fmtDate(rel.data_approvazione) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('relazione-missione.show', [tenant, rel.id])"
                                      class="text-blue-600 hover:text-blue-800 font-medium text-sm mr-3">
                                    Visualizza
                                </Link>
                                <Link v-if="rel.stato !== 'approvata'"
                                      :href="route('relazione-missione.edit', [tenant, rel.id])"
                                      class="text-indigo-600 hover:text-indigo-800 font-medium text-sm">
                                    <PencilSquareIcon class="w-4 h-4 inline" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>
