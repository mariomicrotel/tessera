<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { ref, watch, computed } from 'vue';
import {
    DocumentTextIcon,
    PlusIcon,
    TrashIcon,
    BoltIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    anni:            Array,
    annoCorrente:    Number,
    mesiLabel:       Array,
    sezioni:         Object,
    statiLabel:      Object,
    liquidazioni:    Array,
    versamenti:      Array,
    codiciFrecuenti: Array,
});

/* ── Form ─────────────────────────────────────────────────────────────────── */
const form = useForm({
    modalita:                'manuale',   // manuale | da_liquidazione | da_ritenute
    anno:                    props.annoCorrente,
    mese:                    '',
    data_compilazione:       new Date().toISOString().substring(0, 10),
    data_versamento:         '',
    stato:                   'bozza',
    note:                    '',
    liquidazione_iva_id:     null,
    versamento_ritenuta_id:  null,
    righe:                   [],
});

/* ── Riga vuota ──────────────────────────────────────────────────────────── */
const rigaVuota = () => ({
    sezione:          'erario',
    codice_tributo:   '',
    descrizione:      '',
    rateazione:       '',
    anno_riferimento: props.annoCorrente,
    importo_debito:   '',
    importo_credito:  '',
});

/* ── Gestione righe ──────────────────────────────────────────────────────── */
const aggiungiRiga = () => form.righe.push(rigaVuota());
const rimuoviRiga = (i) => form.righe.splice(i, 1);

const applicaCodice = (i, codice) => {
    const c = props.codiciFrecuenti.find(x => x.codice === codice);
    if (!c) return;
    form.righe[i].sezione         = c.sezione;
    form.righe[i].codice_tributo  = c.codice;
    form.righe[i].descrizione     = c.descrizione;
};

/* ── Totali live ─────────────────────────────────────────────────────────── */
const totaleDebiti  = computed(() => form.righe.reduce((s, r) => s + (parseFloat(r.importo_debito)  || 0), 0));
const totaleCrediti = computed(() => form.righe.reduce((s, r) => s + (parseFloat(r.importo_credito) || 0), 0));
const saldo         = computed(() => Math.round((totaleDebiti.value - totaleCrediti.value) * 100) / 100);
const fmt           = (n) => Number(n).toFixed(2);

/* ── Modalità: aggiungi riga default quando si passa a manuale ───────────── */
watch(() => form.modalita, (m) => {
    if (m === 'manuale' && form.righe.length === 0) aggiungiRiga();
    if (m !== 'manuale') form.righe = [];
});

// Aggiungi una riga iniziale per default
if (form.modalita === 'manuale') aggiungiRiga();

/* ── Submit ──────────────────────────────────────────────────────────────── */
const submit = () => form.post(route('f24.store'));
</script>

<template>
    <AppLayout title="Nuovo F24">
        <Head title="Nuovo F24" />
        <template #header>
            <div class="flex items-center gap-2">
                <DocumentTextIcon class="size-5 text-gray-500" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Nuovo Modello F24
                </h2>
            </div>
        </template>

        <div class="py-6 max-w-5xl mx-auto sm:px-6">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- ── Modalità di compilazione ───────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-2">
                        <BoltIcon class="size-4" />Modalità di compilazione
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label v-for="(label, key) in { manuale: 'Manuale (righe libere)', da_liquidazione: 'Da Liquidazione IVA', da_ritenute: 'Da Versamento Ritenute' }"
                               :key="key"
                               class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer transition-colors"
                               :class="form.modalita === key
                                   ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                   : 'border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50'">
                            <input type="radio" v-model="form.modalita" :value="key" class="text-indigo-600" />
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ label }}</span>
                        </label>
                    </div>

                    <!-- Selezione liquidazione IVA -->
                    <div v-if="form.modalita === 'da_liquidazione'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Liquidazione IVA <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.liquidazione_iva_id" required
                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                            <option :value="null">— seleziona —</option>
                            <option v-for="l in liquidazioni" :key="l.id" :value="l.id">
                                {{ l.tipo_periodo === 'mensile' ? `Mensile ${l.periodo}/${l.anno}` : `Trimestrale Q${l.periodo}/${l.anno}` }}
                                — saldo € {{ Number(l.saldo_finale).toFixed(2) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.liquidazione_iva_id" class="mt-1" />
                        <p v-if="!liquidazioni.length" class="text-xs text-gray-400 mt-1">
                            Nessuna liquidazione definitiva con saldo a debito disponibile.
                        </p>
                    </div>

                    <!-- Selezione versamento ritenute -->
                    <div v-if="form.modalita === 'da_ritenute'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Versamento ritenute <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.versamento_ritenuta_id" required
                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                            <option :value="null">— seleziona —</option>
                            <option v-for="v in versamenti" :key="v.id" :value="v.id">
                                {{ v.mese_riferimento }}/{{ v.anno_riferimento }}
                                — cod. {{ v.codice_tributo }}
                                — € {{ Number(v.importo_totale).toFixed(2) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.versamento_ritenuta_id" class="mt-1" />
                        <p v-if="!versamenti.length" class="text-xs text-gray-400 mt-1">
                            Nessun versamento ritenute senza F24 collegato.
                        </p>
                    </div>
                </div>

                <!-- ── Testata ─────────────────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Dati testata
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Anno <span class="text-red-500">*</span>
                            </label>
                            <select v-model.number="form.anno" required
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
                                <option value="bozza">Bozza</option>
                                <option value="compilato">Compilato</option>
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

                <!-- ── Righe (solo modalità manuale) ──────────────────────── -->
                <div v-if="form.modalita === 'manuale'" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
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
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Riga {{ i + 1 }}</span>
                            <button type="button" @click="rimuoviRiga(i)"
                                class="text-red-500 hover:text-red-700">
                                <TrashIcon class="size-4" />
                            </button>
                        </div>

                        <!-- Codice rapido -->
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Codice frequente (scorciatoia)</label>
                            <select @change="e => applicaCodice(i, e.target.value)"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm">
                                <option value="">— seleziona codice —</option>
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
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Sezione <span class="text-red-500">*</span></label>
                                <select v-model="riga.sezione" required
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm">
                                    <option v-for="(sl, sk) in sezioni" :key="sk" :value="sk">{{ sl }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Codice tributo <span class="text-red-500">*</span></label>
                                <input v-model="riga.codice_tributo" type="text" required maxlength="10"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs font-mono shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Rateazione</label>
                                <input v-model="riga.rateazione" type="text" maxlength="6" placeholder="es. 0126"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs font-mono shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Anno rif.</label>
                                <input v-model.number="riga.anno_riferimento" type="number" min="2000" max="2100"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Descrizione</label>
                                <input v-model="riga.descrizione" type="text" maxlength="200"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Importo debiti (€)</label>
                                <input v-model="riga.importo_debito" type="number" step="0.01" min="0"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Importo crediti (€)</label>
                                <input v-model="riga.importo_credito" type="number" step="0.01" min="0"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs shadow-sm" />
                            </div>
                        </div>
                    </div>

                    <button v-if="form.righe.length === 0" type="button" @click="aggiungiRiga"
                        class="w-full py-4 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600 transition-colors">
                        + Aggiungi prima riga
                    </button>

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

                <!-- ── Actions ────────────────────────────────────────────── -->
                <div class="flex justify-end gap-3">
                    <Link :href="route('f24.index')"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Annulla
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Salvataggio…' : 'Crea F24' }}
                    </PrimaryButton>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
