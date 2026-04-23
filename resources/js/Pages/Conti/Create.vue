<script setup>
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    code: '',
    type: 'cassa',
    iban: '',
    ordine: 0,
    attivo: true,
});
</script>

<template>
    <AppLayout title="Nuovo conto">
        <Head title="Nuovo conto" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nuovo conto</h2>
                <Link :href="route('conti.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco</Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form @submit.prevent="form.post(route('conti.store'))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="name" value="Nome *" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel for="code" value="Codice" />
                    <TextInput id="code" v-model="form.code" class="mt-1 block w-full" placeholder="es. Cassa, CC1" />
                    <InputError class="mt-1" :message="form.errors.code" />
                </div>
                <div>
                    <InputLabel for="type" value="Tipo *" />
                    <select id="type" v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                        <option value="cassa">Cassa</option>
                        <option value="banca">Banca</option>
                        <option value="altro">Altro</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.type" />
                </div>
                <div v-show="form.type === 'banca'">
                    <InputLabel for="iban" value="IBAN" />
                    <TextInput id="iban" v-model="form.iban" class="mt-1 block w-full" placeholder="es. IT60X0542811101000000123456" maxlength="34" />
                    <InputError class="mt-1" :message="form.errors.iban" />
                </div>
                <div>
                    <InputLabel for="ordine" value="Ordine" />
                    <TextInput id="ordine" v-model.number="form.ordine" type="number" min="0" class="mt-1 block w-full" />
                    <InputError class="mt-1" :message="form.errors.ordine" />
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input v-model="form.attivo" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Attivo</span>
                    </label>
                    <InputError class="mt-1" :message="form.errors.attivo" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing"><CheckIcon class="size-4 me-2" aria-hidden="true" />Crea conto</PrimaryButton>
                    <Link :href="route('conti.index')"><SecondaryButton type="button">Annulla</SecondaryButton></Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
