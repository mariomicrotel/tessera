<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { PlusIcon, TrashIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ prossimaVersione: String });

const form = useForm({
    versione:          props.prossimaVersione ?? '',
    titolo:            'Statuto',
    stato:             'bozza',
    data_approvazione: '',
    data_deposito:     '',
    note:              '',
    clausole:          [],
});

const aggiungiArticolo = () => {
    form.clausole.push({
        numero_articolo: String(form.clausole.length + 1),
        titolo:          '',
        testo:           '',
        articolo_cts:    '',
        ordine:          form.clausole.length,
    });
};

const rimuoviArticolo = (i) => form.clausole.splice(i, 1);

const submit = () => form.post(route('ets.statuto.store'));
</script>

<template>
    <AppLayout title="Nuovo Statuto">
        <Head title="Nuovo Statuto" />
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('ets.statuto.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <ArrowLeftIcon class="size-5" />
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Nuovo Statuto</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Dati generali -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg px-6 py-6 space-y-4">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">Informazioni generali</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Versione *</label>
                                <input v-model="form.versione" type="text" maxlength="20" placeholder="es. 2025-v1" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                                <InputError :message="form.errors.versione" class="mt-1" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stato *</label>
                                <select v-model="form.stato" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="bozza">Bozza</option>
                                    <option value="approvato">Approvato</option>
                                </select>
                                <InputError :message="form.errors.stato" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Titolo *</label>
                            <input v-model="form.titolo" type="text" maxlength="255" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            <InputError :message="form.errors.titolo" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data approvazione</label>
                                <input v-model="form.data_approvazione" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data deposito</label>
                                <input v-model="form.data_deposito" type="date" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note</label>
                            <textarea v-model="form.note" rows="2" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                        </div>
                    </div>

                    <!-- Articoli -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">Articoli</h3>
                            <button type="button" @click="aggiungiArticolo" class="inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                <PlusIcon class="size-4 me-1" />Aggiungi articolo
                            </button>
                        </div>

                        <div v-if="form.clausole.length === 0" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            Nessun articolo. Puoi aggiungere gli articoli ora o dopo la creazione.
                        </div>

                        <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div v-for="(c, i) in form.clausole" :key="i" class="px-6 py-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Articolo {{ i + 1 }}</span>
                                    <button type="button" @click="rimuoviArticolo(i)" class="text-red-400 hover:text-red-600">
                                        <TrashIcon class="size-4" />
                                    </button>
                                </div>
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Numero articolo *</label>
                                        <input v-model="c.numero_articolo" type="text" maxlength="10" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Titolo</label>
                                        <input v-model="c.titolo" type="text" maxlength="255" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Riferimento CTS</label>
                                        <input v-model="c.articolo_cts" type="text" maxlength="80" placeholder="es. art.5 D.Lgs.117/2017" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Testo *</label>
                                    <textarea v-model="c.testo" rows="4" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('ets.statuto.index')">
                            <SecondaryButton type="button">Annulla</SecondaryButton>
                        </Link>
                        <PrimaryButton type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Salvataggio…' : 'Crea statuto' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
