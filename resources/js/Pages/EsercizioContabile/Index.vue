<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const tenant = usePage().props.tenant;
import {
    LockClosedIcon, LockOpenIcon, PlusIcon,
    ExclamationTriangleIcon, CheckCircleIcon, Cog6ToothIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    esercizi:         Array,
    conti:            Array,
    anno_corrente:    Number,
    esercizio_aperto: Number,
});

// ── Apertura nuovo esercizio ──────────────────────────────────────────────
const showAperturaModal = ref(false);
const formApertura = useForm({ anno: props.anno_corrente });

const apriEsercizio = () => {
    formApertura.post(route('esercizi.store', tenant), {
        onSuccess: () => { showAperturaModal.value = false; },
    });
};

// ── Configurazione conti di chiusura ─────────────────────────────────────
const esercizioInConfig = ref(null);
const formConfig = useForm({
    conto_chiusura_ce_id: null,
    conto_apertura_id:    null,
    note:                 '',
});

const apriConfig = (esercizio) => {
    esercizioInConfig.value = esercizio;
    // Reset e riempi la form con i dati dell'esercizio
    formConfig.reset();
    formConfig.conto_chiusura_ce_id = esercizio.conto_chiusura_ce_id ?? null;
    formConfig.conto_apertura_id    = esercizio.conto_apertura_id ?? null;
    formConfig.note                 = esercizio.note ?? '';
};

const salvaConfig = () => {
    formConfig.put(route('esercizi.update', [tenant, esercizioInConfig.value.id]), {
        onSuccess: () => {
            formConfig.reset();
            esercizioInConfig.value = null;
        },
        onError: () => {
            console.error('Errore salvataggio configurazione', formConfig.errors);
        },
    });
};

// ── Chiusura esercizio ────────────────────────────────────────────────────
const esercizioInChiusura = ref(null);

const chiudiEsercizio = (esercizio) => {
    if (!confirm(`Chiudere l'esercizio ${esercizio.anno}? L'operazione blocca tutti i movimenti e genera le scritture di chiusura. Non sarà reversibile se esiste un anno successivo chiuso.`)) {
        return;
    }
    router.post(route('esercizi.close', [tenant, esercizio.id]));
};

const riaperiEsercizio = (esercizio) => {
    if (!confirm(`Riaprire l'esercizio ${esercizio.anno}? Le scritture di chiusura generate automaticamente verranno cancellate e i movimenti sbloccati.`)) {
        return;
    }
    router.post(route('esercizi.reopen', [tenant, esercizio.id]));
};

const eliminaEsercizio = (esercizio) => {
    if (!confirm(`Eliminare l'esercizio ${esercizio.anno}?`)) return;
    router.delete(route('esercizi.destroy', [tenant, esercizio.id]));
};

// ── Helpers ───────────────────────────────────────────────────────────────
const formatDate = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '—';

const statoClass = (stato) => stato === 'chiuso'
    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';

const esercizioEsistePerAnnoCorrente = computed(() =>
    props.esercizi.some(e => e.anno === props.anno_corrente)
);

// Filtra conti adatti per il ruolo richiesto (transitorio, PN, conto_ordine)
const contiChiusuraCe = computed(() =>
    props.conti.filter(c => ['transitorio', 'patrimonio_netto', 'conto_ordine'].includes(c.natura))
);
const contiApertura = computed(() =>
    props.conti.filter(c => ['transitorio', 'conto_ordine'].includes(c.natura))
);
</script>

