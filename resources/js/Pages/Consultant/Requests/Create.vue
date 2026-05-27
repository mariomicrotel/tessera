<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    entity: { type: Object, required: true },
});

const form = useForm({
    titolo:        '',
    descrizione:   '',
    priorita:      'normale',
    data_scadenza: '',
});

const submit = () => {
    form.post(route('consultant.requests.store', props.entity.slug));
};

const inputClass = 'mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-200 text-sm px-3 py-2';
const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
</script>

<template>
    <AppLayout :title="`Nuova richiesta — ${entity.name}`">
        <Head :title="`Nuova richiesta — ${entity.name}`" />
        <template #header>
            <div>
                <div class="flex items-center gap-1 mb-1">
                    <Link :href="route('consultant.requests.index', entity.slug)"
                        class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 inline-flex items-center gap-1">
                        <ArrowLeftIcon class="size-3.5" /> Richieste
                    </Link>
                </div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Nuova richiesta — {{ entity.name }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <form @submit.prevent="submit" class="space-y-5">

                        <!-- Titolo -->
                        <div>
                            <label for="titolo" :class="labelClass">Titolo *</label>
                            <input
                                id="titolo"
                                v-model="form.titolo"
                                type="text"
                                :class="inputClass"
                                placeholder="Es. Invia bilancio 2024, Documenti assemblea ordinaria..."
                                required
                            />
                            <p v-if="form.errors.titolo" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ form.errors.titolo }}</p>
                        </div>

                        <!-- Descrizione -->
                        <div>
                            <label for="descrizione" :class="labelClass">Descrizione / istruzioni</label>
                            <textarea
                                id="descrizione"
                                v-model="form.descrizione"
                                rows="4"
                                :class="inputClass"
                                placeholder="Specifica i documenti necessari, formato, scadenze normative..."
                            />
                            <p v-if="form.errors.descrizione" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ form.errors.descrizione }}</p>
                        </div>

                        <!-- Priorità + Scadenza -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="priorita" :class="labelClass">Priorità *</label>
                                <select id="priorita" v-model="form.priorita" :class="inputClass">
                                    <option value="bassa">Bassa</option>
                                    <option value="normale">Normale</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                                <p v-if="form.errors.priorita" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ form.errors.priorita }}</p>
                            </div>
                            <div>
                                <label for="data_scadenza" :class="labelClass">Data scadenza</label>
                                <input id="data_scadenza" v-model="form.data_scadenza" type="date" :class="inputClass" />
                                <p v-if="form.errors.data_scadenza" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ form.errors.data_scadenza }}</p>
                            </div>
                        </div>

                        <!-- Azioni -->
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <Link :href="route('consultant.requests.index', entity.slug)"
                                class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                Annulla
                            </Link>
                            <PrimaryButton type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Salvataggio...' : 'Crea richiesta' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
