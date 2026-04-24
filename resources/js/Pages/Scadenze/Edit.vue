<script setup>
import { useForm } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    scadenza: Object,
    tipi:     Object,
    stati:    Object,
    conti:    Array,
});

const form = useForm({
    tipo:          props.scadenza.tipo,
    descrizione:   props.scadenza.descrizione,
    importo:       props.scadenza.importo ?? '',
    data_scadenza: props.scadenza.data_scadenza,
    stato:         props.scadenza.stato,
    riferimento:   props.scadenza.riferimento ?? '',
    conto_id:      props.scadenza.conto_id ?? '',
    ricorrente:    props.scadenza.ricorrente,
    note:          props.scadenza.note ?? '',
});

const submit = () => form.put(route('scadenze.update', props.scadenza));
</script>

<template>
    <AppLayout title="Modifica Scadenza">
        <Head title="Modifica Scadenza" />
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Modifica Scadenza — {{ scadenza.descrizione }}
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <form @submit.prevent="submit" class="space-y-5">

                        <!-- Tipo -->
                        <div>
                            <InputLabel value="Tipo" />
                            <select v-model="form.tipo" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                                <option v-for="(label, key) in tipi" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError :message="form.errors.tipo" />
                        </div>

                        <!-- Stato -->
                        <div>
                            <InputLabel value="Stato" />
                            <select v-model="form.stato" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                                <option v-for="(label, key) in stati" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError :message="form.errors.stato" />
                        </div>

                        <!-- Descrizione -->
                        <div>
                            <InputLabel value="Descrizione *" />
                            <TextInput v-model="form.descrizione" type="text" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.descrizione" />
                        </div>

                        <!-- Importo e Data scadenza -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Importo (€)" />
                                <TextInput v-model="form.importo" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                                <InputError :message="form.errors.importo" />
                            </div>
                            <div>
                                <InputLabel value="Data scadenza *" />
                                <input v-model="form.data_scadenza" type="date" required
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" />
                                <InputError :message="form.errors.data_scadenza" />
                            </div>
                        </div>

                        <!-- Riferimento -->
                        <div>
                            <InputLabel value="Riferimento" />
                            <TextInput v-model="form.riferimento" type="text" class="mt-1 block w-full" />
                            <InputError :message="form.errors.riferimento" />
                        </div>

                        <!-- Conto -->
                        <div>
                            <InputLabel value="Conto (per il pagamento)" />
                            <select v-model="form.conto_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                                <option value="">— Nessuno —</option>
                                <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
                            </select>
                            <InputError :message="form.errors.conto_id" />
                        </div>

                        <!-- Ricorrente -->
                        <div class="flex items-center gap-3">
                            <input id="ricorrente" v-model="form.ricorrente" type="checkbox"
                                   class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm" />
                            <label for="ricorrente" class="text-sm text-gray-700 dark:text-gray-300">
                                Scadenza ricorrente
                            </label>
                        </div>

                        <!-- Note -->
                        <div>
                            <InputLabel value="Note" />
                            <textarea v-model="form.note" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm"></textarea>
                        </div>

                        <!-- Azioni -->
                        <div class="flex justify-end gap-3 pt-2">
                            <Link :href="route('scadenze.index')">
                                <SecondaryButton type="button">Annulla</SecondaryButton>
                            </Link>
                            <PrimaryButton :disabled="form.processing">Aggiorna</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
