<script setup>
import { HeartIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { computed, watch } from 'vue';

const props = defineProps({
    annoDefault:  Number,
    anniRange:    Array,
    modalita:     Object,
    tipiDonante:  Object,
});

const page   = usePage();
const tenant = page.props.tenant;

const form = useForm({
    anno:                    props.annoDefault,
    donante_tipo:            'persona_fisica',
    donante_cf:              '',
    donante_piva:            '',
    donante_cognome:         '',
    donante_nome:            '',
    donante_ragione_sociale: '',
    donante_indirizzo:       '',
    donante_cap:             '',
    donante_comune:          '',
    donante_provincia:       '',
    importo:                 '',
    data_erogazione:         '',
    modalita_pagamento:      'bonifico',
    note:                    '',
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

// Reset nome fields when type changes
watch(() => form.donante_tipo, () => {
    form.donante_cognome = '';
    form.donante_nome = '';
    form.donante_ragione_sociale = '';
    form.donante_piva = '';
});

const submit = () => {
    form.post(route('erogazioni-liberali.store', tenant));
};
</script>

<template>
    <AppLayout title="Nuova Erogazione Liberale">
        <Head title="Nuova Erogazione Liberale" />
        <template #header>
            <div class="flex items-center gap-2">
                <HeartIcon class="w-6 h-6 text-rose-500" />
                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Nuova Erogazione Liberale</h2>
            </div>
        </template>

        <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Info art. 83 -->
            <div class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-xl p-4">
                <InformationCircleIcon class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-blue-800">
                    <strong>Art. 83 D.Lgs. 117/2017</strong> — La donazione è detraibile solo se effettuata
                    tramite strumento tracciabile (bonifico, carta, assegno circolare).
                    Il contante <strong>non dà diritto alla detrazione</strong>.
                    Aliquota: <strong>26%</strong> per persone fisiche, <strong>30%</strong> per enti.
                </div>
            </div>

            <!-- Anno + Dati donante -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-5">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200 text-base">Dati donante</h3>

                <!-- Anno -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Anno competenza <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.anno"
                                class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm">
                            <option v-for="a in anniRange" :key="a" :value="a">{{ a }}</option>
                        </select>
                        <p v-if="form.errors.anno" class="text-red-600 text-xs mt-1">{{ form.errors.anno }}</p>
                    </div>

                    <!-- Tipo donante -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Tipo donante <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.donante_tipo"
                                class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm">
                            <option v-for="(lbl, key) in tipiDonante" :key="key" :value="key">{{ lbl }}</option>
                        </select>
                    </div>
                </div>

                <!-- CF / PIVA -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Codice fiscale <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.donante_cf" type="text" maxlength="16"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-mono uppercase"
                               @input="form.donante_cf = form.donante_cf.toUpperCase()"
                               placeholder="RSSMRA80A01H501U" />
                        <p v-if="form.errors.donante_cf" class="text-red-600 text-xs mt-1">{{ form.errors.donante_cf }}</p>
                    </div>
                    <div v-if="!isPF">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Partita IVA</label>
                        <input v-model="form.donante_piva" type="text" maxlength="11"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-mono" />
                    </div>
                </div>

                <!-- Nome -->
                <div v-if="isPF" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cognome</label>
                        <input v-model="form.donante_cognome" type="text" maxlength="100"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                        <p v-if="form.errors.donante_cognome" class="text-red-600 text-xs mt-1">{{ form.errors.donante_cognome }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                        <input v-model="form.donante_nome" type="text" maxlength="100"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                </div>

                <div v-else>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ragione sociale</label>
                    <input v-model="form.donante_ragione_sociale" type="text" maxlength="200"
                           class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    <p v-if="form.errors.donante_ragione_sociale" class="text-red-600 text-xs mt-1">{{ form.errors.donante_ragione_sociale }}</p>
                </div>

                <!-- Indirizzo -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Indirizzo</label>
                        <input v-model="form.donante_indirizzo" type="text" maxlength="200"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CAP</label>
                        <input v-model="form.donante_cap" type="text" maxlength="10"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-mono" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comune</label>
                        <input v-model="form.donante_comune" type="text" maxlength="100"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Provincia</label>
                        <input v-model="form.donante_provincia" type="text" maxlength="2"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-mono uppercase"
                               @input="form.donante_provincia = form.donante_provincia.toUpperCase()"
                               placeholder="MI" />
                    </div>
                </div>
            </div>

            <!-- Dati donazione -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-5">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200 text-base">Dati donazione</h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Importo (€) <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.importo" type="number" min="0.01" step="0.01"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm"
                               placeholder="100.00" />
                        <p v-if="form.errors.importo" class="text-red-600 text-xs mt-1">{{ form.errors.importo }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Data erogazione <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.data_erogazione" type="date"
                               class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm" />
                        <p v-if="form.errors.data_erogazione" class="text-red-600 text-xs mt-1">{{ form.errors.data_erogazione }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Modalità pagamento <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.modalita_pagamento"
                                class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm">
                            <option v-for="(lbl, key) in modalita" :key="key" :value="key">{{ lbl }}</option>
                        </select>
                    </div>
                </div>

                <!-- Preview detraibilità -->
                <div v-if="form.importo > 0"
                     :class="['rounded-lg p-4 flex items-start gap-3 text-sm',
                              isTracciabile ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200']">
                    <div :class="['flex-shrink-0 font-bold text-lg', isTracciabile ? 'text-green-700' : 'text-red-600']">
                        {{ isTracciabile ? '✓' : '✗' }}
                    </div>
                    <div>
                        <p :class="['font-semibold', isTracciabile ? 'text-green-800' : 'text-red-700']">
                            {{ isTracciabile ? 'Donazione detraibile' : 'Donazione NON detraibile (contante)' }}
                        </p>
                        <p v-if="isTracciabile" class="text-green-700 mt-0.5">
                            Aliquota detrazione: <strong>{{ aliquota }}%</strong>
                            ({{ form.donante_tipo === 'persona_fisica' ? 'persona fisica — art. 83 c.1 CTS' : 'ente — art. 83 c.2 CTS' }})
                            <br>
                            Detrazione spettante al donante: <strong>€ {{ importoDetrazione }}</strong>
                        </p>
                        <p v-else class="text-red-600 mt-0.5 text-xs">
                            I pagamenti in contante non sono ammessi per la detrazione ex art. 83 CTS.
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                    <textarea v-model="form.note" rows="2"
                              class="w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm"></textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center">
                <Link :href="route('erogazioni-liberali.index', tenant)"
                      class="text-sm text-gray-500 hover:text-gray-700">← Annulla</Link>
                <PrimaryButton @click="submit" :disabled="form.processing">
                    {{ form.processing ? 'Salvataggio…' : 'Registra erogazione' }}
                </PrimaryButton>
            </div>

        </div>
    </AppLayout>
</template>
