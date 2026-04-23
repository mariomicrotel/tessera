<script setup>
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ elezione: Object, organi: Array, members: Array });

const form = useForm({
    organo_id: props.elezione.organo_id ?? '',
    titolo: props.elezione.titolo ?? '',
    data_elezione: props.elezione.data_elezione ? props.elezione.data_elezione.slice(0, 10) : '',
    permetti_astenuti: !!props.elezione.permetti_astenuti,
});
</script>

<template>
    <AppLayout title="Modifica elezione">
        <Head title="Modifica elezione" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Modifica elezione</h2>
                <Link :href="route('elezioni.show', elezione.id)" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro</Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form @submit.prevent="form.put(route('elezioni.update', elezione.id))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="organo_id" value="Organo (opzionale)" />
                    <select id="organo_id" v-model="form.organo_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                        <option value="">— Nessuno —</option>
                        <option v-for="o in organi" :key="o.id" :value="o.id">{{ o.nome }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel for="titolo" value="Titolo *" />
                    <TextInput id="titolo" v-model="form.titolo" class="mt-1 block w-full" maxlength="255" />
                    <InputError class="mt-1" :message="form.errors.titolo" />
                </div>
                <div>
                    <InputLabel for="data_elezione" value="Data elezione *" />
                    <TextInput id="data_elezione" v-model="form.data_elezione" type="date" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.data_elezione" />
                </div>
                <div class="flex items-center gap-2">
                    <input
                        id="permetti_astenuti"
                        v-model="form.permetti_astenuti"
                        type="checkbox"
                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                    />
                    <InputLabel for="permetti_astenuti" value="Permetti astenuti" class="!mb-0" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing"><CheckIcon class="size-4 me-2" aria-hidden="true" />Salva</PrimaryButton>
                    <Link :href="route('elezioni.show', elezione.id)" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
