<script setup>
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    condizioniPagamento: Object,
    categorie:           Object,
});

const form = useForm({
    name:                 '',
    ragione_sociale:      '',
    email:                '',
    phone:                '',
    partita_iva:          '',
    codice_fiscale:       '',
    codice_sdi:           '',
    pec:                  '',
    indirizzo:            '',
    cap:                  '',
    citta:                '',
    provincia:            '',
    nazione:              'IT',
    iban:                 '',
    condizioni_pagamento: '30gg',
    categoria:            'servizi',
    note:                 '',
    attivo:               true,
});
</script>

<template>
    <AppLayout title="Nuovo fornitore">
        <Head title="Nuovo fornitore" />
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('suppliers.index')" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                    <ArrowLeftIcon class="size-5" aria-hidden="true" />
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nuovo fornitore</h2>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6">
            <form @submit.prevent="form.post(route('suppliers.store'))" class="space-y-6">

                <!-- Dati identificativi -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b dark:border-gray-700 pb-2">Dati identificativi</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="name" value="Nome commerciale *" />
                            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required autocomplete="organization" />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="ragione_sociale" value="Ragione sociale" />
                            <TextInput id="ragione_sociale" v-model="form.ragione_sociale" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.ragione_sociale" />
                        </div>
                        <div>
                            <InputLabel for="partita_iva" value="Partita IVA" />
                            <TextInput id="partita_iva" v-model="form.partita_iva" class="mt-1 block w-full font-mono" maxlength="20" placeholder="12345678901" />
                            <InputError class="mt-1" :message="form.errors.partita_iva" />
                        </div>
                        <div>
                            <InputLabel for="codice_fiscale" value="Codice fiscale" />
                            <TextInput id="codice_fiscale" v-model="form.codice_fiscale" class="mt-1 block w-full font-mono" maxlength="16" />
                            <InputError class="mt-1" :message="form.errors.codice_fiscale" />
                        </div>
                        <div>
                            <InputLabel for="codice_sdi" value="Codice SDI" />
                            <TextInput id="codice_sdi" v-model="form.codice_sdi" class="mt-1 block w-full font-mono" maxlength="7" placeholder="XXXXXXX" />
                            <InputError class="mt-1" :message="form.errors.codice_sdi" />
                        </div>
                        <div>
                            <InputLabel for="pec" value="PEC" />
                            <TextInput id="pec" v-model="form.pec" type="email" class="mt-1 block w-full" placeholder="fornitore@pec.it" />
                            <InputError class="mt-1" :message="form.errors.pec" />
                        </div>
                    </div>
                </div>

                <!-- Contatti -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b dark:border-gray-700 pb-2">Contatti</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Telefono" />
                            <TextInput id="phone" v-model="form.phone" type="tel" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.phone" />
                        </div>
                    </div>
                </div>

                <!-- Indirizzo -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b dark:border-gray-700 pb-2">Indirizzo</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-3">
                            <InputLabel for="indirizzo" value="Indirizzo" />
                            <TextInput id="indirizzo" v-model="form.indirizzo" class="mt-1 block w-full" placeholder="Via Roma 1" />
                            <InputError class="mt-1" :message="form.errors.indirizzo" />
                        </div>
                        <div>
                            <InputLabel for="cap" value="CAP" />
                            <TextInput id="cap" v-model="form.cap" class="mt-1 block w-full font-mono" maxlength="5" />
                            <InputError class="mt-1" :message="form.errors.cap" />
                        </div>
                        <div>
                            <InputLabel for="citta" value="Città" />
                            <TextInput id="citta" v-model="form.citta" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.citta" />
                        </div>
                        <div>
                            <InputLabel for="provincia" value="Provincia (sigla)" />
                            <TextInput id="provincia" v-model="form.provincia" class="mt-1 block w-full font-mono uppercase" maxlength="2" placeholder="MI" />
                            <InputError class="mt-1" :message="form.errors.provincia" />
                        </div>
                        <div>
                            <InputLabel for="nazione" value="Nazione (ISO)" />
                            <TextInput id="nazione" v-model="form.nazione" class="mt-1 block w-full font-mono uppercase" maxlength="2" placeholder="IT" />
                            <InputError class="mt-1" :message="form.errors.nazione" />
                        </div>
                    </div>
                </div>

                <!-- Dati bancari e condizioni commerciali -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b dark:border-gray-700 pb-2">Dati bancari e condizioni</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <InputLabel for="iban" value="IBAN" />
                            <TextInput id="iban" v-model="form.iban" class="mt-1 block w-full font-mono" maxlength="34" placeholder="IT60 X054 2811 1010 0000 0123 456" />
                            <InputError class="mt-1" :message="form.errors.iban" />
                        </div>
                        <div>
                            <InputLabel for="condizioni_pagamento" value="Condizioni pagamento *" />
                            <select
                                id="condizioni_pagamento"
                                v-model="form.condizioni_pagamento"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                            >
                                <option v-for="(label, key) in condizioniPagamento" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.condizioni_pagamento" />
                        </div>
                        <div>
                            <InputLabel for="categoria" value="Categoria *" />
                            <select
                                id="categoria"
                                v-model="form.categoria"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                            >
                                <option v-for="(label, key) in categorie" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.categoria" />
                        </div>
                    </div>
                </div>

                <!-- Note e stato -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b dark:border-gray-700 pb-2">Note e stato</h3>
                    <div>
                        <InputLabel for="note" value="Note interne" />
                        <textarea
                            id="note"
                            v-model="form.note"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"
                        />
                        <InputError class="mt-1" :message="form.errors.note" />
                    </div>
                    <div class="flex items-center gap-3">
                        <input id="attivo" v-model="form.attivo" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 shadow-sm" />
                        <label for="attivo" class="text-sm text-gray-700 dark:text-gray-300">Fornitore attivo</label>
                    </div>
                </div>

                <!-- Azioni -->
                <div class="flex gap-3">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        <CheckIcon class="size-4 me-2" aria-hidden="true" />Salva fornitore
                    </PrimaryButton>
                    <Link
                        :href="route('suppliers.index')"
                        class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla
                    </Link>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
