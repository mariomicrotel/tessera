<script setup>
import { CheckIcon, ArrowLeftIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    members: { type: Array, default: () => [] },
    requestForSelf: { type: Boolean, default: false },
    authMember: { type: Object, default: null },
});

const form = useForm({
    member_id: props.requestForSelf && props.authMember ? String(props.authMember.id) : '',
    refund_date: new Date().toISOString().slice(0, 10),
    items: props.requestForSelf ? [{ description: '', amount: '' }] : [],
});

const addItem = () => {
    form.items.push({ description: '', amount: '' });
};
const removeItem = (index) => {
    form.items.splice(index, 1);
};
const hasValidItem = () => form.items.some(i => Number(i.amount) > 0);
const canSubmit = () => {
    if (!props.requestForSelf) return true;
    return form.items.length >= 1 && hasValidItem();
};
</script>

<template>
    <AppLayout :title="requestForSelf ? 'Richiedi rimborso' : 'Nuovo rimborso'">
        <Head :title="requestForSelf ? 'Richiedi rimborso spese' : 'Nuovo rimborso spese'" />
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ requestForSelf ? 'Richiedi rimborso spese' : 'Nuovo rimborso spese' }}</h2>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form @submit.prevent="form.post(route('expense-refunds.store'))" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div v-if="requestForSelf && authMember">
                    <InputLabel value="Beneficiario" />
                    <p class="mt-1 text-gray-700 dark:text-gray-300">Richiedi rimborso per te stesso ({{ authMember.full_name }})</p>
                    <input type="hidden" v-model="form.member_id" :value="authMember.id" />
                    <InputError class="mt-1" :message="form.errors.member_id" />
                </div>
                <div v-else>
                    <InputLabel for="member_id" value="Beneficiario (socio) *" />
                    <select id="member_id" v-model="form.member_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                        <option value="">Seleziona socio</option>
                        <option v-for="m in members" :key="m.id" :value="m.id">{{ m.cognome }} {{ m.nome }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.member_id" />
                </div>
                <div>
                    <InputLabel for="refund_date" value="Data rimborso *" />
                    <TextInput id="refund_date" v-model="form.refund_date" type="date" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.refund_date" />
                </div>
                <!-- Per il socio: almeno una voce obbligatoria prima di inviare la richiesta -->
                <div v-if="requestForSelf" class="space-y-3 border-t border-gray-200 dark:border-gray-600 pt-4">
                    <InputLabel value="Voci di rimborso * (almeno una)" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">Inserisci almeno una voce con importo maggiore di zero per inviare la richiesta.</p>
                    <div v-for="(item, index) in form.items" :key="index" class="flex gap-2 items-center">
                        <input v-model="item.description" placeholder="Descrizione" class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" />
                        <input v-model="item.amount" type="number" step="0.01" min="0" placeholder="Importo €" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" />
                        <button type="button" @click="removeItem(index)" class="text-red-600 hover:underline" :disabled="form.items.length <= 1">Elimina</button>
                    </div>
                    <button type="button" @click="addItem" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                        <PlusIcon class="size-4" aria-hidden="true" />Aggiungi voce
                    </button>
                    <InputError class="mt-1" :message="form.errors.items" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing || (requestForSelf && !canSubmit())"><CheckIcon class="size-4 me-2" aria-hidden="true" />{{ requestForSelf ? 'Invia richiesta' : 'Crea e aggiungi voci' }}</PrimaryButton>
                    <Link :href="route('expense-refunds.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
