<script setup>
import { PlusIcon, TrashIcon, ArrowLeftIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed } from 'vue';

const props = defineProps({
    suppliers:       Array,
    codiciIva:       Array,
    conti:           Array,
    tipiDocumento:   Object,
    esigibilitaOpts: Object,
});

/* ── Form ─────────────────────────────────────────────────────────────── */
const form = useForm({
    supplier_id:         null,
    numero_fattura:      '',
    data_fattura:        '',
    data_ricezione:      '',
    data_registrazione:  '',
    data_scadenza:       '',
    esigibilita:         'immediata',
    tipo_documento:      'TD01',
    note:                '',
    righe: [rigaVuota()],
});

function rigaVuota() {
    return {
        codice_iva_id:            props.codiciIva[0]?.id ?? null,
        conto_id:                 null,
        descrizione:              '',
        quantita:                 1,
        prezzo_unitario:          0,
        indetraibile_percentuale: null,
    };
}

function aggiungiRiga() {
    form.righe.push(rigaVuota());
}

function rimuoviRiga(index) {
    if (form.righe.length > 1) {
        form.righe.splice(index, 1);
    }
}

/* ── Calcolo live ─────────────────────────────────────────────────────── */
function getAliquota(codiceIvaId) {
    const codice = props.codiciIva.find(c => c.id === Number(codiceIvaId));
    return codice ? Number(codice.percentuale) : 0;
}
function getIndPct(codiceIvaId, rigaIndPct) {
    if (rigaIndPct !== null && rigaIndPct !== undefined && rigaIndPct !== '') {
        return Number(rigaIndPct);
    }
    const codice = props.codiciIva.find(c => c.id === Number(codiceIvaId));
    return codice ? Number(codice.indetraibile_percentuale ?? 0) : 0;
}

function calcolaRiga(riga) {
    const qta    = Number(riga.quantita    ?? 0);
    const prezzo = Number(riga.prezzo_unitario ?? 0);
    const imp    = Math.round(qta * prezzo * 100) / 100;
    const aliq   = getAliquota(riga.codice_iva_id);
    const iva    = Math.round(imp * aliq / 100 * 100) / 100;
    const indPct = getIndPct(riga.codice_iva_id, riga.indetraibile_percentuale);
    const ivaInd = Math.round(iva * indPct / 100 * 100) / 100;
    return { imp, iva, ivaInd, totale: Math.round((imp + iva) * 100) / 100 };
}

const totali = computed(() => {
    let imponibile = 0, iva = 0, ivaInd = 0;
    for (const r of form.righe) {
        const c = calcolaRiga(r);
        imponibile += c.imp;
        iva        += c.iva;
        ivaInd     += c.ivaInd;
    }
    return {
        imponibile: Math.round(imponibile * 100) / 100,
        iva:        Math.round(iva * 100) / 100,
        ivaInd:     Math.round(ivaInd * 100) / 100,
        totale:     Math.round((imponibile + iva) * 100) / 100,
    };
});

const fmt = (n) => Number(n ?? 0).toFixed(2);
const fmtRiga = (riga) => {
    const c = calcolaRiga(riga);
    return { imp: fmt(c.imp), iva: fmt(c.iva), totale: fmt(c.totale) };
};
</script>

