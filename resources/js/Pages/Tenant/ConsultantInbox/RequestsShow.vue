<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ArrowLeftIcon, PaperClipIcon, CloudArrowUpIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    richiesta: { type: Object, required: true },
});

const fileInput = ref(null);
const form = useForm({ file: null, note: '' });

const onSelect = (e) => { form.file = e.target.files[0]; };

const submit = () => {
    form.post(route('tenant.consultant-inbox.requests.respond', [$page.props.currentTenant?.slug, props.richiesta.id]), {
        forceFormData: true,
        onSuccess: () => { form.reset(); if (fileInput.value) fileInput.value.value = ''; },
    });
};

import { usePage } from '@inertiajs/vue3';
const $page = usePage();

const fmt = (s) => s ? new Date(s).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' }) : '—';
const fmtDate = (s) => s ? new Date(s).toLocaleDateString('it-IT') : '—';
</script>

<template>
    <AppLayout :title="`Richiesta — ${richiesta.titolo}`">
        <Head :title="`Richiesta — ${richiesta.titolo}`" />

        <template #header>
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Link :href="route('tenant.consultant-inbox.requests.index', $page.props.currentTenant?.slug)"
                        class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
                        <ArrowLeftIcon class="size-3" /> Richieste consulente
                    </Link>
                </div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ richiesta.titolo }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Info richiesta -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Consulente</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ richiesta.consulente?.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Priorità</dt>
                            <dd class="text-gray-900 dark:text-gray-100 capitalize">{{ richiesta.priorita }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Stato</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ richiesta.stato }}</dd>
                        </div>
                        <div v-if="richiesta.data_scadenza">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Scadenza</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ fmtDate(richiesta.data_scadenza) }}</dd>
                        </div>
                    </dl>
                    <div v-if="richiesta.descrizione" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Descrizione</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ richiesta.descrizione }}</p>
                    </div>
                </div>

                <!-- Documenti -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
                        Documenti caricati ({{ richiesta.documenti?.length ?? 0 }})
                    </h3>
                    <ul v-if="richiesta.documenti?.length > 0" class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="d in richiesta.documenti" :key="d.id" class="py-2.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <PaperClipIcon class="size-4 text-gray-400 shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-gray-100 truncate">{{ d.filename_originale }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ d.size_human }} · da {{ d.uploaded_by ?? 'consulente' }} · {{ fmt(d.uploaded_at) }}
                                    </p>
                                    <p v-if="d.note" class="text-xs text-gray-600 dark:text-gray-300 mt-0.5 italic">{{ d.note }}</p>
                                </div>
                            </div>
                            <span v-if="d.uploaded_as_response"
                                class="shrink-0 text-xs px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                Risposta
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400 italic">Nessun documento caricato.</p>
                </div>

                <!-- Form upload risposta -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-3 flex items-center gap-2">
                        <CloudArrowUpIcon class="size-4" /> Carica documento di risposta
                    </h3>
                    <form @submit.prevent="submit" class="space-y-3">
                        <input ref="fileInput" type="file" required @change="onSelect"
                            class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-blue-600 file:text-white file:text-xs hover:file:bg-blue-700" />
                        <p v-if="form.errors.file" class="text-xs text-red-600">{{ form.errors.file }}</p>

                        <textarea v-model="form.note" rows="2" maxlength="500"
                            placeholder="Nota opzionale per il consulente..."
                            class="w-full text-sm rounded-lg border-blue-300 dark:border-blue-700 bg-white dark:bg-gray-800"></textarea>

                        <PrimaryButton type="submit" :disabled="!form.file || form.processing" class="text-sm">
                            <CloudArrowUpIcon class="size-4 me-1.5" />
                            {{ form.processing ? 'Caricamento…' : 'Invia al consulente' }}
                        </PrimaryButton>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