<template>
    <AppLayout title="Esercizi Contabili">
        <Head title="Esercizi Contabili" />

        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Esercizi Contabili
                </h2>
                <PrimaryButton v-if="!esercizioEsistePerAnnoCorrente" @click="showAperturaModal = true">
                    <PlusIcon class="size-4 me-1" /> Nuovo esercizio {{ anno_corrente }}
                </PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Info box -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 text-sm text-blue-800 dark:text-blue-200">
                    <strong>Flusso chiusura esercizio:</strong>
                    (1) Configura i conti di chiusura →
                    (2) Chiudi l'esercizio: vengono generate automaticamente le scritture di chiusura CE e SP
                    e tutti i movimenti dell'anno vengono bloccati →
                    (3) Il primo movimento dell'anno successivo è la riapertura dei saldi patrimoniali.
                </div>

                <!-- Tabella esercizi -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Anno</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Stato</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Apertura</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Chiusura</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Movimenti</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Conti chiusura</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-if="!esercizi.length">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    Nessun esercizio registrato. Crea il primo esercizio.
                                </td>
                            </tr>
                            <tr v-for="e in esercizi" :key="e.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-gray-100 text-lg">
                                    {{ e.anno }}
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statoClass(e.stato)]">
                                        <LockClosedIcon v-if="e.stato==='chiuso'" class="inline size-3 me-1" />
                                        <LockOpenIcon   v-else                   class="inline size-3 me-1" />
                                        {{ e.stato }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formatDate(e.data_apertura) }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formatDate(e.data_chiusura) }}</td>
                                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ e.num_movimenti }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 max-w-xs">
                                    <div v-if="e.configurato" class="flex items-center gap-1 text-green-700 dark:text-green-400">
                                        <CheckCircleIcon class="size-4 flex-shrink-0" />
                                        Configurato
                                    </div>
                                    <div v-else class="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                        <ExclamationTriangleIcon class="size-4 flex-shrink-0" />
                                        Conti non configurati
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-1 flex-wrap">
                                        <!-- Configura (solo esercizi aperti) -->
                                        <SecondaryButton
                                            v-if="e.stato === 'aperto'"
                                            class="text-xs py-1 px-2"
                                            @click="apriConfig(e)"
                                            title="Configura conti di chiusura"
                                        >
                                            <Cog6ToothIcon class="size-4" />
                                        </SecondaryButton>

                                        <!-- Chiudi -->
                                        <PrimaryButton
                                            v-if="e.stato === 'aperto' && e.configurato"
                                            class="text-xs py-1 px-2 bg-amber-600 hover:bg-amber-700"
                                            @click="chiudiEsercizio(e)"
                                        >
                                            <LockClosedIcon class="size-4 me-1" /> Chiudi
                                        </PrimaryButton>

                                        <!-- Riapri -->
                                        <SecondaryButton
                                            v-if="e.stato === 'chiuso'"
                                            class="text-xs py-1 px-2"
                                            @click="riaperiEsercizio(e)"
                                        >
                                            <LockOpenIcon class="size-4 me-1" /> Riapri
                                        </SecondaryButton>

                                        <!-- Elimina (solo se aperto e senza movimenti) -->
                                        <DangerButton
                                            v-if="e.stato === 'aperto' && e.num_movimenti === 0"
                                            class="text-xs py-1 px-2"
                                            @click="eliminaEsercizio(e)"
                                        >
                                            ✕
                                        </DangerButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: apertura nuovo esercizio -->
        <Teleport to="body">
            <div v-if="showAperturaModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-sm mx-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Apri nuovo esercizio</h3>
                    <form @submit.prevent="apriEsercizio" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anno</label>
                            <input
                                v-model="formApertura.anno"
                                type="number"
                                :min="2000" :max="2100"
                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm"
                                required
                            />
                            <p v-if="formApertura.errors.anno" class="mt-1 text-xs text-red-600">{{ formApertura.errors.anno }}</p>
                        </div>
                        <div class="flex justify-end gap-2">
                            <SecondaryButton type="button" @click="showAperturaModal = false">Annulla</SecondaryButton>
                            <PrimaryButton type="submit" :disabled="formApertura.processing">Apri esercizio</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal: configurazione conti di chiusura -->
        <Teleport to="body">
            <div v-if="esercizioInConfig" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-lg mx-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                        Configurazione chiusura {{ esercizioInConfig.anno }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Seleziona i conti di contropartita per le scritture automatiche.
                    </p>
                    <form @submit.prevent="salvaConfig" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Conto Riepilogo CE
                                <span class="text-gray-400 font-normal">(raccoglie utile/perdita)</span>
                            </label>
                            <select v-model="formConfig.conto_chiusura_ce_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm shadow-sm">
                                <option :value="null">— Nessuno —</option>
                                <option v-for="c in contiChiusuraCe" :key="c.id" :value="c.id">
                                    {{ c.codice }} — {{ c.descrizione }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Conto Apertura/Chiusura SP
                                <span class="text-gray-400 font-normal">(bilancia lo stato patrimoniale)</span>
                            </label>
                            <select v-model="formConfig.conto_apertura_id"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm shadow-sm">
                                <option :value="null">— Nessuno —</option>
                                <option v-for="c in contiApertura" :key="c.id" :value="c.id">
                                    {{ c.codice }} — {{ c.descrizione }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                            <textarea v-model="formConfig.note" rows="2"
                                      class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm shadow-sm" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <SecondaryButton type="button" @click="() => { formConfig.reset(); esercizioInConfig = null; }">Annulla</SecondaryButton>
                            <PrimaryButton type="submit" :disabled="formConfig.processing">Salva configurazione</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
