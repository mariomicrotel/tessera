<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { PlusIcon, EyeIcon, PencilSquareIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ atti: Array });

const formatDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoStyle = (stato) => stato === 'registrato'
    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
</script>

<template>
    <AppLayout title="Atto Costitutivo">
        <Head title="Atto Costitutivo" />
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Atto Costitutivo</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Atto notarile e registrazione all'Agenzia Entrate</p>
                </div>
                <Link :href="route('ets.atto-costitutivo.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" />Nuovo atto</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

                <div v-if="atti.length === 0" class="bg-white dark:bg-gray-800 shadow rounded-lg px-6 py-12 text-center">
                    <DocumentTextIcon class="size-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-gray-500 dark:text-gray-400 mb-4">Nessun atto costitutivo registrato.</p>
                    <Link :href="route('ets.atto-costitutivo.create')">
                        <PrimaryButton>Inserisci atto costitutivo</PrimaryButton>
                    </Link>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notaio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Repertorio</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data atto</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reg. AE</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stato</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Allegati</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="a in atti" :key="a.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ a.notaio || '—' }}</td>
                                <td class="px-6 py-4 text-sm font-mono text-gray-700 dark:text-gray-300">{{ a.repertorio || '—' }}</td>
                                <td class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ formatDate(a.data_atto) }}</td>
                                <td class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ formatDate(a.data_registrazione_ae) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="statoStyle(a.stato)" class="text-xs font-medium px-2 py-1 rounded-full">{{ a.stato_label }}</span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">{{ a.attachments?.length ?? 0 }}</td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Link :href="route('ets.atto-costitutivo.show', a.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                            <EyeIcon class="size-4" />
                                        </Link>
                                        <Link :href="route('ets.atto-costitutivo.edit', a.id)" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                                            <PencilSquareIcon class="size-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <Link :href="route('ets.compliance.dashboard')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← Torna al pannello compliance</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
