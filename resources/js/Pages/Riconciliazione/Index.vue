<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { PlusIcon, TrashIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({ estratti: Array });

const fmt     = (v) => Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2 });
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const destroy = (e) => {
    if (confirm(`Eliminare l'estratto conto "${e.nome_file}"? Saranno eliminati tutti i movimenti.`)) {
        router.delete(route('riconciliazione.destroy', e));
    }
};
</script>

<template>
    <AppLayout title="Riconciliazione Bancaria">
        <Head title="Riconciliazione Bancaria" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Riconciliazione Bancaria
                </h2>
                <Link :href="route('riconciliazione.create')">
                    <PrimaryButton><PlusIcon class="size-4 mr-1" />Carica estratto conto</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div v-if="!estratti.length" class="bg-white dark:bg-gray-800 shadow rounded-lg p-8 text-center text-gray-500">
                    Nessun estratto conto importato. Carica il primo file CSV o MT940.
                </div>

                <div v-else class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">File</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Banca / IBAN</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Periodo</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Movimenti</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Da riconciliare</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="e in estratti" :key="e.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                    <span class="text-xs uppercase text-gray-400 mr-1">{{ e.formato }}</span>
                                    {{ e.nome_file }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    <div>{{ e.banca ?? '—' }}</div>
                                    <div class="text-xs font-mono text-gray-400">{{ e.iban ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    {{ fmtDate(e.periodo_dal) }} — {{ fmtDate(e.periodo_al) }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-200">
                                    {{ e.movimenti_count }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span :class="e.movimenti_non_riconciliati_count > 0
                                        ? 'text-orange-600 font-semibold'
                                        : 'text-green-600 font-semibold'">
                                        {{ e.movimenti_non_riconciliati_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex justify-center gap-3">
                                        <Link :href="route('riconciliazione.show', e)"
                                              class="text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1 text-xs">
                                            Apri <ArrowRightIcon class="size-3" />
                                        </Link>
                                        <button @click="destroy(e)"
                                                class="text-red-500 hover:text-red-700">
                                            <TrashIcon class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
