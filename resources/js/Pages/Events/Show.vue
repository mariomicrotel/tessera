<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PencilSquareIcon, UserPlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({ event: Object, members: Array });

const addMode = ref('socio'); // 'socio' | 'ospite'

const form = useForm({
    member_id: '',
    guest_name: '',
});

const soloSoci = computed(() => !!props.event.solo_soci);

const canAddMore = computed(() => {
    if (!props.event.max_participants) return true;
    const count = props.event.registrations?.length ?? 0;
    return count < props.event.max_participants;
});

const showAddForm = computed(() => {
    if (!canAddMore.value) return false;
    if (soloSoci.value) return (props.members?.length ?? 0) > 0;
    return true;
});

const canSubmit = computed(() => {
    if (soloSoci.value) return !!form.member_id;
    if (addMode.value === 'socio') return !!form.member_id;
    return !!form.guest_name?.trim();
});

function addRegistration() {
    if (soloSoci.value) {
        if (!form.member_id) return;
        form.transform((data) => ({ member_id: data.member_id })).post(route('events.register', props.event.id), {
            preserveScroll: true,
            onSuccess: () => form.reset('member_id'),
        });
        return;
    }
    if (addMode.value === 'socio') {
        if (!form.member_id) return;
        form.transform((data) => ({ member_id: data.member_id })).post(route('events.register', props.event.id), {
            preserveScroll: true,
            onSuccess: () => form.reset('member_id'),
        });
    } else {
        if (!form.guest_name?.trim()) return;
        form.transform((data) => ({ guest_name: data.guest_name.trim() })).post(route('events.register', props.event.id), {
            preserveScroll: true,
            onSuccess: () => form.reset('guest_name'),
        });
    }
}

function isGuest(r) {
    return r.member_id == null || !r.member;
}

function annullaIscrizione(registrationId) {
    if (!confirm('Annullare l\'iscrizione?')) return;
    router.delete(route('events.registrations.destroy', [props.event.id, registrationId]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout title="Evento">
        <Head :title="event.title" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ event.title }}</h2>
                <Link :href="route('events.edit', event.id)" class="inline-flex items-center gap-1 px-3 py-2 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"><PencilSquareIcon class="size-4" aria-hidden="true" />Modifica</Link>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto sm:px-6 space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div v-if="event.poster" class="mb-4">
                    <img :src="event.poster.url" :alt="event.poster.original_name" class="max-h-64 w-auto object-contain rounded border border-gray-200 dark:border-gray-600" />
                </div>
                <p v-if="event.description" class="mb-4">{{ event.description }}</p>
                <dl class="grid grid-cols-2 gap-4">
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Inizio</dt><dd>{{ event.start_at ? new Date(event.start_at).toLocaleString('it-IT') : '' }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">Iscritti</dt><dd>{{ event.registrations?.length ?? 0 }}{{ event.max_participants ? ' / ' + event.max_participants : '' }}</dd></div>
                    <div v-if="soloSoci" class="col-span-2"><dt class="text-sm text-gray-500 dark:text-gray-400">Riservato ai soci</dt><dd>Sì</dd></div>
                </dl>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium mb-4">Iscritti</h3>
                <template v-if="showAddForm">
                    <!-- Evento riservato ai soci: solo select socio -->
                    <form v-if="soloSoci" @submit.prevent="addRegistration" class="mb-4 flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1">
                            <InputLabel for="member_id" value="Aggiungi socio" />
                            <select
                                id="member_id"
                                v-model="form.member_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                            >
                                <option value="">Seleziona socio...</option>
                                <option v-for="m in members" :key="m.id" :value="m.id">
                                    {{ m.cognome }} {{ m.nome }}{{ m.numero_tessera ? ' (n. ' + m.numero_tessera + ')' : '' }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.member_id" />
                        </div>
                        <PrimaryButton type="submit" :disabled="form.processing || !canSubmit">
                            <UserPlusIcon class="size-4 me-2" aria-hidden="true" />
                            Aggiungi iscritto
                        </PrimaryButton>
                    </form>
                    <!-- Evento aperto: scelta socio o ospite -->
                    <form v-else @submit.prevent="addRegistration" class="mb-4 space-y-3">
                        <div class="flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input v-model="addMode" type="radio" value="socio" class="rounded-full border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                <span>Aggiungi socio</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input v-model="addMode" type="radio" value="ospite" class="rounded-full border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                <span>Aggiungi ospite</span>
                            </label>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div v-if="addMode === 'socio'" class="min-w-[200px] flex-1">
                                <InputLabel for="member_id" value="Socio" />
                                <select
                                    id="member_id"
                                    v-model="form.member_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm"
                                >
                                    <option value="">Seleziona socio...</option>
                                    <option v-for="m in members" :key="m.id" :value="m.id">
                                        {{ m.cognome }} {{ m.nome }}{{ m.numero_tessera ? ' (n. ' + m.numero_tessera + ')' : '' }}
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.member_id" />
                            </div>
                            <div v-else class="min-w-[200px] flex-1">
                                <InputLabel for="guest_name" value="Nome ospite" />
                                <TextInput id="guest_name" v-model="form.guest_name" class="mt-1 block w-full" placeholder="Nome e cognome" />
                                <InputError class="mt-1" :message="form.errors.guest_name" />
                            </div>
                            <PrimaryButton type="submit" :disabled="form.processing || !canSubmit">
                                <UserPlusIcon class="size-4 me-2" aria-hidden="true" />
                                Aggiungi iscritto
                            </PrimaryButton>
                        </div>
                    </form>
                </template>
                <p v-else-if="event.max_participants && (event.registrations?.length ?? 0) >= event.max_participants" class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    Numero massimo di partecipanti raggiunto.
                </p>
                <p v-else-if="soloSoci && !members?.length" class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    Tutti i soci sono già iscritti a questo evento.
                </p>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <li v-for="r in event.registrations" :key="r.id" class="py-2 flex items-center justify-between gap-2">
                        <span>
                            {{ r.display_name || (r.member ? r.member.cognome + ' ' + r.member.nome : r.guest_name || '') }}
                            <span v-if="isGuest(r)" class="ml-2 text-xs text-gray-500 dark:text-gray-400">(ospite)</span>
                        </span>
                        <button
                            type="button"
                            @click="annullaIscrizione(r.id)"
                            class="p-1.5 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                            title="Annulla iscrizione"
                        >
                            <XMarkIcon class="size-4" aria-hidden="true" />
                        </button>
                    </li>
                </ul>
                <p v-if="event.registrations?.length === 0" class="py-4 text-sm text-gray-500 dark:text-gray-400">Nessun iscritto.</p>
            </div>
        </div>
    </AppLayout>
</template>
