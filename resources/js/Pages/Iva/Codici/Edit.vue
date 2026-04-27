<script setup>
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    codice: Object,
    tipi: Object,
});

const form = useForm({
    codice: props.codice.codice,
    descrizione: props.codice.descrizione,
    percentuale: props.codice.percentuale,
    tipo: props.codice.tipo,
    natura_sdi: props.codice.natura_sdi,
    indetraibile_percentuale: props.codice.indetraibile_percentuale,
    attivo: props.codice.attivo,
});

const natureSdi = {
    N: 'Normale',
    E: 'Esente',
    F: 'Fuori campo',
    L: 'Non imponibile',
    R: 'Reverse charge',
    S: 'Split payment',
};

function submit() {
    form.put(route('iva.codici.update', props.codice.id), {
        onSuccess: () => {
            // Il redirect è gestito dal server
        },
    });
}
</script>

<template>
    <Head title="Modifica Codice IVA" />

    <AppLayout>
        <div class="max-w-2xl mx-auto px-4 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Modifica Codice IVA {{ codice.codice }}</h1>
                <Link :href="route('iva.codici.index')" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                    <ArrowLeftIcon class="w-4 h-4 mr-2" />
                    Indietro
                </Link>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="bg-white rounded-lg shadow p-6">
                <!-- Codice -->
                <div class="mb-4">
                    <InputLabel for="codice" value="Codice IVA *" />
                    <TextInput
                        id="codice"
                        v-model="form.codice"
                        type="text"
                        maxlength="10"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.codice" class="mt-2" />
                </div>

                <!-- Descrizione -->
                <div class="mb-4">
                    <InputLabel for="descrizione" value="Descrizione *" />
                    <TextInput
                        id="descrizione"
                        v-model="form.descrizione"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.descrizione" class="mt-2" />
                </div>

                <!-- Percentuale -->
                <div class="mb-4">
                    <InputLabel for="percentuale" value="Percentuale (%)" />
                    <TextInput
                        id="percentuale"
                        v-model.number="form.percentuale"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="form.errors.percentuale" class="mt-2" />
                </div>

                <!-- Tipo -->
                <div class="mb-4">
                    <InputLabel for="tipo" value="Tipo *" />
                    <select
                        id="tipo"
                        v-model="form.tipo"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        required
                    >
                        <option v-for="(label, key) in tipi" :key="key" :value="key">
                            {{ label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.tipo" class="mt-2" />
                </div>

                <!-- Natura SDI -->
                <div class="mb-4">
                    <InputLabel for="natura_sdi" value="Natura SDI *" />
                    <select
                        id="natura_sdi"
                        v-model="form.natura_sdi"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        required
                    >
                        <option v-for="(label, code) in natureSdi" :key="code" :value="code">
                            {{ code }} - {{ label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.natura_sdi" class="mt-2" />
                </div>

                <!-- Indetraibile -->
                <div class="mb-4">
                    <InputLabel for="indetraibile_percentuale" value="% Indetraibile" />
                    <TextInput
                        id="indetraibile_percentuale"
                        v-model.number="form.indetraibile_percentuale"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="form.errors.indetraibile_percentuale" class="mt-2" />
                </div>

                <!-- Attivo -->
                <div class="mb-6">
                    <label class="flex items-center">
                        <input
                            v-model="form.attivo"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                        <span class="ml-2 text-sm text-gray-700">Attivo</span>
                    </label>
                    <InputError :message="form.errors.attivo" class="mt-2" />
                </div>

                <!-- Alert: codice di sistema -->
                <div v-if="codice.di_sistema" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded text-sm text-blue-700">
                    ⓘ Questo codice IVA è di sistema e non può essere eliminato.
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-between">
                    <Link :href="route('iva.codici.index')" class="text-sm text-gray-600 hover:text-gray-900">
                        Annulla
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Salvataggio...' : 'Salva Modifiche' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
