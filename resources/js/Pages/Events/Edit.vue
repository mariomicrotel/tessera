<script setup>
import { ref } from 'vue';
import { PencilSquareIcon, ArrowLeftIcon, PhotoIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    event: Object,
    max_poster_size: { type: String, default: '2M' },
});
const posterInput = ref(null);

function toDatetimeLocal(iso) {
    if (!iso) return '';
    return iso.slice(0, 16);
}

const form = useForm({
    title: props.event.title ?? '',
    start_at: toDatetimeLocal(props.event.start_at),
    end_at: toDatetimeLocal(props.event.end_at),
    max_participants: props.event.max_participants ?? '',
    description: props.event.description ?? '',
    solo_soci: !!props.event.solo_soci,
    poster: null,
});

function removePoster() {
    if (!confirm('Rimuovere la locandina?')) return;
    router.delete(route('events.poster.destroy', props.event.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Modifica evento">
        <Head :title="'Modifica: ' + event.title" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Modifica evento</h2>
                <Link :href="route('events.show', event.id)" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"><ArrowLeftIcon class="size-4" aria-hidden="true" />Dettaglio</Link>
            </div>
        </template>

        <div class="py-6 max-w-2xl mx-auto sm:px-6">
            <form @submit.prevent="form.transform((data) => ({ ...data, _method: 'PUT' })).post(route('events.update', event.id), { forceFormData: true })" class="space-y-4 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
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
                    <input
                        ref="posterInput"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="form.poster = $event.target.files?.[0] ?? null"
                    />
                    <div v-if="event.poster && !form.poster" class="mt-1 flex flex-wrap items-center gap-3">
                        <img :src="event.poster.url" :alt="event.poster.original_name" class="h-24 object-contain border border-gray-200 dark:border-gray-600 rounded" />
                        <div class="flex flex-col gap-2">
                            <SecondaryButton type="button" @click="posterInput?.click()">
                                <PhotoIcon class="size-4 me-2" aria-hidden="true" />
                                Sostituisci
                            </SecondaryButton>
                            <SecondaryButton type="button" @click="removePoster">
                                <TrashIcon class="size-4 me-2" aria-hidden="true" />
                                Rimuovi locandina
                            </SecondaryButton>
                        </div>
                    </div>
                    <div v-else class="mt-1 flex flex-wrap items-center gap-3">
                        <SecondaryButton type="button" @click="posterInput?.click()">
                            <PhotoIcon class="size-4 me-2" aria-hidden="true" />
                            {{ form.poster ? form.poster.name : 'Scegli immagine' }}
                        </SecondaryButton>
                    </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Opzionale. Dimensione massima: {{ max_poster_size }}.</p>
                    <InputError class="mt-1" :message="form.errors.poster" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton type="submit" :disabled="form.processing"><PencilSquareIcon class="size-4 me-2" aria-hidden="true" />Salva modifiche</PrimaryButton>
                    <Link :href="route('events.show', event.id)" class="inline-flex items-center gap-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md font-medium text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700"><ArrowLeftIcon class="size-4 me-1" aria-hidden="true" />Annulla</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
