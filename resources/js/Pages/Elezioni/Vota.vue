<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ elezione: Object });

const form = useForm({
    candidatura_ids: [],
});
</script>

<template>
    <AppLayout :title="'Vota: ' + elezione.titolo">
        <Head :title="'Vota: ' + elezione.titolo" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Votazione: {{ elezione.titolo }}</h2>
                <Link :href="route('dashboard')" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300">Torna alla Dashboard</Link>
            </div>
        </template>

        <div class="py-6 max-w-xl mx-auto sm:px-6">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Puoi scegliere uno o più candidati. Il voto è anonimo e non può essere modificato.<span v-if="elezione.permetti_astenuti"> Puoi anche astenerti senza selezionare nessuno.</span></p>
            <form @submit.prevent="form.post(route('elezioni.vota.store', elezione.id))" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <fieldset class="space-y-3">
                    <legend class="sr-only">Candidati</legend>
                    <div v-for="c in elezione.candidature" :key="c.id" class="flex items-center rounded-md border border-gray-200 dark:border-gray-600 p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <input
                            :id="'cand-' + c.id"
                            v-model="form.candidatura_ids"
                            type="checkbox"
                            :value="c.id"
                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label :for="'cand-' + c.id" class="ml-3 block font-medium text-gray-900 dark:text-gray-100 cursor-pointer">
                            {{ c.member?.cognome }} {{ c.member?.nome }}
                        </label>
                    </div>
                </fieldset>
                <InputError class="mt-2" :message="form.errors.candidatura_ids" />
                <div class="mt-6 flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing || (!elezione.permetti_astenuti && form.candidatura_ids.length === 0)">Conferma voto</PrimaryButton>
                    <Link :href="route('dashboard')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
