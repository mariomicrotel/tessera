<script setup>
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    display_name: '',
});
</script>

<template>
    <AppLayout title="Nuova tipologia socio">
        <Head title="Nuova tipologia socio" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nuova tipologia socio</h2>
                <Link :href="route('member-types.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco</Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form @submit.prevent="form.post(route('member-types.store'))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="name" value="Codice (nome interno) *" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" placeholder="es. volontario" maxlength="50" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Solo lettere minuscole, numeri e trattini. Usato nei dati tecnici.</p>
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel for="display_name" value="Nome visualizzato *" />
                    <TextInput id="display_name" v-model="form.display_name" class="mt-1 block w-full" placeholder="es. Volontario" maxlength="100" />
                    <InputError class="mt-1" :message="form.errors.display_name" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing"><CheckIcon class="size-4 me-2" aria-hidden="true" />Salva</PrimaryButton>
                    <Link :href="route('member-types.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
