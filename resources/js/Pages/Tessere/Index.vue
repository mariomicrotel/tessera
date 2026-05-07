<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { PlusIcon, CheckCircleIcon, ArrowPathIcon, TrashIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tessere: Object,
    filters: Object,
    anni:    Array,
});

const page = usePage();
const canManage = computed(() => ['admin', 'segreteria'].some(r => page.props?.userRoles?.includes(r)));
const isAdmin   = computed(() => page.props?.userRoles?.includes('admin'));

/* ── Filtri ─────────────────────────────────────────────────────────────── */
const annoFiltro  = ref(props.filters.anno);
const statoFiltro = ref(props.filters.stato);
const searchFiltro = ref(props.filters.search);

const applyFilters = () => {
    router.get(route('tessere.index'), {
        anno:   annoFiltro.value,
        stato:  statoFiltro.value,
        search: searchFiltro.value,
    }, { preserveState: true, replace: true });
};

/* ── Crea tessera singola ────────────────────────────────────────────────── */
const showCreaForm = ref(false);
const creaForm = ref({
    member_id:      '',
    anno:           new Date().getFullYear(),
    data_emissione: new Date().toISOString().slice(0, 10),
    data_scadenza:  '',
    stato:          'emessa',
    note:           '',
});
const submitCrea = () => {
    router.post(route('tessere.store-singola'), creaForm.value, {
        preserveScroll: true,
        onSuccess: () => { showCreaForm.value = false; },
    });
};

/* ── Emetti bozze in bulk ────────────────────────────────────────────────── */
const showEmettiForm = ref(false);
const emettiForm = ref({
    anno:           props.filters.anno,
    data_emissione: new Date().toISOString().slice(0, 10),
    data_scadenza:  '',
});
const submitEmetti = () => {
    router.post(route('tessere.emetti-bulk'), emettiForm.value, {
        preserveScroll: true,
        onSuccess: () => { showEmettiForm.value = false; },
    });
};

/* ── Aggiorna scadute ────────────────────────────────────────────────────── */
const aggiornaScadute = () => {
    if (!confirm('Marcare come "scaduta" tutte le tessere emesse con data scadenza nel passato?')) return;
    router.post(route('tessere.aggiorna-scadute'), {}, { preserveScroll: true });
};

/* ── Edit inline ─────────────────────────────────────────────────────────── */
const editId   = ref(null);
const editForm = ref({});
const openEdit = (t) => {
    editId.value   = t.id;
    editForm.value = {
        data_emissione: t.data_emissione ?? '',
        data_scadenza:  t.data_scadenza  ?? '',
        stato:          t.stato,
        note:           t.note ?? '',
    };
};
const submitEdit = (id) => {
    router.put(route('tessere.update', id), editForm.value, {
        preserveScroll: true,
        onSuccess: () => { editId.value = null; },
    });
};

/* ── Elimina ─────────────────────────────────────────────────────────────── */
const deleteTessera = (t) => {
    if (!confirm(`Eliminare la tessera ${t.numero}?`)) return;
    router.delete(route('tessere.destroy', t.id), { preserveScroll: true });
};

/* ── Badge stato ─────────────────────────────────────────────────────────── */
const statoBadge = (stato) => ({
    bozza:    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    emessa:   'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    scaduta:  'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    revocata: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
}[stato] ?? 'bg-gray-100 text-gray-700');

const statoLabel = (stato) => ({ bozza: 'Bozza', emessa: 'Emessa', scaduta: 'Scaduta', revocata: 'Revocata' }[stato] ?? stato);

const fmt = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const flash = computed(() => page.props?.flash ?? {});
</script>

