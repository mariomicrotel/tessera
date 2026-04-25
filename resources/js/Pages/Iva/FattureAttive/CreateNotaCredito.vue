<script setup>
import { PlusIcon, ArrowLeftIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed } from 'vue';

const props = defineProps({
    fattura:           Object,
    contiCrediti:      Array,
    contiRicavo:       Array,
    contiIva:          Array,
});

const fmt = (n) => Number(n ?? 0).toFixed(2);

const form = useForm({
    tipo_storno:         'totale',
    importo_storno:      null,
    motivo_nota_credito: '',
    righe:               [],
    conto_crediti_id:    null,
    conto_ricavi_id:     null,
    conto_iva_debito_id: null,
});

// Calcolo automatico dell'importo per storno totale
const importoSuggerito = computed(() => {
    if (form.tipo_storno === 'totale') {
        return fmt(props.fattura.totale_documento);
    }
    return form.importo_storno ? fmt(form.importo_storno) : '';
});

function submit() {
    form.post(route('iva.fatture-attive.store-nota-credito', props.fattura.id));
}
</script>

<template>
    <AppLayout :title="`Nota di Credito per ${fattura.numero_fattura}`">
        <Head :title="`Crea Nota di Credito per ${fattura.numero_fattura}`" />
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('iva.fatture-attive.show', fattura.id)"
                      class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                    <ArrowLeftIcon class="size-5" aria-hidden="true" />
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Crea Nota di Credito
                    <span class="font-mono text-indigo-600 dark:text-indigo-400 ml-1">per {{ fattura.numero_fattura }}</span>
                </h2>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- ── Informazioni fattura origine ──────────────────── -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b dark:border-gray-700 pb-2">
                        Fattura collegata
                    </h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Numero</dt>
                            <dd class="font-medium font-mono text-gray-900 dark:text-gray-100">{{ fattura.numero_fattura }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Data</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ new Date(fattura.data_fattura).toLocaleDateString('it-IT') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Totale</dt>
                            <dd class="font-medium font-mono text-gray-900 dark:text-gray-100">€ {{ fmt(fattura.totale_documento) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- ── Dati nota di credito ──────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b dark:border-gray-700 pb-2">
                        Dati nota di credito
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Tipo storno -->
                        <div>
                            <InputLabel for="tipo_storno" value="Tipo di storno *" />
                            <select id="tipo_storno" v-model="form.tipo_storno"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <option value="totale">Storno totale</option>
                                <option value="parziale">Storno parziale</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.tipo_storno" />
                        </div>

                        <!-- Importo (per parziale) -->
                        <div>
                            <InputLabel for="importo_storno" value="Importo storno" />
                            <TextInput id="importo_storno"
                                v-model="form.importo_storno"
                                type="number" step="0.01" min="0.01"
                                :placeholder="importoSuggerito"
                                :disabled="form.tipo_storno === 'totale'"
                                class="mt-1 block w-full font-mono" />
                            <InputError class="mt-1" :message="form.errors.importo_storno" />
                        </div>
                    </div>

                    <!-- Motivo -->
                    <div>
                        <InputLabel for="motivo" value="Motivo della nota di credito *" />
                        <textarea id="motivo" v-model="form.motivo_nota_credito" rows="2"
                            placeholder="Es: Reso cliente, Sconto accordato, Errore fatturazione"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                            required />
                        <InputError class="mt-1" :message="form.errors.motivo_nota_credito" />
                    </div>
                </div>

                <!-- ── Conti contabili (opzionali) ───────────────────── -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b dark:border-gray-700 pb-2">
                        Conti contabili (opzionali)
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Se valorizzati, il sistema registrerà automaticamente i movimenti contabili di storno.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <InputLabel for="conto_crediti" value="Conto crediti" />
                            <select id="conto_crediti" v-model="form.conto_crediti_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <option :value="null">— Nessuno —</option>
                                <option v-for="c in contiCrediti" :key="c.id" :value="c.id">{{ c.codice }} – {{ c.descrizione }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.conto_crediti_id" />
                        </div>

                        <div>
                            <InputLabel for="conto_ricavi" value="Conto ricavi" />
                            <select id="conto_ricavi" v-model="form.conto_ricavi_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <option :value="null">— Nessuno —</option>
                                <option v-for="c in contiRicavo" :key="c.id" :value="c.id">{{ c.codice }} – {{ c.descrizione }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.conto_ricavi_id" />
                        </div>

                        <div>
                            <InputLabel for="conto_iva" value="Conto IVA debito" />
                            <select id="conto_iva" v-model="form.conto_iva_debito_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <option :value="null">— Nessuno —</option>
                                <option v-for="c in contiIva" :key="c.id" :value="c.id">{{ c.codice }} – {{ c.descrizione }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.conto_iva_debito_id" />
                        </div>
                    </div>
                </div>

                <!-- ── Azioni ──────────────────────────────────────────── -->
                <div class="flex gap-3">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        <CheckIcon class="size-4 me-2" aria-hidden="true" />Crea Nota di Credito
                    </PrimaryButton>
                    <Link :href="route('iva.fatture-attive.show', fattura.id)"
                        class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ArrowLeftIcon class="size-4" />Annulla
                    </Link>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
