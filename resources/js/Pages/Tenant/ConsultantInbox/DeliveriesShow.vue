<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    ArrowLeftIcon, PaperClipIcon, ArrowDownTrayIcon,
    CheckCircleIcon, XCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    delivery: { type: Object, required: true },
});

const $page = usePage();
const fmt = (s) => s ? new Date(s).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' }) : '—';

// ── Accetta / Contesta ────────────────────────────────────────────────
const showAcceptForm = ref(false);
const showContestForm = ref(false);

const acceptForm  = useForm({ note: '' });
const contestForm = useForm({ note: '' });

const submitAccept = () => {
    acceptForm.post(route('tenant.consultant-inbox.deliveries.accept', [$page.props.currentTenant?.slug, props.delivery.id]), {
        onSuccess: () => { showAcceptForm.value = false; },
    });
};
const submitContest = () => {
    contestForm.post(route('tenant.consultant-inbox.deliveries.contest', [$page.props.currentTenant?.slug, props.delivery.id]), {
        onSuccess: () => { showContestForm.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="`Consegna — ${delivery.titolo}`">
        <Head :title="`Consegna — ${delivery.titolo}`" />

        <template #header>
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Link :href="route('tenant.consultant-inbox.deliveries.index', $page.props.currentTenant?.slug)"
                        class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
                        <ArrowLeftIcon class="size-3" /> Consegne consulente
                    </Link>
                </div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ delivery.titolo }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Metadati -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Consulente</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ delivery.consulente?.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Tipo</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ delivery.tipo_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Stato</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ delivery.stato_label }}</dd>
                        </div>
                        <div v-if="delivery.data_consegna">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Consegnata</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ fmt(delivery.data_consegna) }}</dd>
                        </div>
                    </dl>

                    <div v-if="delivery.descrizione" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Descrizione</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ delivery.descrizione }}</p>
                    </div>

                    <div v-if="delivery.feedback_note"
                        class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-900/30 text-sm">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">La tua nota di feedback:</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ delivery.feedback_note }}</p>
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
                                        {{ doc.size_human }} · {{ fmt(doc.uploaded_at) }}
                                    </p>
                                    <p v-if="doc.note" class="text-xs text-gray-600 dark:text-gray-300 italic mt-0.5">{{ doc.note }}</p>
                                </div>
                            </div>
                            <a :href="doc.download_url" target="_blank"
                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 flex items-center gap-1 shrink-0">
                                <ArrowDownTrayIcon class="size-4" /> Scarica
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Azioni: accetta / contesta -->
                <div v-if="delivery.can_accept || delivery.can_contest"
                    class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
                        Conferma ricezione
                    </h3>

                    <!-- Form accetta -->
                    <div v-if="showAcceptForm" class="space-y-3 mb-4">
                        <textarea v-model="acceptForm.note" rows="2" maxlength="500"
                            placeholder="Nota opzionale (es. ringraziamento, conferma)..."
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700"></textarea>
                        <div class="flex gap-2">
                            <PrimaryButton @click="submitAccept" :disabled="acceptForm.processing" class="text-sm bg-green-600 hover:bg-green-700">
                                <CheckCircleIcon class="size-4 me-1.5" /> Conferma accettazione
                            </PrimaryButton>
                            <SecondaryButton @click="showAcceptForm = false" class="text-sm">Annulla</SecondaryButton>
                        </div>
                    </div>

                    <!-- Form contesta -->
                    <div v-else-if="showContestForm" class="space-y-3 mb-4">
                        <textarea v-model="contestForm.note" rows="3" maxlength="500" required
                            placeholder="Descrivi il problema (obbligatorio): es. file errato, importo non corretto, dati mancanti..."
                            class="w-full text-sm rounded-lg border-red-300 dark:border-red-700 bg-white dark:bg-gray-700"></textarea>
                        <p v-if="contestForm.errors.note" class="text-xs text-red-500">{{ contestForm.errors.note }}</p>
                        <div class="flex gap-2">
                            <PrimaryButton @click="submitContest" :disabled="!contestForm.note || contestForm.processing"
                                class="text-sm bg-red-600 hover:bg-red-700">
                                <XCircleIcon class="size-4 me-1.5" /> Invia contestazione
                            </PrimaryButton>
                            <SecondaryButton @click="showContestForm = false" class="text-sm">Annulla</SecondaryButton>
                        </div>
                    </div>

                    <!-- Bottoni iniziali -->
                    <div v-else class="flex gap-2">
                        <PrimaryButton @click="showAcceptForm = true" class="text-sm bg-green-600 hover:bg-green-700">
                            <CheckCircleIcon class="size-4 me-1.5" /> Accetta
                        </PrimaryButton>
                        <SecondaryButton @click="showContestForm = true" class="text-sm text-red-600">
                            <XCircleIcon class="size-4 me-1.5" /> Contesta
                        </SecondaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
