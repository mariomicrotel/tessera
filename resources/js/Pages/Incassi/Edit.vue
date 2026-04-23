<script setup>
import { ArrowLeftIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    incasso: Object,
    conti: Array,
});

const form = useForm({
    amount: String(Number(props.incasso.amount).toFixed(2)),
    paid_at: props.incasso.paid_at
        ? new Date(props.incasso.paid_at).toISOString().slice(0, 10)
        : '',
    conto_id: props.incasso.conto_id ?? '',
    description: props.incasso.description ?? '',
    donor_name: props.incasso.donor_name ?? '',
});

const typeLabels = { quota: 'Quota', donazione: 'Donazione', altro: 'Generico' };

const backHref = () => {
    if (props.incasso.type === 'donazione') return route('donazioni.index');
    if (props.incasso.type === 'altro') return route('incassi-generici.index');
    return route('quote-sociali.index');
};

const receiptSent = props.incasso.receipt?.sent_at != null;

const submit = () => {
    form.put(route('incassi.update', props.incasso.id));
};
</script>

<template>
    <AppLayout title="Modifica incasso">
        <Head title="Modifica incasso" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Modifica incasso #{{ incasso.id }}
                </h2>
                <Link :href="backHref()" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300">
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />Indietro
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <!-- Avviso ricevuta già inviata -->
            <div v-if="receiptSent" class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg">
                <p class="text-sm text-red-700 dark:text-red-400">
                    <strong>Attenzione:</strong> la ricevuta è già stata inviata per email. La modifica non è consentita.
                </p>
            </div>

            <!-- Avviso ricevuta emessa ma non inviata -->
            <div v-else-if="incasso.receipt" class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                <p class="text-sm text-yellow-700 dark:text-yellow-400">
                    <strong>Attenzione:</strong> è stata emessa una ricevuta (n. {{ incasso.receipt.number }}) per questo incasso.
                    Dopo la modifica il PDF non verrà rigenerato automaticamente; usa la funzione "Rigenera PDF" dalla pagina ricevuta.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <!-- Tipo (sola lettura) -->
                <div>
                    <InputLabel value="Tipo" />
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 font-medium">
                        {{ typeLabels[incasso.type] ?? incasso.type }}
                    </p>
                </div>

                <!-- Socio / Donatore (sola lettura) -->
                <div>
                    <InputLabel :value="incasso.type === 'donazione' ? 'Donatore' : 'Socio'" />
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        <template v-if="incasso.member">
                            {{ incasso.member.cognome }} {{ incasso.member.nome }}
                        </template>
                        <template v-else-if="incasso.donor_name">
                            {{ incasso.donor_name }} <span class="text-gray-400">(inserito a mano)</span>
                        </template>
                        <template v-else>—</template>
                    </p>
                </div>

                <!-- Nome donatore modificabile (solo se inserito a mano) -->
                <div v-if="!incasso.member && (incasso.type === 'donazione' || incasso.type === 'altro')">
                    <InputLabel for="donor_name" value="Nome donatore / destinatario" />
                    <TextInput
                        id="donor_name"
                        v-model="form.donor_name"
                        type="text"
                        class="mt-1 block w-full"
                        :disabled="receiptSent"
                    />
                    <InputError class="mt-1" :message="form.errors.donor_name" />
                </div>

                <!-- Importo e Data -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="amount" value="Importo (€) *" />
                        <TextInput
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="mt-1 block w-full"
                            required
                            :disabled="receiptSent"
                        />
                        <InputError class="mt-1" :message="form.errors.amount" />
                    </div>
                    <div>
                        <InputLabel for="paid_at" value="Data *" />
                        <TextInput
                            id="paid_at"
                            v-model="form.paid_at"
                            type="date"
                            class="mt-1 block w-full"
                            required
                            :disabled="receiptSent"
                        />
                        <InputError class="mt-1" :message="form.errors.paid_at" />
                    </div>
                </div>

                <!-- Conto -->
                <div>
                    <InputLabel for="conto_id" value="Conto di destinazione *" />
                    <select
                        id="conto_id"
                        v-model="form.conto_id"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                        required
                        :disabled="receiptSent"
                    >
                        <option v-for="c in conti" :key="c.id" :value="c.id">
                            {{ c.name }}{{ c.code ? ' (' + c.code + ')' : '' }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.conto_id" />
                </div>

                <!-- Causale -->
                <div>
                    <InputLabel for="description" value="Causale / Descrizione" />
                    <TextInput
                        id="description"
                        v-model="form.description"
                        class="mt-1 block w-full"
                        :disabled="receiptSent"
                    />
                    <InputError class="mt-1" :message="form.errors.description" />
                </div>

                <!-- Prima nota (info) -->
                <div v-if="incasso.prima_nota_entry" class="text-sm text-gray-500 dark:text-gray-400">
                    Il movimento di prima nota collegato verrà aggiornato automaticamente.
                </div>

                <!-- Azioni -->
                <div class="flex gap-2 pt-2">
                    <PrimaryButton type="submit" :disabled="form.processing || receiptSent">
                        <CheckIcon class="size-4 me-2" aria-hidden="true" />Salva modifiche
                    </PrimaryButton>
                    <Link
                        :href="route('incassi.show', incasso.id)"
                        class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
