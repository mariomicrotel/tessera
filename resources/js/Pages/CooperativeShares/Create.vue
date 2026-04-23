<script setup>
import { computed } from 'vue';
import { CheckIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SearchableMemberSelect from '@/Components/SearchableMemberSelect.vue';

const props = defineProps({
    members:                Array,
    valore_unitario_default: Number,
});

// Normalizza i membri per SearchableMemberSelect (che usa cognome + nome).
// Per le persone giuridiche usiamo ragione_sociale come "cognome" per la ricerca.
const membersForSelect = computed(() => props.members.map(m => ({
    ...m,
    cognome: m.tipo_persona === 'giuridica' ? (m.ragione_sociale ?? '') : (m.cognome ?? ''),
    nome:    m.tipo_persona === 'giuridica' ? '' : (m.nome ?? ''),
})));

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    member_id:       '',
    numero_quote:    1,
    valore_unitario: props.valore_unitario_default ?? 50,
    data:            today,
    note:            '',
});

const totaleCalcolato = computed(() =>
    ((Number(form.numero_quote) || 0) * (Number(form.valore_unitario) || 0)).toFixed(2)
);

const fmt = (v) => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Number(v) || 0);
</script>

<template>
    <AppLayout title="Nuova Sottoscrizione Quote">
        <Head title="Nuova Sottoscrizione Quote" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Nuova Sottoscrizione Quote
                </h2>
                <Link
                    :href="route('capitale-sociale.index')"
                    class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 text-sm"
                >
                    <ArrowLeftIcon class="size-4" aria-hidden="true" />Annulla
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form
                @submit.prevent="form.post(route('capitale-sociale.store'))"
                class="space-y-5 bg-white dark:bg-gray-800 shadow rounded-lg p-6"
            >

                <!-- Socio -->
                <div>
                    <InputLabel for="member_id" value="Socio *" />
                    <SearchableMemberSelect
                        id="member_id"
                        v-model="form.member_id"
                        :members="membersForSelect"
                        placeholder="Cerca e seleziona il socio…"
                        class="mt-1"
                    />
                    <InputError class="mt-1" :message="form.errors.member_id" />
                </div>

                <!-- Quote + Valore unitario -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="numero_quote" value="N° Quote *" />
                        <TextInput
                            id="numero_quote"
                            v-model="form.numero_quote"
                            type="number"
                            min="1"
                            step="1"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.numero_quote" />
                    </div>
                    <div>
                        <InputLabel for="valore_unitario" value="Valore unitario (€) *" />
                        <TextInput
                            id="valore_unitario"
                            v-model="form.valore_unitario"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.valore_unitario" />
                    </div>
                </div>

                <!-- Totale calcolato (readonly) -->
                <div class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 px-4 py-3 flex justify-between items-center">
                    <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">Totale da sottoscrivere</span>
                    <span class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">{{ fmt(totaleCalcolato) }}</span>
                </div>

                <!-- Data sottoscrizione -->
                <div>
                    <InputLabel for="data" value="Data sottoscrizione *" />
                    <TextInput
                        id="data"
                        v-model="form.data"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.data" />
                </div>

                <!-- Note -->
                <div>
                    <InputLabel for="note" value="Note" />
                    <textarea
                        id="note"
                        v-model="form.note"
                        rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                    ></textarea>
                </div>

                <!-- Azioni -->
                <div class="flex gap-3 pt-2">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        <CheckIcon class="size-4 me-2" aria-hidden="true" />
                        Sottoscrivi Quote
                    </PrimaryButton>
                    <Link
                        :href="route('capitale-sociale.index')"
                        class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla
                    </Link>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
