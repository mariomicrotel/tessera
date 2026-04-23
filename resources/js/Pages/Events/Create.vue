<script setup>
import { ref } from 'vue';
import { PlusIcon, ArrowLeftIcon, PhotoIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    max_poster_size: { type: String, default: '2M' },
});

const posterInput = ref(null);
const form = useForm({
    title: '',
    start_at: '',
    end_at: '',
    max_participants: '',
    description: '',
    solo_soci: false,
    poster: null,
});
</script>

<template>
    <AppLayout title="Nuovo evento">
        <Head title="Nuovo evento" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nuovo evento</h2>
                <Link :href="route('events.index')" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Elenco</Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form @submit.prevent="form.post(route('events.store'), { forceFormData: true })" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div>
                    <InputLabel for="title" value="Titolo *" />
                    <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.title" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="start_at" value="Data e ora inizio *" />
                        <TextInput id="start_at" v-model="form.start_at" type="datetime-local" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.start_at" />
                    </div>
                    <div>
                        <InputLabel for="end_at" value="Data e ora fine" />
                        <TextInput id="end_at" v-model="form.end_at" type="datetime-local" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.end_at" />
                    </div>
                </div>
                <div>
                    <InputLabel for="max_participants" value="Numero massimo partecipanti" />
                    <TextInput id="max_participants" v-model="form.max_participants" type="number" min="1" class="mt-1 block w-full" placeholder="Illimitato" />
                    <InputError class="mt-1" :message="form.errors.max_participants" />
                </div>
                <div>
                    <InputLabel for="description" value="Descrizione" />
                    <textarea id="description" v-model="form.description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" placeholder="Descrizione dell'evento..." />
                    <InputError class="mt-1" :message="form.errors.description" />
                </div>
                <div class="flex items-center gap-2">
                    <input id="solo_soci" v-model="form.solo_soci" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                    <InputLabel for="solo_soci" value="Riservato ai soci" class="mb-0" />
                </div>
                <div>
                    <InputLabel value="Locandina" />
                    <div class="mt-1 flex flex-wrap items-center gap-3">
                        <input
                            ref="posterInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="form.poster = $event.target.files?.[0] ?? null"
                        />
                        <SecondaryButton type="button" @click="posterInput?.click()">
                            <PhotoIcon class="size-4 me-2" aria-hidden="true" />
                            {{ form.poster ? form.poster.name : 'Scegli immagine' }}
                        </SecondaryButton>
                    </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Opzionale. Dimensione massima: {{ max_poster_size }}.</p>
                    <InputError class="mt-1" :message="form.errors.poster" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing"><PlusIcon class="size-4 me-2" aria-hidden="true" />Crea evento</PrimaryButton>
                    <Link :href="route('events.index')" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
