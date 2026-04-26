<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';
import { DocumentTextIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modello:         Object,
    sezioni:         Object,
    statiLabel:      Object,
    codiciFrecuenti: Array,
});

/* ── Inizializza form con righe esistenti ────────────────────────────────── */
const form = useForm({
    anno:               props.modello.anno,
    mese:               props.modello.mese ?? '',
    data_compilazione:  props.modello.data_compilazione?.substring(0, 10) ?? '',
    data_versamento:    props.modello.data_versamento?.substring(0, 10) ?? '',
    stato:              props.modello.stato,
    note:               props.modello.note ?? '',
    righe: (props.modello.righe ?? []).map(r => ({
        sezione:          r.sezione,
        codice_tributo:   r.codice_tributo,
        descrizione:      r.descrizione ?? '',
        rateazione:       r.rateazione ?? '',
        anno_riferimento: r.anno_riferimento ?? props.modello.anno,
        importo_debito:   r.importo_debito,
        importo_credito:  r.importo_credito,
    })),
});

const rigaVuota = () => ({
    sezione: 'erario', codice_tributo: '', descrizione: '',
    rateazione: '', anno_riferimento: props.modello.anno,
    importo_debito: '', importo_credito: '',
});

const aggiungiRiga = () => form.righe.push(rigaVuota());
const rimuoviRiga  = (i) => form.righe.splice(i, 1);

const applicaCodice = (i, codice) => {
    const c = props.codiciFrecuenti.find(x => x.codice === codice);
    if (!c) return;
    form.righe[i].sezione        = c.sezione;
    form.righe[i].codice_tributo = c.codice;
    form.righe[i].descrizione    = c.descrizione;
};

const totaleDebiti  = computed(() => form.righe.reduce((s, r) => s + (parseFloat(r.importo_debito)  || 0), 0));
const totaleCrediti = computed(() => form.righe.reduce((s, r) => s + (parseFloat(r.importo_credito) || 0), 0));
const saldo         = computed(() => Math.round((totaleDebiti.value - totaleCrediti.value) * 100) / 100);
const fmt           = (n) => Number(n).toFixed(2);

const anni     = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - i);
const mesiLabel = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                   'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

const submit = () => form.put(route('f24.update', props.modello.id));
</script>

<template>
    <AppLayout :title="`Modifica F24 — ${modello.anno}`">
        <Head :title="`Modifica F24 — ${modello.anno}`" />
        <template #header>
            <div class="flex items-center gap-2">
                <DocumentTextIcon class="size-5 text-gray-500" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Modifica F24 — {{ modello.anno }}
                </h2>
            </div>
        </template>

        <div class="py-6 max-w-5xl mx-auto sm:px-6">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- Testata -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Dati testata
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anno</label>
                            <select v-model.number="form.anno"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                                <option v-for="a in anni" :key="a" :value="a">{{ a }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mese</label>
                            <select v-model.number="form.mese"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                                <option value="">— annuale —</option>
                                <option v-for="(m, i) in mesiLabel.slice(1)" :key="i+1" :value="i+1">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stato</label>
                            <select v-model="form.stato"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                                <option v-for="(sl, sk) in statiLabel" :key="sk" :value="sk">{{ sl }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Data compilazione <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.data_compilazione" type="date" required
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                            <InputError :message="form.errors.data_compilazione" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data versamento</label>
                            <input v-model="form.data_versamento" type="date"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                        <textarea v-model="form.note" maxlength="1000" rows="2"
                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm"></textarea>
                    </div>
                </div>

                <!-- Righe -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Righe tributi
                        </h3>
                        <button type="button" @click="aggiungiRiga"
                            class="inline-flex items-center gap-1 text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                            <PlusIcon class="size-4" />Aggiungi riga
                        </button>
                    </div>

                    <div v-for="(riga, i) in form.righe" :key="i"
                         class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-500">Riga {{ i + 1 }}</span>
                            <button type="button" @click="rimuoviRiga(i)" class="text-red-500 hover:text-red-700">
                                <TrashIcon class="size-4" />
                            </button>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Codice frequente</label>
                            <select @change="e => applicaCodice(i, e.target.value)"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm">
                                <option value="">— seleziona —</option>
                                <optgroup v-for="(grpLabel, grp) in sezioni" :key="grp" :label="grpLabel">
                                    <option v-for="c in codiciFrecuenti.filter(x => x.sezione === grp)"
                                            :key="c.codice" :value="c.codice">
                                        {{ c.codice }} — {{ c.descrizione }}
                                    </option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Sezione</label>
                                <select v-model="riga.sezione"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm">
                                    <option v-for="(sl, sk) in sezioni" :key="sk" :value="sk">{{ sl }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Codice tributo</label>
                                <input v-model="riga.codice_tributo" type="text" maxlength="10"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs font-mono shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Rateazione</label>
                                <input v-model="riga.rateazione" type="text" maxlength="6"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs font-mono shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Anno rif.</label>
                                <input v-model.number="riga.anno_riferimento" type="number"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">Descrizione</label>
                                <input v-model="riga.descrizione" type="text" maxlength="200"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Importo debiti (€)</label>
                                <input v-model="riga.importo_debito" type="number" step="0.01" min="0"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Importo crediti (€)</label>
                                <input v-model="riga.importo_credito" type="number" step="0.01" min="0"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm" />
                            </div>
                        </div>
                    </div>

                    <!-- Anteprima saldi -->
                    <div v-if="form.righe.length" class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4 grid grid-cols-3 gap-4 text-center text-sm">
                        <div>
                            <p class="text-xs text-gray-500">Totale debiti</p>
                            <p class="text-lg font-bold font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(totaleDebiti) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Totale crediti</p>
                            <p class="text-lg font-bold font-mono text-green-700 dark:text-green-400">€ {{ fmt(totaleCrediti) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Saldo</p>
                            <p class="text-lg font-bold font-mono" :class="saldo > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400'">
                                € {{ fmt(saldo) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <Link :href="route('f24.show', modello.id)"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Annulla
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Salvataggio…' : 'Aggiorna F24' }}
                    </PrimaryButton>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
