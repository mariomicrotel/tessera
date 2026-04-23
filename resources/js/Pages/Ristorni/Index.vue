<script setup>
import { ref, watch } from 'vue';
import { PlusIcon, EyeIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    ristorni: Object,
    filters:  Object,
});

const anno = ref(props.filters?.anno || '');
const status = ref(props.filters?.status || '');

let t = null;
function applyFilters() {
    router.get(route('ristorni.index'), {
        anno:   anno.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
}
watch([anno, status], () => {
    clearTimeout(t);
    t = setTimeout(applyFilters, 300);
});

const fmt = (v) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v) || 0);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statusConfig = {
    deliberato:   { label: 'Deliberato',   cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
    in_pagamento: { label: 'In pagamento', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    pagato:       { label: 'Pagato',       cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
    annullato:    { label: 'Annullato',    cls: 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400' },
};
const badgeFor = (s) => statusConfig[s] ?? { label: s, cls: 'bg-gray-100 text-gray-600' };

const totaleNetto = (r) => (r.entries ?? []).reduce((s, e) => s + Number(e.importo_netto || 0), 0);
</script>

<template>
    <Head title="Ristorni ai soci" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Ristorni ai soci</h2>
                <Link :href="route('ristorni.create')">
                    <PrimaryButton><PlusIcon class="w-4 h-4 mr-1" /> Nuovo ristorno</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <!-- Filtri -->
            <div class="flex flex-wrap gap-3 bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                <input v-model="anno" type="number" placeholder="Anno"
                       class="border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 w-32" />
                <select v-model="status"
                        class="border rounded px-3 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                    <option value="">Tutti gli stati</option>
                    <option value="deliberato">Deliberato</option>
                    <option value="in_pagamento">In pagamento</option>
                    <option value="pagato">Pagato</option>
                    <option value="annullato">Annullato</option>
                </select>
            </div>

            <!-- Tabella -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Anno</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Delibera</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Lordo</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Netto</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Soci</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Pagamento</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="r in ristorni.data" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 font-semibold">{{ r.anno }}</td>
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ fmtDate(r.data_delibera_assemblea) }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ fmt(r.importo_totale_deliberato) }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-700 dark:text-gray-300">{{ fmt(totaleNetto(r)) }}</td>
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ (r.entries ?? []).length }}</td>
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ fmtDate(r.data_pagamento) }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full"
                                      :class="badgeFor(r.status).cls">{{ badgeFor(r.status).label }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <Link :href="route('ristorni.show', r.id)"
                                      class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    <EyeIcon class="w-5 h-5 inline" />
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="ristorni.data.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                Nessun ristorno registrato.
                                <Link :href="route('ristorni.create')" class="text-indigo-600 hover:underline ml-1">
                                    Deliberare il primo ristorno?
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginazione semplice -->
            <div v-if="ristorni.links" class="flex gap-1 justify-center">
                <Link v-for="(link, i) in ristorni.links" :key="i"
                      :href="link.url || ''"
                      :class="[
                          'px-3 py-1 rounded text-sm border',
                          link.active
                              ? 'bg-indigo-600 text-white border-indigo-600'
                              : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600',
                          !link.url ? 'opacity-40 pointer-events-none' : ''
                      ]"
                      v-html="link.label" />
            </div>
        </div>
    </AppLayout>
</template>
