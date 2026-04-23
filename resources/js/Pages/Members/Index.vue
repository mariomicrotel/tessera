<script setup>
import { PlusIcon, FunnelIcon, PencilSquareIcon, ArrowLeftIcon, ArrowRightIcon, EnvelopeIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import { reactive, ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    members: Object,
    memberTypes: Array,
    filters: Object,
});

const form = reactive({
    search: props.filters?.search ?? '',
    stato: props.filters?.stato ?? '',
    member_type_id: props.filters?.member_type_id ?? '',
    in_regola: props.filters?.in_regola ?? '',
});

const selectedIds = ref([]);
const bulkSubmitting = ref(false);
const bulkInviaEmail = ref(true);

const idsOnPage = computed(() => (props.members?.data ?? []).map((m) => m.id));
const isAllOnPageSelected = computed(() => idsOnPage.value.length > 0 && idsOnPage.value.every((id) => selectedIds.value.includes(id)));
const isSomeSelected = computed(() => selectedIds.value.length > 0);

function toggleSelectAll() {
    if (isAllOnPageSelected.value) {
        selectedIds.value = selectedIds.value.filter((id) => !idsOnPage.value.includes(id));
    } else {
        const add = idsOnPage.value.filter((id) => !selectedIds.value.includes(id));
        selectedIds.value = [...selectedIds.value, ...add];
    }
}

function toggleOne(id) {
    const i = selectedIds.value.indexOf(id);
    if (i === -1) {
        selectedIds.value = [...selectedIds.value, id];
    } else {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    }
}

function bulkApprove() {
    bulkSubmitting.value = true;
    router.post(route('members.accept-admission-bulk'), {
        ids: selectedIds.value,
        invia_email: bulkInviaEmail.value,
        search: form.search,
        stato: form.stato,
        member_type_id: form.member_type_id,
        in_regola: form.in_regola,
    }, {
        preserveScroll: true,
        onFinish: () => { bulkSubmitting.value = false; },
    });
    selectedIds.value = [];
}

function clearSelection() {
    selectedIds.value = [];
}

const search = () => {
    router.get(route('members.index'), form);
};
</script>

<template>
    <AppLayout title="Soci e volontari">
        <Head title="Soci e volontari" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Soci e volontari</h2>
                <div class="flex items-center gap-2">
                    <Link :href="route('members.create')">
                        <PrimaryButton><PlusIcon class="size-4 me-2" aria-hidden="true" />Nuovo socio</PrimaryButton>
                    </Link>
                    <Link :href="route('members.invites.create')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <EnvelopeIcon class="size-4 me-2" aria-hidden="true" />Invia invito
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <form id="filter-form" @submit.prevent="search" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cerca</label>
                        <TextInput v-model="form.search" name="search" class="block w-full" placeholder="Nome, cognome, email, CF..." />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stato</label>
                        <select v-model="form.stato" name="stato" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                            <option value="">Tutti</option>
                            <option value="aspirante">Aspirante</option>
                            <option value="attivo">Attivo</option>
                            <option value="sospeso">Sospeso</option>
                            <option value="cessato">Cessato</option>
                            <option value="rigettato">Rigettato</option>
                            <option value="in_ricorso">In ricorso</option>
                            <option value="decesso">Decesso</option>
                            <option value="dimesso">Dimesso</option>
                            <option value="escluso">Escluso</option>
                            <option value="moroso">Moroso</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">In regola con la quota</label>
                        <select v-model="form.in_regola" name="in_regola" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                            <option value="">Tutti</option>
                            <option value="1">Sì</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipologia</label>
                        <select v-model="form.member_type_id" name="member_type_id" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                            <option value="">Tutte</option>
                            <option v-for="t in memberTypes" :key="t.id" :value="t.id">{{ t.display_name || t.name }}</option>
                        </select>
                    </div>
                    <PrimaryButton type="submit"><FunnelIcon class="size-4 me-2" aria-hidden="true" />Filtra</PrimaryButton>
                </form>

                <div v-if="isSomeSelected" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 px-4 py-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ selectedIds.length }} soci selezionati</span>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" v-model="bulkInviaEmail" class="rounded border-gray-300 dark:border-gray-600" />
                        Invia email di notifica
                    </label>
                    <PrimaryButton type="button" :disabled="bulkSubmitting" @click="bulkApprove">
                        <CheckCircleIcon class="size-4 me-2" aria-hidden="true" />Approva selezionati
                    </PrimaryButton>
                    <button type="button" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline" @click="clearSelection">Annulla selezione</button>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 w-10 text-left">
                                    <span class="sr-only">Seleziona</span>
                                    <input
                                        v-if="idsOnPage.length > 0"
                                        type="checkbox"
                                        :checked="isAllOnPageSelected"
                                        :indeterminate="isSomeSelected && !isAllOnPageSelected"
                                        class="rounded border-gray-300 dark:border-gray-600"
                                        aria-label="Seleziona tutti nella pagina"
                                        @change="toggleSelectAll"
                                    />
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">N. tessera</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Cognome / Nome</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipologia</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">In regola</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Email (anagrafica)</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="m in members.data" :key="m.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 w-10">
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(m.id)"
                                        class="rounded border-gray-300 dark:border-gray-600"
                                        :aria-label="'Seleziona ' + m.cognome + ' ' + m.nome"
                                        @change="toggleOne(m.id)"
                                    />
                                </td>
                                <td class="px-4 py-2 text-sm">{{ m.numero_tessera ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <Link :href="route('members.show', m.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ m.cognome }} {{ m.nome }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ m.member_type?.display_name || m.member_type?.name }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 rounded text-xs" :class="{
                                        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': m.stato === 'attivo',
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': m.stato === 'aspirante',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400': m.stato === 'in_ricorso',
                                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': ['rigettato', 'escluso', 'decesso'].includes(m.stato),
                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': !['attivo', 'aspirante', 'in_ricorso', 'rigettato', 'escluso', 'decesso'].includes(m.stato)
                                    }">{{ m.stato }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 rounded text-xs" :class="m.in_regola_con_quota ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'">{{ m.in_regola_con_quota ? 'Sì' : 'No' }}</span>
                                </td>
                                <td class="px-4 py-2 text-sm">{{ m.email || '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="route('members.edit', m.id)" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline mr-2"><PencilSquareIcon class="size-4" aria-hidden="true" />Modifica</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="members.data?.length === 0" class="px-4 py-8 text-center text-gray-500">Nessun socio trovato.</div>
                    <div v-if="members.prev_page_url || members.next_page_url" class="px-4 py-2 border-t flex justify-between">
                        <Link v-if="members.prev_page_url" :href="members.prev_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
                        <span v-else></span>
                        <Link v-if="members.next_page_url" :href="members.next_page_url" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">Avanti<ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