<template>
    <AppLayout title="Tessere">
        <Head title="Tessere" />
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Gestione Tessere</h2>
                <div v-if="canManage" class="flex gap-2 flex-wrap">
                    <SecondaryButton type="button" @click="aggiornaScadute">
                        <ArrowPathIcon class="size-4 me-1" />Aggiorna scadute
                    </SecondaryButton>
                    <SecondaryButton type="button" @click="showEmettiForm = !showEmettiForm">
                        <CheckCircleIcon class="size-4 me-1" />Emetti bozze
                    </SecondaryButton>
                    <PrimaryButton type="button" @click="showCreaForm = !showCreaForm">
                        <PlusIcon class="size-4 me-1" />Nuova tessera
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-6xl mx-auto sm:px-6 space-y-4">

            <!-- Flash -->
            <div v-if="flash.success" class="rounded-md bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                {{ flash.success }}
            </div>

            <!-- Form: nuova tessera singola -->
            <div v-if="showCreaForm && canManage" class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 space-y-3">
                <h3 class="font-medium text-gray-900 dark:text-gray-100">Nuova tessera</h3>
                <form @submit.prevent="submitCrea" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <InputLabel for="crea_member_id" value="ID Socio *" />
                        <TextInput id="crea_member_id" v-model="creaForm.member_id" type="number" class="mt-1 block w-full" required placeholder="ID del socio" />
                    </div>
                    <div>
                        <InputLabel for="crea_anno" value="Anno *" />
                        <TextInput id="crea_anno" v-model.number="creaForm.anno" type="number" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <InputLabel for="crea_stato" value="Stato *" />
                        <select id="crea_stato" v-model="creaForm.stato" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                            <option value="bozza">Bozza</option>
                            <option value="emessa">Emessa</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="crea_emissione" value="Data emissione" />
                        <TextInput id="crea_emissione" v-model="creaForm.data_emissione" type="date" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="crea_scadenza" value="Data scadenza" />
                        <TextInput id="crea_scadenza" v-model="creaForm.data_scadenza" type="date" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="crea_note" value="Note" />
                        <TextInput id="crea_note" v-model="creaForm.note" class="mt-1 block w-full" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3 flex gap-2">
                        <PrimaryButton type="submit">Crea</PrimaryButton>
                        <SecondaryButton type="button" @click="showCreaForm = false">Annulla</SecondaryButton>
                    </div>
                </form>
            </div>

            <!-- Form: emetti bozze bulk -->
            <div v-if="showEmettiForm && canManage" class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 space-y-3">
                <h3 class="font-medium text-gray-900 dark:text-gray-100">Emetti tutte le bozze dell'anno</h3>
                <form @submit.prevent="submitEmetti" class="flex flex-wrap gap-3 items-end">
                    <div>
                        <InputLabel for="em_anno" value="Anno *" />
                        <TextInput id="em_anno" v-model.number="emettiForm.anno" type="number" class="mt-1 w-28" required />
                    </div>
                    <div>
                        <InputLabel for="em_emissione" value="Data emissione *" />
                        <TextInput id="em_emissione" v-model="emettiForm.data_emissione" type="date" class="mt-1 w-40" required />
                    </div>
                    <div>
                        <InputLabel for="em_scadenza" value="Data scadenza" />
                        <TextInput id="em_scadenza" v-model="emettiForm.data_scadenza" type="date" class="mt-1 w-40" />
                    </div>
                    <div class="flex gap-2">
                        <PrimaryButton type="submit">Emetti</PrimaryButton>
                        <SecondaryButton type="button" @click="showEmettiForm = false">Annulla</SecondaryButton>
                    </div>
                </form>
            </div>

            <!-- Filtri -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <div>
                        <InputLabel for="f_anno" value="Anno" />
                        <select id="f_anno" v-model.number="annoFiltro" class="mt-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm" @change="applyFilters">
                            <option v-for="a in anni" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="f_stato" value="Stato" />
                        <select id="f_stato" v-model="statoFiltro" class="mt-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm" @change="applyFilters">
                            <option value="">Tutti</option>
                            <option value="bozza">Bozza</option>
                            <option value="emessa">Emessa</option>
                            <option value="scaduta">Scaduta</option>
                            <option value="revocata">Revocata</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[160px]">
                        <InputLabel for="f_search" value="Cerca" />
                        <TextInput id="f_search" v-model="searchFiltro" class="mt-1 block w-full" placeholder="Numero, cognome, CF..." @keyup.enter="applyFilters" />
                    </div>
                    <SecondaryButton type="button" @click="applyFilters">Filtra</SecondaryButton>
                </div>
            </div>

            <!-- Tabella -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">N°</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Socio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Stato</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Emissione</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Scadenza</th>
                            <th v-if="canManage" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <template v-if="tessere.data.length">
                            <template v-for="t in tessere.data" :key="t.id">
                                <!-- Riga normale -->
                                <tr v-if="editId !== t.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-2 font-mono font-medium">{{ t.numero }}</td>
                                    <td class="px-4 py-2">
                                        <span v-if="t.member">{{ t.member.cognome }} {{ t.member.nome }}</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-0.5 rounded text-xs" :class="statoBadge(t.stato)">{{ statoLabel(t.stato) }}</span>
                                    </td>
                                    <td class="px-4 py-2">{{ fmt(t.data_emissione) }}</td>
                                    <td class="px-4 py-2">{{ fmt(t.data_scadenza) }}</td>
                                    <td v-if="canManage" class="px-4 py-2 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline text-xs inline-flex items-center gap-1" @click="openEdit(t)">
                                                <PencilSquareIcon class="size-3.5" />Modifica
                                            </button>
                                            <button v-if="isAdmin && t.stato === 'bozza'" type="button" class="text-red-600 dark:text-red-400 hover:underline text-xs inline-flex items-center gap-1" @click="deleteTessera(t)">
                                                <TrashIcon class="size-3.5" />Elimina
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Riga edit inline -->
                                <tr v-else class="bg-indigo-50 dark:bg-indigo-900/10">
                                    <td class="px-4 py-2 font-mono font-medium">{{ t.numero }}</td>
                                    <td class="px-4 py-2">
                                        <span v-if="t.member">{{ t.member.cognome }} {{ t.member.nome }}</span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <select v-model="editForm.stato" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs py-0.5">
                                            <option value="bozza">Bozza</option>
                                            <option value="emessa">Emessa</option>
                                            <option value="scaduta">Scaduta</option>
                                            <option value="revocata">Revocata</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input v-model="editForm.data_emissione" type="date" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs py-0.5 w-32" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <input v-model="editForm.data_scadenza" type="date" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs py-0.5 w-32" />
                                    </td>
                                    <td v-if="canManage" class="px-4 py-2 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline text-xs" @click="submitEdit(t.id)">Salva</button>
                                            <button type="button" class="text-gray-500 dark:text-gray-400 hover:underline text-xs" @click="editId = null">Annulla</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </template>
                        <tr v-else>
                            <td :colspan="canManage ? 6 : 5" class="px-4 py-6 text-center text-gray-400 dark:text-gray-500 text-sm">
                                Nessuna tessera trovata per i filtri selezionati.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Paginazione -->
                <div v-if="tessere.last_page > 1" class="px-4 py-3 flex gap-2 flex-wrap border-t border-gray-200 dark:border-gray-700">
                    <a v-for="link in tessere.links" :key="link.label"
                       :href="link.url ?? '#'"
                       class="px-2 py-1 text-xs rounded border"
                       :class="link.active
                           ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                           : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/30'"
                       v-html="link.label"
                       @click.prevent="link.url && router.visit(link.url)"
                    />
                </div>
            </div>

        </div>
    </AppLayout>
</template>
