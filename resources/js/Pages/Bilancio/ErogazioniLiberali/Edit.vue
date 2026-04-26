<script setup>
import { HeartIcon } from '@heroicons/vue/24/outline';
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { computed, watch } from 'vue';

const props = defineProps({
    erogazione:  Object,
    anniRange:   Array,
    modalita:    Object,
    tipiDonante: Object,
});

const page   = usePage();
const tenant = page.props.tenant;

const form = useForm({
    anno:                    props.erogazione.anno,
    donante_tipo:            props.erogazione.donante_tipo,
    donante_cf:              props.erogazione.donante_cf,
    donante_piva:            props.erogazione.donante_piva ?? '',
    donante_cognome:         props.erogazione.donante_cognome ?? '',
    donante_nome:            props.erogazione.donante_nome ?? '',
    donante_ragione_sociale: props.erogazione.donante_ragione_sociale ?? '',
    donante_indirizzo:       props.erogazione.donante_indirizzo ?? '',
    donante_cap:             props.erogazione.donante_cap ?? '',
    donante_comune:          props.erogazione.donante_comune ?? '',
    donante_provincia:       props.erogazione.donante_provincia ?? '',
    importo:                 props.erogazione.importo,
    data_erogazione:         props.erogazione.data_erogazione
                               ? props.erogazione.data_erogazione.substring(0, 10)
                               : '',
    modalita_pagamento:      props.erogazione.modalita_pagamento,
    note:                    props.erogazione.note ?? '',
});

const isPF          = computed(() => form.donante_tipo === 'persona_fisica');
const isTracciabile = computed(() => ['bonifico','assegno_circolare','carta_credito','carta_debito','altro_tracciabile'].includes(form.modalita_pagamento));
const aliquota      = computed(() => {
    if (!isTracciabile.value) return null;
    return form.donante_tipo === 'persona_fisica' ? 26 : 30;
});
const importoDetrazione = computed(() => {
    if (!aliquota.value || !form.importo) return null;
    return ((parseFloat(form.importo) || 0) * aliquota.value / 100).toFixed(2);
});

const submit = () => {
    form.put(route('erogazioni-liberali.update', [tenant, props.erogazione.id]));
};
</script>

<template>
    <AppLayout title="Modifica Erogazione Liberale">
        <Head title="Modifica Erogazione Liberale" />
        <template #header>
            <div class="flex items-center gap-2">
                <HeartIcon class="w-6 h-6 text-rose-500" />
                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Modifica Erogazione Liberale</h2>
            </div>
        </template>

        <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Dati donante -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-5">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200 text-base">Dati donante</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anno *</label>
                        <select v-model="form.anno"
                                class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm">
                            <option v-for="a in anniRange" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo donante *</label>
                        <select v-model="form.donante_tipo"
                                class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm">
                            <option v-for="(lbl, key) in tipiDonante" :key="key" :value="key">{{ lbl }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Codice fiscale *</label>
                        <input v-model="form.donante_cf" type="text" maxlength="16"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-mono uppercase"
                               @input="form.donante_cf = form.donante_cf.toUpperCase()" />
                        <p v-if="form.errors.donante_cf" class="text-red-600 text-xs mt-1">{{ form.errors.donante_cf }}</p>
                    </div>
                    <div v-if="!isPF">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Partita IVA</label>
                        <input v-model="form.donante_piva" type="text" maxlength="11"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-mono" />
                    </div>
                </div>

                <div v-if="isPF" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cognome</label>
                        <input v-model="form.donante_cognome" type="text"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                        <input v-model="form.donante_nome" type="text"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                </div>
                <div v-else>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ragione sociale</label>
                    <input v-model="form.donante_ragione_sociale" type="text"
                           class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Indirizzo</label>
                        <input v-model="form.donante_indirizzo" type="text"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CAP</label>
                        <input v-model="form.donante_cap" type="text"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-mono" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comune</label>
                        <input v-model="form.donante_comune" type="text"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prov.</label>
                        <input v-model="form.donante_provincia" type="text" maxlength="2"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-mono uppercase"
                               @input="form.donante_provincia = form.donante_provincia.toUpperCase()" />
                    </div>
                </div>
            </div>

            <!-- Dati donazione -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-5">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200 text-base">Dati donazione</h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Importo (€) *</label>
                        <input v-model="form.importo" type="number" min="0.01" step="0.01"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                        <p v-if="form.errors.importo" class="text-red-600 text-xs mt-1">{{ form.errors.importo }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data erogazione *</label>
                        <input v-model="form.data_erogazione" type="date"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Modalità *</label>
                        <select v-model="form.modalita_pagamento"
                                class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm">
                            <option v-for="(lbl, key) in modalita" :key="key" :value="key">{{ lbl }}</option>
                        </select>
                    </div>
                </div>

                <!-- Preview detraibilità -->
                <div v-if="form.importo > 0"
                     :class="['rounded-lg p-3 text-sm', isTracciabile ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200']">
                    <strong :class="isTracciabile ? 'text-green-800' : 'text-red-700'">
                        {{ isTracciabile ? `Detraibile al ${aliquota}% — € ${importoDetrazione}` : 'Non detraibile (contante)' }}
                    </strong>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                    <textarea v-model="form.note" rows="2"
                              class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm"></textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center">
                <Link :href="route('erogazioni-liberali.show', [tenant, erogazione.id])"
                      class="text-sm text-gray-500 hover:text-gray-700">← Annulla</Link>
                <PrimaryButton @click="submit" :disabled="form.processing">
                    {{ form.processing ? 'Salvataggio…' : 'Salva modifiche' }}
                </PrimaryButton>
            </div>

        </div>
    </AppLayout>
</template>
