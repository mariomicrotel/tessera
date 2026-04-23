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
    members:       Array,
    tasso_default: Number,
});

// Normalizza per SearchableMemberSelect (giuridiche: ragione_sociale → cognome)
const membersForSelect = computed(() => props.members.map(m => ({
    ...m,
    cognome: m.tipo_persona === 'giuridica' ? (m.ragione_sociale ?? '') : (m.cognome ?? ''),
    nome:    m.tipo_persona === 'giuridica' ? '' : (m.nome ?? ''),
})));

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    member_id:   '',
    tasso_annuo: props.tasso_default ?? 0.02,
    data:        today,
});

const fmtPct = (v) => ((Number(v) || 0) * 100).toFixed(2).replace('.', ',') + '%';
</script>

<template>
    <AppLayout title="Nuovo Libretto Prestito Sociale">
        <Head title="Nuovo Libretto Prestito Sociale" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Apri Libretto Prestito Sociale
                </h2>
                <Link :href="route('prestito-sociale.index')"
                    class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 text-sm">
                    <ArrowLeftIcon class="size-4" />Annulla
                </Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form
                @submit.prevent="form.post(route('prestito-sociale.store'))"
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

                <!-- Tasso annuo + Data -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="tasso_annuo" value="Tasso interesse annuo *" />
                        <TextInput
                            id="tasso_annuo"
                            v-model="form.tasso_annuo"
                            type="number"
                            min="0"
                            max="0.20"
                            step="0.0001"
                            class="mt-1 block w-full"
                            required
                        />
                        <p class="text-xs text-gray-400 mt-0.5">
                            Inserire come decimale (es. 0.02 = {{ fmtPct(0.02) }}).
                            Valore attuale: <strong>{{ fmtPct(form.tasso_annuo) }}</strong>
                        </p>
                        <InputError class="mt-1" :message="form.errors.tasso_annuo" />
                    </div>
                    <div>
                        <InputLabel for="data" value="Data apertura *" />
                        <TextInput
                            id="data"
                            v-model="form.data"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.data" />
                    </div>
                </div>

                <!-- Info box -->
                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 px-4 py-3 text-sm text-blue-700 dark:text-blue-300">
                    Il libretto verrà aperto con saldo zero. Il socio potrà effettuare depositi dal dettaglio del libretto.
                    Gli interessi sono soggetti a ritenuta fiscale del 26% (DPR 600/73 art. 26).
                </div>

                <!-- Azioni -->
                <div class="flex gap-3 pt-2">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        <CheckIcon class="size-4 me-2" />
                        Apri Libretto
                    </PrimaryButton>
                    <Link :href="route('prestito-sociale.index')"
                        class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700">
                        <ArrowLeftIcon class="size-4 me-1" />Annulla
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
