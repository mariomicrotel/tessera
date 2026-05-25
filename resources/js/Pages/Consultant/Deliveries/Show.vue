<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    ArrowLeftIcon, PaperClipIcon, ArrowDownTrayIcon, TrashIcon,
    PaperAirplaneIcon, XMarkIcon, CheckCircleIcon, ExclamationCircleIcon, ClockIcon, EyeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    entity:   { type: Object, required: true },
    delivery: { type: Object, required: true },
});

const STATE_ICON = {
    bozza: ClockIcon, consegnato: PaperAirplaneIcon, letto: EyeIcon,
    accettato: CheckCircleIcon, contestato: ExclamationCircleIcon,
};
const STATE_COLOR = {
    gray: 'text-gray-500', blue: 'text-blue-600',
    cyan: 'text-cyan-600', green: 'text-green-600', red: 'text-red-600',
};

const fmtDateTime = (s) => s ? new Date(s).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' }) : '—';

// ── Upload aggiuntivo ────────────────────────────────────────────────────
const fileInput = ref(null);
const uploadForm = useForm({ file: null, note: '' });

const onSelectFile = (e) => { uploadForm.file = e.target.files[0]; };
const submitUpload = () => {
    uploadForm.post(route('consultant.deliveries.files.upload', [props.entity.slug, props.delivery.id]), {
        forceFormData: true,
        onSuccess: () => { uploadForm.reset(); if (fileInput.value) fileInput.value.value = ''; },
    });
};

// ── Consegna ─────────────────────────────────────────────────────────────
const consegna = () => {
    if (! confirm('Inviare la consegna all\'ente? Riceverà una notifica email.')) return;
    router.post(route('consultant.deliveries.consegna', [props.entity.slug, props.delivery.id]));
};

// ── Elimina ──────────────────────────────────────────────────────────────
const destroy = () => {
    if (! confirm('Eliminare definitivamente la consegna e i suoi file?')) return;
    router.delete(route('consultant.deliveries.destroy', [props.entity.slug, props.delivery.id]));
};
</script>

<template>
    <AppLayout :title="`Consegna — ${delivery.titolo}`">
        <Head :title="`Consegna — ${delivery.titolo}`" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('consultant.deliveries.index', entity.slug)"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
                            <ArrowLeftIcon class="size-3" /> Consegne
                        </Link>
                    </div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ delivery.titolo }}
                    </h2>
                </div>
                <div class="flex gap-2">
                    <button v-if="delivery.is_bozza"
                        @click="consegna"
                        :disabled="delivery.documents_count === 0"
                        :title="delivery.documents_count === 0 ? 'Carica almeno un file' : 'Invia all\'ente'">
                        <PrimaryButton type="button" class="text-sm" :disabled="delivery.documents_count === 0">
                            <PaperAirplaneIcon class="size-4 me-1.5" /> Consegna
                        </PrimaryButton>
                    </button>
                    <button v-if="delivery.is_bozza || delivery.is_contestato" @click="destroy" class="text-sm">
                        <SecondaryButton type="button" class="text-sm text-red-600">
                            <TrashIcon class="size-4 me-1.5" /> Elimina
                        </SecondaryButton>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Status header -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-center gap-4">
                        <component :is="STATE_ICON[delivery.stato]"
                            :class="['size-10 shrink-0', STATE_COLOR[delivery.stato_badge_color]]" />
                        <div class="flex-1">
                            <h3 :class="['text-lg font-bold', STATE_COLOR[delivery.stato_badge_color]]">
                                {{ delivery.stato_label }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <template v-if="delivery.is_bozza">Solo tu la vedi. Carica file e clicca "Consegna".</template>
                                <template v-else-if="delivery.stato === 'consegnato'">Inviata all'ente. In attesa che la apra.</template>
                                <template v-else-if="delivery.stato === 'letto'">L'ente ha aperto il dettaglio. In attesa di feedback.</template>
                                <template v-else-if="delivery.is_accettato">L'ente ha accettato la consegna.</template>
                                <template v-else-if="delivery.is_contestato">L'ente ha contestato la consegna. Verifica le note.</template>
                            </p>
                            <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <div v-if="delivery.data_consegna">
                                    <p class="text-xs text-gray-400">Consegnata</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ fmtDateTime(delivery.data_consegna) }}</p>
                                </div>
                                <div v-if="delivery.data_lettura">
                                    <p class="text-xs text-gray-400">Letta</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ fmtDateTime(delivery.data_lettura) }}</p>
                                </div>
                                <div v-if="delivery.data_feedback">
                                    <p class="text-xs text-gray-400">Feedback</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ fmtDateTime(delivery.data_feedback) }}</p>
                                </div>
                            </div>
                            <div v-if="delivery.feedback_note"
                                class="mt-3 p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>Nota dell'ente:</strong> {{ delivery.feedback_note }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dettagli -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Tipo</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ delivery.tipo_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Creata</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ fmtDateTime(delivery.created_at) }}</dd>
                        </div>
                    </dl>

                    <div v-if="delivery.descrizione" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Descrizione</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ delivery.descrizione }}</p>
                    </div>
                </div>

                <!-- Allegati -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
                        Allegati ({{ delivery.documenti?.length ?? 0 }})
                    </h3>

                    <ul v-if="delivery.documenti?.length > 0" class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="doc in delivery.documenti" :key="doc.id"
                            class="py-2.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <PaperClipIcon class="size-4 text-gray-400 shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-gray-100 truncate">{{ doc.filename_originale }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ doc.size_human }} · {{ fmtDateTime(doc.uploaded_at) }}
                                    </p>
                                </div>
                            </div>
                            <a :href="doc.download_url" target="_blank"
                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 flex items-center gap-1">
                                <ArrowDownTrayIcon class="size-4" /> Scarica
                            </a>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400 italic">Nessun allegato caricato.</p>

                    <!-- Upload aggiuntivo (solo se bozza o consegnato) -->
                    <div v-if="delivery.is_bozza || delivery.stato === 'consegnato' || delivery.stato === 'letto'"
                        class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <form @submit.prevent="submitUpload" class="space-y-2">
                            <input ref="fileInput" type="file" @change="onSelectFile" required class="text-xs" />
                            <textarea v-model="uploadForm.note" rows="2" maxlength="500"
                                placeholder="Nota opzionale per questo file..."
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></textarea>
                            <PrimaryButton type="submit" :disabled="!uploadForm.file || uploadForm.processing" class="text-xs">
                                <PaperClipIcon class="size-3.5 me-1" /> {{ uploadForm.processing ? 'Upload…' : 'Aggiungi file' }}
                            </PrimaryButton>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
