<script setup>
import { ref } from 'vue';
import { PlusIcon, EyeIcon, PencilSquareIcon, FunnelIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({ elezioni: Object, organi: Array, filters: Object });
const filterStato = ref(props.filters?.stato ?? '');
const filterOrganoId = ref(props.filters?.organo_id ?? '');

function applicaFiltro() {
    const q = {};
    if (filterStato.value) q.stato = filterStato.value;
    if (filterOrganoId.value) q.organo_id = filterOrganoId.value;
    router.get(route('elezioni.index'), q, { preserveState: true });
}

function statoLabel(e) {
    if (e.invalidata_at) return 'Invalidata';
    if (e.stato === 'bozza') return 'Bozza';
    if (e.stato === 'aperta') return 'Aperta';
    if (e.stato === 'chiusa') return 'Chiusa';
    return e.stato;
}
</script>

<template>
    <AppLayout title="Elezioni">
        <Head title="Elezioni" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Elezioni</h2>
                <Link :href="route('elezioni.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuova elezione</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap gap-2 items-center">
                    <FunnelIcon class="size-5 text-gray-400" aria-hidden="true" />
                    <select v-model="filterStato" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" @change="applicaFiltro">
                        <option value="">Tutti gli stati</option>
                        <option value="bozza">Bozza</option>
                        <option value="aperta">Aperta</option>
                        <option value="chiusa">Chiusa</option>
                    </select>
                    <select v-model="filterOrganoId" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" @change="applicaFiltro">
                        <option value="">Tutti gli organi</option>
                        <option v-for="o in organi" :key="o.id" :value="o.id">{{ o.nome }}</option>
                    </select>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Titolo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Organo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="e in elezioni.data" :key="e.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 font-medium">{{ e.titolo }}</td>
                                <td class="px-4 py-2">{{ e.organo?.nome ?? '—' }}</td>
                                <td class="px-4 py-2">{{ e.data_elezione ? new Date(e.data_elezione).toLocaleDateString('it-IT') : '' }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 rounded text-xs" :class="{ 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': e.stato === 'bozza' && !e.invalidata_at, 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300': e.stato === 'aperta', 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300': e.stato === 'chiusa' && !e.invalidata_at, 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300': e.invalidata_at }">{{ statoLabel(e) }}</span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('elezioni.show', e.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline me-2">
                                        <EyeIcon class="size-4" aria-hidden="true" />Dettaglio
                                    </Link>
                                    <Link v-if="e.stato === 'chiusa'" :href="route('elezioni.risultati', e.id)" class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:underline">Risultati</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!elezioni.data?.length" class="px-4 py-8 text-center text-gray-500">Nessuna elezione.</div>
                    <div v-if="elezioni.prev_page_url || elezioni.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="elezioni.prev_page_url" :href="elezioni.prev_page_url" class="text-sm text-indigo-600 dark:text-indigo-400">Precedente</Link>
                        <span v-else></span>
                        <Link v-if="elezioni.next_page_url" :href="elezioni.next_page_url" class="text-sm text-indigo-600 dark:text-indigo-400">Successiva</Link>
                        <span v-else></span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
