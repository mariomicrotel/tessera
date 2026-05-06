<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    PlusIcon, FunnelIcon, PencilSquareIcon, TrashIcon,
    CheckCircleIcon, ArrowPathIcon, ChevronLeftIcon, ChevronRightIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    scadenze: Object,
    tipi:     Object,
    stati:    Object,
    conti:    Array,
    filters:  Object,
});

const form = reactive({
    tipo:   props.filters?.tipo   ?? '',
    stato:  props.filters?.stato  ?? '',
    dal:    props.filters?.dal    ?? '',
    al:     props.filters?.al     ?? '',
    search: props.filters?.search ?? '',
});

const filter = () => router.get(route('scadenze.index'), form);

const destroy = (scadenza) => {
    if (confirm(`Eliminare la scadenza "${scadenza.descrizione}"?`)) {
        router.delete(route('scadenze.destroy', scadenza));
    }
};

// ── Modal "Marca come pagata" ──────────────────────────────────────────────
const pagataModal = ref(null);
const pagataForm = reactive({ data_pagamento: '', conto_id: '' });
const pagataProcessing = ref(false);

const openPagataModal = (scadenza) => {
    pagataModal.value = scadenza;
    pagataForm.data_pagamento = new Date().toISOString().substring(0, 10);
    pagataForm.conto_id = scadenza.conto_id ?? '';
};

const submitPagata = () => {
    pagataProcessing.value = true;
    router.post(route('scadenze.mark-pagata', pagataModal.value), pagataForm, {
        onFinish: () => {
            pagataProcessing.value = false;
            pagataModal.value = null;
        },
    });
};

const badgeClass = (stato) => {
    if (stato === 'pagata')  return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    if (stato === 'sospesa') return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
    return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
};

const formatDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const formatEur  = (n) => n != null ? Number(n).toLocaleString('it-IT', { style: 'currency', currency: 'EUR' }) : '—';
</script>

