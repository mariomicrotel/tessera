<script setup>
import { PencilSquareIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ location: Object, hasOtherLegalLocation: Boolean });

const form = useForm({
    name: props.location.name ?? '',
    address: props.location.address ?? '',
    tipo: props.location.tipo ?? 'operativa',
});
</script>

<template>
    <AppLayout title="Modifica sede">
        <Head :title="'Modifica: ' + location.name" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Modifica sede</h2>
                <Link :href="route('locations.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco</Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form @submit.prevent="form.put(route('locations.update', location.id))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="name" value="Nome *" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel for="address" value="Indirizzo" />
                    <TextInput id="address" v-model="form.address" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.address" />
                </div>
                <div>
                    <InputLabel for="tipo" value="Tipo *" />
                    <select id="tipo" v-model="form.tipo" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="operativa">Operativa</option>
                        <option value="legale" :disabled="hasOtherLegalLocation">Legale</option>
                    </select>
                    <p v-if="hasOtherLegalLocation" class="mt-1 text-sm text-amber-600 dark:text-amber-400">Esiste già un'altra sede legale. Può esserci solo una sede legale.</p>
                    <InputError class="mt-1" :message="form.errors.tipo" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing"><PencilSquareIcon class="size-4 me-2" aria-hidden="true" />Salva modifiche</PrimaryButton>
                    <Link :href="route('locations.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
