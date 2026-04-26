<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';
import {
    UserGroupIcon,
    CalculatorIcon,
    BanknotesIcon,
    DocumentTextIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    compenso:     Object,
    membri:       Array,
    contiCosto:   Array,
    contiPassivo: Array,
    tipiRapporto: Object,
    causali:      Object,
});

const form = useForm({
    member_id:                    props.compenso.member_id,
    nome_percipiente:             props.compenso.nome_percipiente,
    codice_fiscale:               props.compenso.codice_fiscale,
    partita_iva:                  props.compenso.partita_iva ?? '',
    indirizzo:                    props.compenso.indirizzo ?? '',
    tipo_rapporto:                props.compenso.tipo_rapporto,
    codice_causale:               props.compenso.codice_causale,
    data_pagamento:               props.compenso.data_pagamento?.substring(0, 10) ?? '',
    causale_prestazione:          props.compenso.causale_prestazione,
    compenso_lordo:               props.compenso.compenso_lordo,
    base_imponibile_ritenuta:     props.compenso.base_imponibile_ritenuta,
    aliquota_ritenuta:            props.compenso.aliquota_ritenuta,
    rimborsi_spese:               props.compenso.rimborsi_spese ?? '',
    contributo_inps_beneficiario: props.compenso.contributo_inps_beneficiario ?? '',
    contributo_inps_committente:  props.compenso.contributo_inps_committente ?? '',
    conto_costo_id:               props.compenso.conto_costo_id,
    conto_ritenute_id:            props.compenso.conto_ritenute_id,
    note:                         props.compenso.note ?? '',
});

/* ── Calcolo live ────────────────────────────────────────────────────────── */
const lordo    = computed(() => parseFloat(form.compenso_lordo)           || 0);
const base     = computed(() => parseFloat(form.base_imponibile_ritenuta) || lordo.value);
const aliquota = computed(() => parseFloat(form.aliquota_ritenuta)        || 0);
const ritenuta = computed(() => Math.round(base.value * aliquota.value)   / 100);
const netto    = computed(() => Math.round((lordo.value - ritenuta.value) * 100) / 100);

const fmt = (n) => Number(n).toFixed(2);

const submit = () => form.put(route('compensi-terzi.update', props.compenso.id));
</script>

<template>
    <AppLayout :title="`Modifica compenso — ${compenso.nome_percipiente}`">
        <Head :title="`Modifica compenso — ${compenso.nome_percipiente}`" />
        <template #header>
            <div class="flex items-center gap-2">
                <UserGroupIcon class="size-5 text-gray-500" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Modifica compenso — {{ compenso.nome_percipiente }}
                </h2>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- ── 1. Anagrafica ──────────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        <UserIcon class="size-4" />Percipiente
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Nome / Ragione sociale <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.nome_percipiente" type="text" required maxlength="150"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                            <InputError :message="form.errors.nome_percipiente" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Codice Fiscale <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.codice_fiscale" type="text" required maxlength="16"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm font-mono shadow-sm uppercase" />
                            <InputError :message="form.errors.codice_fiscale" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Partita IVA</label>
                            <input v-model="form.partita_iva" type="text" maxlength="11"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm font-mono shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Indirizzo</label>
                            <input v-model="form.indirizzo" type="text" maxlength="255"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                    </div>
                </div>

                <!-- ── 2. Tipo rapporto ────────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        <DocumentTextIcon class="size-4" />Tipo rapporto
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Tipo rapporto <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.tipo_rapporto" required
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                                <option v-for="(label, key) in tipiRapporto" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Codice causale (Mod. 770) <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.codice_causale" required
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                                <option v-for="(label, key) in causali" :key="key" :value="key">{{ key }} — {{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Data pagamento <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.data_pagamento" type="date" required
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                            <InputError :message="form.errors.data_pagamento" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Causale / descrizione prestazione <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="form.causale_prestazione" required maxlength="500" rows="2"
                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm"></textarea>
                        <InputError :message="form.errors.causale_prestazione" class="mt-1" />
                    </div>
                </div>

                <!-- ── 3. Importi ──────────────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        <CalculatorIcon class="size-4" />Importi e ritenuta
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Compenso lordo (€) <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.compenso_lordo" type="number" step="0.01" min="0.01" required
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                            <InputError :message="form.errors.compenso_lordo" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Base imponibile ritenuta (€)
                            </label>
                            <input v-model="form.base_imponibile_ritenuta" type="number" step="0.01" min="0"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Aliquota ritenuta (%)
                            </label>
                            <input v-model.number="form.aliquota_ritenuta" type="number" step="0.01" min="0" max="100"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                    </div>

                    <div v-if="lordo > 0" class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 p-4 grid grid-cols-3 gap-4 text-center text-sm">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Ritenuta calcolata</p>
                            <p class="text-lg font-bold font-mono text-red-600 dark:text-red-400">€ {{ fmt(ritenuta) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Netto da corrispondere</p>
                            <p class="text-lg font-bold font-mono text-green-700 dark:text-green-400">€ {{ fmt(netto) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Aliquota</p>
                            <p class="text-lg font-bold font-mono text-gray-700 dark:text-gray-300">{{ aliquota }}%</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rimborsi spese (€)</label>
                            <input v-model="form.rimborsi_spese" type="number" step="0.01" min="0"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">INPS beneficiario (€)</label>
                            <input v-model="form.contributo_inps_beneficiario" type="number" step="0.01" min="0"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">INPS committente (€)</label>
                            <input v-model="form.contributo_inps_committente" type="number" step="0.01" min="0"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm" />
                        </div>
                    </div>
                </div>

                <!-- ── 4. Conti contabili ──────────────────────────────────── -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        <BanknotesIcon class="size-4" />Contabilità
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conto di costo</label>
                            <select v-model="form.conto_costo_id"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                                <option :value="null">— nessuno —</option>
                                <option v-for="c in contiCosto" :key="c.id" :value="c.id">
                                    {{ c.codice }} — {{ c.descrizione }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conto ritenute a debito</label>
                            <select v-model="form.conto_ritenute_id"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm">
                                <option :value="null">— nessuno —</option>
                                <option v-for="c in contiPassivo" :key="c.id" :value="c.id">
                                    {{ c.codice }} — {{ c.descrizione }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                        <textarea v-model="form.note" maxlength="1000" rows="2"
                            class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm"></textarea>
                    </div>
                </div>

                <!-- ── Actions ────────────────────────────────────────────── -->
                <div class="flex justify-end gap-3">
                    <Link :href="route('compensi-terzi.show', compenso.id)"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Annulla
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Salvataggio…' : 'Aggiorna compenso' }}
                    </PrimaryButton>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
