<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    PlusIcon, PencilIcon, TrashIcon,
    CheckCircleIcon, ArrowPathIcon, BoltIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    ratei:    Array,
    conti_ce: Array,
    conti_sp: Array,
    anno:     Number,
    anni:     Array,
    tipi:     Array,
});

// ── Navigazione anno ──────────────────────────────────────────────────
const changeAnno = (a) => {
    router.get(route('ratei-risconti.index', route().params.tenant), { anno: a });
};

// ── Labels ────────────────────────────────────────────────────────────
const labelTipo = (t) => ({
    rateo_attivo:     'Rateo attivo',
    rateo_passivo:    'Rateo passivo',
    risconto_attivo:  'Risconto attivo',
    risconto_passivo: 'Risconto passivo',
})[t] ?? t;

const badgeClass = (stato) => ({
    da_registrare: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    registrato:    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    stornato:      'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
})[stato] ?? '';

const badgeTipoClass = (tipo) => tipo.startsWith('risconto')
    ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
    : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';

const formatDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';
const formatEuro = (n) => n != null
    ? new Intl.NumberFormat('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
    : '—';

// ── Totali per tipo ───────────────────────────────────────────────────
const totali = computed(() => {
    const t = { rateo_attivo: 0, rateo_passivo: 0, risconto_attivo: 0, risconto_passivo: 0 };
    for (const r of props.ratei) {
        if (r.stato !== 'stornato') t[r.tipo] = (t[r.tipo] ?? 0) + r.quota_esercizio;
    }
    return t;
});

// ── Form crea/modifica ────────────────────────────────────────────────
const showModal = ref(false);
const editingId = ref(null);

const emptyForm = () => ({
    anno_esercizio:     props.anno,
    tipo:               'rateo_passivo',
    descrizione:        '',
    importo_totale:     '',
    quota_esercizio:    '',
    data_inizio:        '',
    data_fine:          '',
    conto_economico_id: null,
    conto_rettifica_id: null,
    note:               '',
});

const form = useForm(emptyForm());

const apriCrea = () => {
    editingId.value = null;
    form.reset();
    Object.assign(form, emptyForm());
    showModal.value = true;
};

const apriModifica = (r) => {
    editingId.value = r.id;
    form.anno_esercizio     = r.anno_esercizio;
    form.tipo               = r.tipo;
    form.descrizione        = r.descrizione;
    form.importo_totale     = r.importo_totale;
    form.quota_esercizio    = r.quota_esercizio;
    form.data_inizio        = r.data_inizio;
    form.data_fine          = r.data_fine;
    form.conto_economico_id = r.conto_economico_id;
    form.conto_rettifica_id = r.conto_rettifica_id;
    form.note               = r.note ?? '';
    showModal.value = true;
};

const salva = () => {
    if (editingId.value) {
        form.put(route('ratei-risconti.update', [route().params.tenant, editingId.value]), {
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        form.post(route('ratei-risconti.store', route().params.tenant), {
            onSuccess: () => { showModal.value = false; },
        });
    }
};

// ── Azioni ────────────────────────────────────────────────────────────
const registra = (r) => {
    if (!confirm(`Generare la scrittura contabile per "${r.descrizione}"?`)) return;
    router.post(route('ratei-risconti.registra', [route().params.tenant, r.id]));
};

const storna = (r) => {
    if (!confirm(`Generare lo storno di "${r.descrizione}" all'01/01/${r.anno_esercizio + 1}?`)) return;
    router.post(route('ratei-risconti.storna', [route().params.tenant, r.id]));
};

const elimina = (r) => {
    if (!confirm(`Eliminare "${r.descrizione}"?`)) return;
    router.delete(route('ratei-risconti.destroy', [route().params.tenant, r.id]));
};

// ── Batch ─────────────────────────────────────────────────────────────
const registraBatch = () => {
    const da = props.ratei.filter(r => r.stato === 'da_registrare').length;
    if (!confirm(`Registrare tutti i ${da} ratei/risconti in attesa per il ${props.anno}?`)) return;
    router.post(route('ratei-risconti.batch', route().params.tenant), { anno: props.anno });
};

const daRegistrareCount = computed(() => props.ratei.filter(r => r.stato === 'da_registrare').length);
</script>

<template>
    <AppLayout title="Ratei e Risconti">
        <Head title="Ratei e Risconti" />

        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Ratei e Risconti
                </h2>
                <div class="flex gap-2 flex-wrap">
                    <!-- Filtro anno -->
                    <select
                        :value="anno"
                        @change="changeAnno(Number($event.target.value))"
                        class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm"
                    >
                        <option v-for="a in anni" :key="a" :value="a">{{ a }}</option>
                    </select>

                    <!-- Registra batch -->
                    <SecondaryButton
                        v-if="daRegistrareCount > 0"
                        @click="registraBatch"
                    >
                        <BoltIcon class="size-4 me-1" />
                        Registra tutti ({{ daRegistrareCount }})
                    </SecondaryButton>

                    <PrimaryButton @click="apriCrea">
                        <PlusIcon class="size-4 me-1" /> Nuovo
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Riepilogo totali -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div v-for="[tipo, label] in [
                        ['rateo_attivo', 'Ratei Attivi'],
                        ['rateo_passivo', 'Ratei Passivi'],
                        ['risconto_attivo', 'Risconti Attivi'],
                        ['risconto_passivo', 'Risconti Passivi'],
                    ]" :key="tipo"
                        class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center"
                    >
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ label }}</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            € {{ formatEuro(totali[tipo]) }}
                        </p>
                    </div>
                </div>

                <!-- Info box -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 text-sm text-blue-800 dark:text-blue-200">
                    <strong>Flusso:</strong>
                    (1) Crea il rateo/risconto con i conti e il periodo di competenza →
                    (2) Clicca <em>Registra</em> per generare la scrittura contabile a fine esercizio →
                    (3) Clicca <em>Storna</em> per generare il contro-giroconto al 01/01 dell'anno successivo.
                </div>

                <!-- Tabella -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Tipo</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Descrizione</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Periodo</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Quota</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Conti</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Stato</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="!ratei.length">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    Nessun rateo/risconto per l'anno {{ anno }}.
                                </td>
                            </tr>
                            <tr v-for="r in ratei" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <!-- Tipo -->
                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', badgeTipoClass(r.tipo)]">
                                        {{ labelTipo(r.tipo) }}
                                    </span>
                                </td>
                                <!-- Descrizione -->
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100 max-w-xs truncate">
                                    {{ r.descrizione }}
                                </td>
                                <!-- Periodo -->
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ formatDate(r.data_inizio) }} — {{ formatDate(r.data_fine) }}
                                </td>
                                <!-- Quota -->
                                <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-gray-100">
                                    € {{ formatEuro(r.quota_esercizio) }}
                                </td>
                                <!-- Conti -->
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                    <div>CE: {{ r.conto_economico }}</div>
                                    <div>SP: {{ r.conto_rettifica }}</div>
                                </td>
                                <!-- Stato -->
                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', badgeClass(r.stato)]">
                                        {{ r.stato.replace('_', ' ') }}
                                    </span>
                                </td>
                                <!-- Azioni -->
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-1 flex-wrap">
                                        <!-- Modifica (solo da_registrare) -->
                                        <SecondaryButton
                                            v-if="r.stato === 'da_registrare'"
                                            class="text-xs py-1 px-2"
                                            @click="apriModifica(r)"
                                            title="Modifica"
                                        >
                                            <PencilIcon class="size-4" />
                                        </SecondaryButton>

                                        <!-- Registra (solo da_registrare) -->
                                        <PrimaryButton
                                            v-if="r.stato === 'da_registrare'"
                                            class="text-xs py-1 px-2 bg-green-600 hover:bg-green-700"
                                            @click="registra(r)"
                                            title="Registra scrittura contabile"
                                        >
                                            <CheckCircleIcon class="size-4 me-1" /> Registra
                                        </PrimaryButton>

                                        <!-- Storna (solo registrato) -->
                                        <SecondaryButton
                                            v-if="r.stato === 'registrato'"
                                            class="text-xs py-1 px-2"
                                            @click="storna(r)"
                                            title="Genera storno anno successivo"
                                        >
                                            <ArrowPathIcon class="size-4 me-1" /> Storna
                                        </SecondaryButton>

                                        <!-- Elimina (solo da_registrare) -->
                                        <DangerButton
                                            v-if="r.stato === 'da_registrare'"
                                            class="text-xs py-1 px-2"
                                            @click="elimina(r)"
                                            title="Elimina"
                                        >
                                            <TrashIcon class="size-4" />
                                        </DangerButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: crea / modifica -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl overflow-y-auto max-h-[90vh]">
                    <div class="p-6 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ editingId ? 'Modifica rateo/risconto' : 'Nuovo rateo/risconto' }}
                        </h3>

                        <form @submit.prevent="salva" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Anno -->
                                <div v-if="!editingId">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anno esercizio</label>
                                    <input v-model.number="form.anno_esercizio" type="number"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm"
                                        required />
                                </div>

                                <!-- Tipo -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                                    <select v-model="form.tipo"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm"
                                        :disabled="!!editingId">
                                        <option v-for="t in tipi" :key="t" :value="t">{{ labelTipo(t) }}</option>
                                    </select>
                                    <p v-if="form.errors.tipo" class="mt-1 text-xs text-red-600">{{ form.errors.tipo }}</p>
                                </div>
                            </div>

                            <!-- Descrizione -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descrizione</label>
                                <input v-model="form.descrizione" type="text" maxlength="250"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm"
                                    required />
                                <p v-if="form.errors.descrizione" class="mt-1 text-xs text-red-600">{{ form.errors.descrizione }}</p>
                            </div>

                            <!-- Date -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Inizio competenza</label>
                                    <input v-model="form.data_inizio" type="date"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm"
                                        required />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fine competenza</label>
                                    <input v-model="form.data_fine" type="date"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm"
                                        required />
                                </div>
                            </div>

                            <!-- Importi -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Importo totale
                                        <span class="text-gray-400 font-normal">(opz.)</span>
                                    </label>
                                    <input v-model="form.importo_totale" type="number" step="0.01" min="0"
                                        placeholder="Importo contratto intero"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Quota esercizio
                                        <span class="text-gray-400 font-normal">(se vuoto: calcolata)</span>
                                    </label>
                                    <input v-model="form.quota_esercizio" type="number" step="0.01" min="0.01"
                                        placeholder="Lascia vuoto per calcolo auto"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm" />
                                    <p v-if="form.errors.quota_esercizio" class="mt-1 text-xs text-red-600">{{ form.errors.quota_esercizio }}</p>
                                </div>
                            </div>

                            <!-- Conto CE -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Conto Economico <span class="text-gray-400 font-normal">(costo o ricavo)</span>
                                </label>
                                <select v-model="form.conto_economico_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm"
                                    required>
                                    <option :value="null">— Seleziona —</option>
                                    <option v-for="c in conti_ce" :key="c.id" :value="c.id">
                                        {{ c.codice }} — {{ c.descrizione }}
                                    </option>
                                </select>
                                <p v-if="form.errors.conto_economico_id" class="mt-1 text-xs text-red-600">{{ form.errors.conto_economico_id }}</p>
                            </div>

                            <!-- Conto SP rettifica -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Conto Patrimoniale rettifica <span class="text-gray-400 font-normal">(rateo/risconto SP)</span>
                                </label>
                                <select v-model="form.conto_rettifica_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm"
                                    required>
                                    <option :value="null">— Seleziona —</option>
                                    <option v-for="c in conti_sp" :key="c.id" :value="c.id">
                                        {{ c.codice }} — {{ c.descrizione }}
                                    </option>
                                </select>
                                <p v-if="form.errors.conto_rettifica_id" class="mt-1 text-xs text-red-600">{{ form.errors.conto_rettifica_id }}</p>
                            </div>

                            <!-- Note -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                                <textarea v-model="form.note" rows="2"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm" />
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <SecondaryButton type="button" @click="showModal = false">Annulla</SecondaryButton>
                                <PrimaryButton type="submit" :disabled="form.processing">
                                    {{ editingId ? 'Aggiorna' : 'Crea' }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
