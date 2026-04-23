<script setup>
import { computed } from 'vue';
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ conti: Array });

const form = useForm({
    conto_da_id: '',
    conto_a_id: '',
    date: new Date().toISOString().slice(0, 10),
    importo: '',
    description: '',
});

const canSubmit = computed(() =>
    form.conto_da_id && form.conto_a_id && form.conto_da_id !== form.conto_a_id && form.date && form.importo !== '' && parseFloat(form.importo) > 0,
);
</script>

<template>
    <AppLayout title="Giroconto">
        <Head title="Giroconto" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Giroconto</h2>
                <Link :href="route('prima-nota.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Prima nota</Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Trasferisci un importo da un conto a un altro. Verranno create due righe in prima nota (uscita dal conto di partenza, entrata sul conto di destinazione).</p>
            <form @submit.prevent="form.post(route('prima-nota.giroconto.store'))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="conto_da_id" value="Da conto *" />
                    <select
                        id="conto_da_id"
                        v-model="form.conto_da_id"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                    >
                        <option value="">Seleziona conto di partenza</option>
                        <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.name }}{{ c.code ? ' (' + c.code + ')' : '' }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.conto_da_id" />
                </div>
                <div>
                    <InputLabel for="conto_a_id" value="A conto *" />
                    <select
                        id="conto_a_id"
                        v-model="form.conto_a_id"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                    >
                        <option value="">Seleziona conto di destinazione</option>
                        <option v-for="c in conti" :key="c.id" :value="c.id">{{ c.name }}{{ c.code ? ' (' + c.code + ')' : '' }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.conto_a_id" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="date" value="Data *" />
                        <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.date" />
                    </div>
                    <div>
                        <InputLabel for="importo" value="Importo * (positivo)" />
                        <TextInput id="importo" v-model="form.importo" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.importo" />
                    </div>
                </div>
                <div>
                    <InputLabel for="description" value="Descrizione (opzionale)" />
                    <TextInput id="description" v-model="form.description" class="mt-1 block w-full" placeholder="Se vuota: Giroconto da [conto A] verso [conto B]" />
                    <InputError class="mt-1" :message="form.errors.description" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing || !canSubmit">
                        <CheckIcon class="size-4 me-2" aria-hidden="true" />Registra giroconto
                    </PrimaryButton>
                    <Link :href="route('prima-nota.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