<template>
    <AppLayout title="Scadenzario">
        <Head title="Scadenzario" />
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Scadenzario</h2>
                <div class="flex gap-2">
                    <Link :href="route('scadenze.dashboard')">
                        <SecondaryButton>Dashboard</SecondaryButton>
                    </Link>
                    <Link :href="route('scadenze.fornitori')">
                        <SecondaryButton>Fornitori</SecondaryButton>
                    </Link>
                    <Link :href="route('scadenze.clienti')">
                        <SecondaryButton>Clienti</SecondaryButton>
                    </Link>
                    <Link :href="route('scadenze.create')">
                        <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuova scadenza</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Filtri -->
                <form @submit.prevent="filter" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Tipo</label>
                        <select v-model="form.tipo" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                            <option value="">Tutti</option>
                            <option v-for="(label, key) in tipi" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Stato</label>
                        <select v-model="form.stato" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                            <option value="">Tutti</option>
                            <option v-for="(label, key) in stati" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Dal</label>
                        <input v-model="form.dal" type="date" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Al</label>
                        <input v-model="form.al" type="date" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>
                    <div class="flex-1 min-w-[160px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Cerca</label>
                        <input v-model="form.search" type="text" placeholder="Descrizione o riferimento…" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-1" />Filtra</PrimaryButton>
                </form>

                <!-- Ripresa anno precedente -->
                <div class="flex justify-end">
                    <SecondaryButton type="button"
                        @click="() => { if (confirm('Creare le scadenze ricorrenti per l\'anno corrente dall\'anno precedente?')) { router.post(route('scadenze.riprendi')); } }">
                        <ArrowPathIcon class="size-4 me-2" />Riprendi scadenze anno precedente
                    </SecondaryButton>
                </div>

                <!-- Tabella -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Scadenza</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Tipo</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Descrizione</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Importo</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Stato</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Riferimento</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="!scadenze.data.length">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    Nessuna scadenza trovata.
                                </td>
                            </tr>
                            <tr v-for="s in scadenze.data" :key="s.id"
                                :class="['hover:bg-gray-50 dark:hover:bg-gray-750',
                                         s.is_scaduta ? 'bg-red-50 dark:bg-red-900/10' :
                                         (s.giorni_alla_scadenza <= 7 && s.stato === 'aperta') ? 'bg-orange-50 dark:bg-orange-900/10' : '']">
                                <td class="px-4 py-3 whitespace-nowrap font-medium"
                                    :class="s.is_scaduta ? 'text-red-700 dark:text-red-400' :
                                            (s.giorni_alla_scadenza <= 7 && s.stato === 'aperta') ? 'text-orange-700 dark:text-orange-400' :
                                            'text-gray-900 dark:text-gray-100'">
                                    {{ formatDate(s.data_scadenza) }}
                                    <span v-if="s.is_scaduta" class="ml-1 text-xs text-red-500">(scaduta)</span>
                                    <span v-else-if="s.giorni_alla_scadenza <= 7 && s.stato === 'aperta'"
                                          class="ml-1 text-xs text-orange-500">({{ s.giorni_alla_scadenza }}gg)</span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 capitalize">{{ tipi[s.tipo] ?? s.tipo }}</td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">
                                    {{ s.descrizione }}
                                    <span v-if="s.ricorrente" class="ml-1 text-xs text-indigo-500" title="Ricorrente">↺</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-200">{{ formatEur(s.importo) }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', badgeClass(s.stato)]">
                                        {{ stati[s.stato] ?? s.stato }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ s.riferimento ?? '—' }}</td>
                                <td class="px-4 py-3 text-center whitespace-nowrap space-x-2">
                                    <button v-if="s.stato === 'aperta'"
                                            @click="openPagataModal(s)"
                                            title="Marca come pagata"
                                            class="inline-flex items-center text-green-600 hover:text-green-800 dark:text-green-400">
                                        <CheckCircleIcon class="size-4" />
                                    </button>
                                    <Link :href="route('scadenze.edit', s)"
                                          title="Modifica"
                                          class="inline-flex items-center text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                        <PencilSquareIcon class="size-4" />
                                    </Link>
                                    <button @click="destroy(s)"
                                            title="Elimina"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400">
                                        <TrashIcon class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Paginazione -->
                    <div v-if="scadenze.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Pagina {{ scadenze.current_page }} di {{ scadenze.last_page }}
                        </span>
                        <div class="flex gap-1">
                            <Link v-if="scadenze.prev_page_url" :href="scadenze.prev_page_url">
                                <SecondaryButton class="py-1"><ChevronLeftIcon class="size-4" /></SecondaryButton>
                            </Link>
                            <Link v-if="scadenze.next_page_url" :href="scadenze.next_page_url">
                                <SecondaryButton class="py-1"><ChevronRightIcon class="size-4" /></SecondaryButton>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Marca come pagata -->
        <Teleport to="body">
            <div v-if="pagataModal"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                 @click.self="pagataModal = null">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Marca come pagata</h3>
                        <button @click="pagataModal = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <XMarkIcon class="size-5" />
                        </button>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ pagataModal.descrizione }}</span>
                        <span v-if="pagataModal.importo"> — {{ formatEur(pagataModal.importo) }}</span>
                    </p>
                    <form @submit.prevent="submitPagata" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data pagamento *</label>
                            <input v-model="pagataForm.data_pagamento" type="date" required
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm" />
                        </div>
                        <div v-if="conti.length > 0">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conto utilizzato</label>
                            <select v-model="pagataForm.conto_id"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <option value="">— Nessuno —</option>
                                <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <SecondaryButton type="button" @click="pagataModal = null">Annulla</SecondaryButton>
                            <PrimaryButton type="submit" :disabled="pagataProcessing">
                                <CheckCircleIcon class="size-4 me-2" />Conferma pagamento
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
