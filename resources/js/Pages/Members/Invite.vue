<script setup>
import { CheckIcon, ArrowLeftIcon, EnvelopeIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    email: '',
});
</script>

<template>
    <AppLayout title="Invia invito">
        <Head title="Invia invito - Domanda di ammissione" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Invia invito per domanda di ammissione</h2>
                <Link :href="route('members.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Annulla</Link>
            </div>
        </template>

        <div class="py-6 max-w-xl mx-auto sm:px-6">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Inserisci l'indirizzo email del destinatario. Riceverà un link personale e a uso singolo per compilare il modulo di domanda di ammissione come socio. Il link scade dopo 7 giorni.
            </p>
            <form @submit.prevent="form.post(route('members.invites.store'))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="email" value="Email *" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        placeholder="nome@esempio.it"
                        required
                        autofocus
                    />
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        <EnvelopeIcon class="size-4 me-2" aria-hidden="true" />
                        Invia invito
                    </PrimaryButton>
                    <Link :href="route('members.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
