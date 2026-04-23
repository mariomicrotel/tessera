<script setup>
import { PlusIcon, PencilSquareIcon, TrashIcon, FunnelIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    conti: Object,
    filters: Object,
    anno: Number,
    anniDisponibili: Array,
    liquiditaTotale: Number,
});

const form = reactive({
    search: props.filters?.search ?? '',
    anno:   String(props.anno ?? new Date().getFullYear()),
});

const search = () => router.get(route('conti.index'), form, { preserveState: true, replace: true });

function tipoLabel(type) {
    return { cassa: 'Cassa', banca: 'Banca', altro: 'Altro' }[type] ?? type;
}

function tipoClass(type) {
    return {
        cassa: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        banca: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        altro: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    }[type] ?? '';
}

function canDelete(c) {
    return (c.movimenti_count ?? 0) === 0 && (c.incassi_count ?? 0) === 0;
}
function deleteConto(conto) {
    if (!canDelete(conto)) return;
    if (!confirm('Eliminare il conto "' + conto.name + '"?')) return;
    router.delete(route('conti.destroy', conto.id));
}

function fmtEur(val) {
    return '€\u00a0' + Number(val ?? 0).toFixed(2);
}

function saldoClass(val) {
    const n = Number(val ?? 0);
    if (n > 0) return 'text-green-600 dark:text-green-400 font-semibold';
    if (n < 0) return 'text-red-600 dark:text-red-400 font-semibold';
    return 'text-gray-500 dark:text-gray-400';
}
</script>

<template>
    <AppLayout title="Conti tesoreria">
        <Head title="Conti tesoreria" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Conti tesoreria</h2>
                <Link :href="route('conti.create')">
                    <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuovo conto</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Filtri -->
                <form @submit.prevent="search" class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cerca (nome o codice)</label>
                        <TextInput v-model="form.search" type="text" class="block w-full" placeholder="Nome, codice…" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Saldo per anno</label>
                        <select
                            v-model="form.anno"
                            @change="search"
                            class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                        >
                            <option v-for="a in anniDisponibili" :key="a" :value="String(a)">{{ a }}</option>
                        </select>
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>

                <!-- Card liquidità totale -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Liquidità totale (cassa + banca)</p>
                        <p
                            class="mt-1 text-2xl font-bold"
                            :class="(liquiditaTotale ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ fmtEur(liquiditaTotale) }}
                        </p>
                    </div>
                    <div class="text-xs text-gray-400 dark:text-gray-500 text-right">
                        Saldo totale<br>tutti i movimenti
                    </div>
                </div>

                <!-- Tabella conti -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nome</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Codice</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">IBAN</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Saldo {{ anno }}
                                </th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Saldo totale</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Mov.</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="c in (conti.data || conti)"
                                :key="c.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ c.name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ c.code || '—' }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="tipoClass(c.type)">
                                        {{ tipoLabel(c.type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm font-mono text-gray-600 dark:text-gray-400">
                                    {{ c.type === 'banca' ? (c.iban || '—') : '—' }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                        :class="c.attivo
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                    >
                                        {{ c.attivo ? 'Attivo' : 'Inattivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right text-sm" :class="saldoClass(c.saldo_anno)">
                                    {{ fmtEur(c.saldo_anno) }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm" :class="saldoClass(c.saldo_totale)">
                                    {{ fmtEur(c.saldo_totale) }}
                                </td>
                                <td class="px-4 py-2 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ c.movimenti_count ?? 0 }}
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <Link
                                        :href="route('conti.edit', c.id)"
                                        class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline me-2"
                                    >
                                        <PencilSquareIcon class="size-4" aria-hidden="true" />Modifica
                                    </Link>
                                    <button
                                        v-if="canDelete(c)"
                                        type="button"
                                        class="inline-flex items-center gap-1 text-sm text-red-600 dark:text-red-400 hover:underline"
                                        @click="deleteConto(c)"
                                    >
                                        <TrashIcon class="size-4" aria-hidden="true" />Elimina
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="!(conti.data || conti)?.length" class="px-4 py-8 text-center text-gray-500">
                        Nessun conto.
                        <Link :href="route('conti.create')" class="text-indigo-600 dark:text-indigo-400 hover:underline">Aggiungi il primo conto</Link>.
                    </p>

                    <div v-if="conti.prev_page_url || conti.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="conti.prev_page_url" :href="conti.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                            <ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro
                        </Link>
                        <span v-else></span>
                        <Link v-if="conti.next_page_url" :href="conti.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                            Avanti<ArrowRightIcon class="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