<template>
    <AppLayout title="Nuova fattura passiva">
        <Head title="Nuova fattura passiva" />
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('iva.fatture-passive.index')"
                      class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                    <ArrowLeftIcon class="size-5" aria-hidden="true" />
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Nuova fattura passiva
                </h2>
            </div>
        </template>

        <div class="py-6 max-w-6xl mx-auto sm:px-6">
            <form @submit.prevent="form.post(route('iva.fatture-passive.store'))" class="space-y-6">

                <!-- Testata -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b dark:border-gray-700 pb-2">
                        Dati documento
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Fornitore -->
                        <div class="sm:col-span-3">
                            <InputLabel for="supplier_id" value="Fornitore" />
                            <select id="supplier_id" v-model="form.supplier_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <option :value="null">— Nessun fornitore —</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">
                                    {{ s.ragione_sociale || s.name }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.supplier_id" />
                        </div>

                        <!-- Numero fattura -->
                        <div>
                            <InputLabel for="numero_fattura" value="Numero fattura *" />
                            <TextInput id="numero_fattura" v-model="form.numero_fattura" class="mt-1 block w-full font-mono" required />
                            <InputError class="mt-1" :message="form.errors.numero_fattura" />
                        </div>

                        <!-- Tipo documento -->
                        <div>
                            <InputLabel for="tipo_documento" value="Tipo documento *" />
                            <select id="tipo_documento" v-model="form.tipo_documento" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <option v-for="(label, key) in tipiDocumento" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.tipo_documento" />
                        </div>

                        <!-- Esigibilità -->
                        <div>
                            <InputLabel for="esigibilita" value="Esigibilità IVA *" />
                            <select id="esigibilita" v-model="form.esigibilita" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <option v-for="(label, key) in esigibilitaOpts" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.esigibilita" />
                        </div>

                        <!-- Date -->
                        <div>
                            <InputLabel for="data_fattura" value="Data fattura *" />
                            <TextInput id="data_fattura" v-model="form.data_fattura" type="date" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="form.errors.data_fattura" />
                        </div>
                        <div>
                            <InputLabel for="data_registrazione" value="Data registrazione *" />
                            <TextInput id="data_registrazione" v-model="form.data_registrazione" type="date" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="form.errors.data_registrazione" />
                        </div>
                        <div>
                            <InputLabel for="data_ricezione" value="Data ricezione" />
                            <TextInput id="data_ricezione" v-model="form.data_ricezione" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.data_ricezione" />
                        </div>
                        <div>
                            <InputLabel for="data_scadenza" value="Scadenza" />
                            <TextInput id="data_scadenza" v-model="form.data_scadenza" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.data_scadenza" />
                        </div>

                        <!-- Note -->
                        <div class="sm:col-span-3">
                            <InputLabel for="note" value="Note" />
                            <textarea id="note" v-model="form.note" rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm" />
                            <InputError class="mt-1" :message="form.errors.note" />
                        </div>
                    </div>
                </div>

                <!-- Righe -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <div class="flex items-center justify-between border-b dark:border-gray-700 pb-2">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Righe fattura</h3>
                        <button type="button" @click="aggiungiRiga"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 border border-indigo-300 dark:border-indigo-700 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
                            <PlusIcon class="size-3.5" />Aggiungi riga
                        </button>
                    </div>
                    <InputError :message="form.errors.righe" />

                    <div class="space-y-4">
                        <div v-for="(riga, idx) in form.righe" :key="idx"
                             class="border dark:border-gray-700 rounded-lg p-4 space-y-3 relative">

                            <!-- Rimuovi riga -->
                            <button v-if="form.righe.length > 1" type="button" @click="rimuoviRiga(idx)"
                                class="absolute top-3 right-3 text-gray-400 hover:text-red-500 transition-colors">
                                <TrashIcon class="size-4" aria-hidden="true" />
                            </button>

                            <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                                <!-- Descrizione -->
                                <div class="sm:col-span-6">
                                    <InputLabel :for="`desc_${idx}`" value="Descrizione *" />
                                    <TextInput :id="`desc_${idx}`" v-model="riga.descrizione" class="mt-1 block w-full" required />
                                    <InputError class="mt-1" :message="form.errors[`righe.${idx}.descrizione`]" />
                                </div>

                                <!-- Codice IVA -->
                                <div class="sm:col-span-2">
                                    <InputLabel :for="`iva_${idx}`" value="Codice IVA *" />
                                    <select :id="`iva_${idx}`" v-model="riga.codice_iva_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                        <option v-for="c in codiciIva" :key="c.id" :value="c.id">
                                            {{ c.codice }} – {{ c.descrizione }} ({{ c.percentuale }}%)
                                        </option>
                                    </select>
                                    <InputError class="mt-1" :message="form.errors[`righe.${idx}.codice_iva_id`]" />
                                </div>

                                <!-- Conto -->
                                <div class="sm:col-span-2">
                                    <InputLabel :for="`conto_${idx}`" value="Conto di cassa" />
                                    <select :id="`conto_${idx}`" v-model="riga.conto_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                        <option :value="null">— Nessuno —</option>
                                        <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.code }} {{ c.name }}</option>
                                    </select>
                                    <InputError class="mt-1" :message="form.errors[`righe.${idx}.conto_id`]" />
                                </div>

                                <!-- % indetraibile -->
                                <div class="sm:col-span-2">
                                    <InputLabel :for="`indpct_${idx}`" value="% IVA indetraibile" />
                                    <TextInput :id="`indpct_${idx}`" v-model="riga.indetraibile_percentuale"
                                        type="number" step="0.01" min="0" max="100"
                                        class="mt-1 block w-full" placeholder="0" />
                                    <InputError class="mt-1" :message="form.errors[`righe.${idx}.indetraibile_percentuale`]" />
                                </div>

                                <!-- Quantità -->
                                <div class="sm:col-span-1">
                                    <InputLabel :for="`qta_${idx}`" value="Quantità *" />
                                    <TextInput :id="`qta_${idx}`" v-model="riga.quantita"
                                        type="number" step="0.0001" min="0.0001"
                                        class="mt-1 block w-full font-mono text-right" required />
                                    <InputError class="mt-1" :message="form.errors[`righe.${idx}.quantita`]" />
                                </div>

                                <!-- Prezzo unitario -->
                                <div class="sm:col-span-2">
                                    <InputLabel :for="`prezzo_${idx}`" value="Prezzo unitario *" />
                                    <TextInput :id="`prezzo_${idx}`" v-model="riga.prezzo_unitario"
                                        type="number" step="0.0001"
                                        class="mt-1 block w-full font-mono text-right" required />
                                    <InputError class="mt-1" :message="form.errors[`righe.${idx}.prezzo_unitario`]" />
                                </div>

                                <!-- Totali calcolati riga (read-only) -->
                                <div class="sm:col-span-3 flex items-end gap-4 text-sm text-gray-600 dark:text-gray-400 pb-0.5">
                                    <div class="text-right">
                                        <div class="text-xs text-gray-400">Imponibile</div>
                                        <div class="font-mono font-medium text-gray-900 dark:text-gray-100">€ {{ fmtRiga(riga).imp }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-gray-400">IVA</div>
                                        <div class="font-mono font-medium text-gray-900 dark:text-gray-100">€ {{ fmtRiga(riga).iva }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-gray-400">Totale riga</div>
                                        <div class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">€ {{ fmtRiga(riga).totale }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Riepilogo totali -->
                    <div class="border-t dark:border-gray-700 pt-4">
                        <div class="flex justify-end">
                            <dl class="text-sm space-y-1 min-w-48">
                                <div class="flex justify-between gap-8">
                                    <dt class="text-gray-500 dark:text-gray-400">Imponibile totale</dt>
                                    <dd class="font-mono">€ {{ fmt(totali.imponibile) }}</dd>
                                </div>
                                <div class="flex justify-between gap-8">
                                    <dt class="text-gray-500 dark:text-gray-400">IVA totale</dt>
                                    <dd class="font-mono">€ {{ fmt(totali.iva) }}</dd>
                                </div>
                                <div v-if="totali.ivaInd > 0" class="flex justify-between gap-8 text-orange-600 dark:text-orange-400">
                                    <dt>di cui indetraibile</dt>
                                    <dd class="font-mono">€ {{ fmt(totali.ivaInd) }}</dd>
                                </div>
                                <div class="flex justify-between gap-8 border-t dark:border-gray-700 pt-1 font-semibold">
                                    <dt>Totale documento</dt>
                                    <dd class="font-mono">€ {{ fmt(totali.totale) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Azioni -->
                <div class="flex gap-3">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        <CheckIcon class="size-4 me-2" aria-hidden="true" />Registra fattura
                    </PrimaryButton>
                    <Link :href="route('iva.fatture-passive.index')"
                        class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ArrowLeftIcon class="size-4" />Annulla
                    </Link>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
